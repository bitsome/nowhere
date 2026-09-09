<?php

namespace App\Services\Order;

use App\Models\BehaviorEvent;
use App\Models\Conversation;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderEvent;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\BehaviorEventService;
use App\Services\MatchService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 운행 가져오기(claim) 라이프사이클 — 요청/승인/거절의 비즈니스 규칙을 담당한다.
 *
 * 한 운행에 여러 드라이버가 동시에 신청(order_claims)할 수 있다.
 * 승인은 신청 건(claim) 단위로 처리되고, 승인된 신청자를 제외한 나머지 대기 신청은 자동 거절된다.
 */
class OrderClaimService
{
    /**
     * 거절·철회된 드라이버의 재신청 잠금 시간(초) — 같은 운행에 반복 신청을 막는다.
     */
    public const CLAIM_LOCK_SECONDS = 30;

    /**
     * 가져오기 요청 자동 만료 시간(초) — 이 시간 안에 등록자가 승인하지 않으면
     * 자동 거절된 것으로 간주해 목록에서 휴지통으로 옮긴다.
     */
    public const CLAIM_EXPIRE_SECONDS = 1800;

    public function __construct(
        private readonly OrderOfferService $offerService,
        private readonly MatchService $matchService,
        private readonly NotificationService $notificationService,
        private readonly BehaviorEventService $behaviorService,
    ) {}

    /**
     * 승인 대기로 30분이 지난 운행의 가져오기 요청을 일괄 자동 철회한다 (백그라운드 스케줄러).
     * 요청이 만료되면 운행은 마켓으로 복귀하고, 승인하지 않은 등록자에게 만료 사실을 알린다.
     * 마켓에 다시 열린 운행은 아직 알림받지 못한 온라인 기사에게 재매칭된다.
     *
     * @return int 자동 만료 처리된 운행 수
     */
    public function autoExpireDueOrders(): int
    {
        $orders = Order::query()
            ->where('status', Order::STATUS_ACCEPTANCE_PENDING)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<=', now()->subSeconds(self::CLAIM_EXPIRE_SECONDS))
            ->where('admin_hold', false) // 보류(B-2) 운행은 시스템도 건드리지 않는다
            ->limit(50)
            ->get();

        $processed = 0;

        foreach ($orders as $order) {
            // 만료로 철회되는 신청자 목록 — 시스템 자동 처리라 행동 주체는 신청했던 기사들
            $claimants = User::query()
                ->whereIn('id', $order->pendingClaims()->distinct()->pluck('driver_id'))
                ->get();

            DB::transaction(function () use ($order) {
                $pending = $order->pendingClaims()->get();

                if ($pending->isEmpty()) {
                    return;
                }

                $order->pendingClaims()->update(['status' => OrderClaim::STATUS_WITHDRAWN]);

                // 남은 대기 신청이 없으므로 마켓으로 복귀한다 (재신청 잠금은 걸지 않는다 — 30분 대기 후 재신청 가능)
                $this->syncOrderAfterClaimResolved($order, (int) $pending->first()->driver_id, withLock: false);

                // 시스템 자동 처리 사실을 운행 타임라인에 남긴다
                OrderEvent::create([
                    'order_id' => $order->id,
                    'user_id' => null,
                    'event' => 'claim_auto_expired',
                    'from_status' => Order::STATUS_ACCEPTANCE_PENDING,
                    'to_status' => Order::STATUS_PUBLISHED,
                    'note' => '30분 내 승인되지 않아 요청이 만료되었고 운행이 마켓으로 복귀했습니다.',
                ]);
            });

            // 행동 로그 — 등록자가 30분 내 응답하지 않아 요청이 자동 철회됨
            foreach ($claimants as $claimant) {
                $this->behaviorService->record($claimant, BehaviorEvent::EVENT_CLAIM_WITHDRAWN, $order->id, ['cause' => 'auto']);
            }

            // 등록자(원 등록자 우선)에게 만료 안내 — 본인이 승인하지 않아 자동으로 다시 열렸다.
            // 재공개 뒤 같은 운행이 다시 만료돼도 1시간 안에는 중복 알림을 보내지 않는다 (알림 피로도 관리)
            $registrant = User::query()->find($order->original_owner_id ?? $order->user_id);

            if ($registrant !== null) {
                $this->notificationService->notifyOnce(
                    $registrant,
                    '가져오기 요청 만료',
                    $order->id,
                    "{$order->rideSummary()} 운행의 가져오기 요청이 30분 내 승인되지 않아 만료되었습니다. 운행이 다시 마켓에 열렸습니다.",
                );
            }

            // 아직 매칭 알림을 받지 못한 기사에게 다시 제안한다 (기존 수신자는 자동 제외)
            $this->matchService->matchForOrder($order);

            $processed++;
        }

        return $processed;
    }

    /**
     * 마켓의 공개 운행을 가져오기 요청한다.
     * 이미 승인 대기(다른 드라이버 신청) 상태여도 내 신청이 없으면 추가로 신청할 수 있다.
     *
     * @param  string|null  $batchId  일괄 요청 그룹 키 — 같은 추천1 체인에서 한 번에 보낸 요청끼리 묶는다.
     */
    public function claim(User $actor, Order $order, ?string $batchId = null): void
    {
        abort_unless($actor->role === User::ROLE_DRIVER, 403, '기사만 운행을 가져올 수 있습니다.');

        // 제재 상태 — 운행 제한·정지 계정은 운행을 가져올 수 없다 (B-2)
        abort_unless($actor->canOperate(), 403, '정지·제한된 계정으로는 운행을 가져올 수 없습니다.');

        abort_unless($order->user_id !== $actor->id, 403, '본인 운행은 가져올 수 없습니다.');

        // 동시에 여러 드라이버가 같은 운행에 신청해도 상태·중복을 안전하게 처리하도록
        // 행 잠금으로 직렬화한 뒤 다시 검사한다 (검사-저장 원자화).
        DB::transaction(function () use ($actor, $order, $batchId) {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            abort_unless($locked !== null, 404, '운행을 찾을 수 없습니다.');

            abort_unless(in_array($locked->status, [
                Order::STATUS_PUBLISHED,
                Order::STATUS_TRADING,
                Order::STATUS_ACCEPTANCE_PENDING,
            ], true), 403, '현재 상태에서는 가져올 수 없습니다.');

            // 관리자 개입(B-2) — 숨김(마켓 제외)·보류(진행 동결) 운행은 가져올 수 없다
            abort_if((bool) $locked->is_hidden, 403, '숨겨진 운행입니다.');
            abort_if((bool) $locked->admin_hold, 409, '관리자가 보류한 운행입니다.');

            // 중복 신청 방지 — 같은 드라이버가 이미 대기 중인 신청을 남겼으면 다시 신청할 수 없다
            abort_if($locked->hasPendingClaimBy($actor->id), 409, '이미 이 운행을 가져오기 신청했습니다.');

            // 재신청 잠금 — 거절·철회된 드라이버는 30초 동안 같은 운행에 다시 신청할 수 없다
            if ($locked->claim_lock_driver_id === $actor->id
                && $locked->claim_lock_until !== null
                && $locked->claim_lock_until->isFuture()) {
                abort(429, '방금 신청이 처리되었습니다. '.self::CLAIM_LOCK_SECONDS.'초 후 다시 신청할 수 있습니다.');
            }

            // 첫 가져오기라면 원 등록자를 기록 (상호 리뷰 대상 식별)
            if ($locked->original_owner_id === null) {
                $locked->forceFill(['original_owner_id' => $locked->user_id]);
            }

            OrderClaim::create([
                'order_id' => $locked->id,
                'driver_id' => $actor->id,
                'status' => OrderClaim::STATUS_PENDING,
                'batch_id' => $batchId,
            ]);

            // 첫 신청이면 승인 대기로 전환, 이후 신청은 대표(가장 최근) 신청자만 갱신
            $locked->forceFill([
                'status' => Order::STATUS_ACCEPTANCE_PENDING,
                'claimed_at' => $locked->claimed_at ?? now(),
                'claimant_user_id' => $actor->id,
                'claim_batch_id' => $batchId,
            ])->save();
        });

        // 컨트롤러 응답이 최신 상태를 반환하도록 원본 인스턴스도 동기화
        $order->refresh();

        // 행동 로그 — 기사가 가져오기 신청 (개인화: 신청한 운행의 특징 학습)
        $this->behaviorService->record($actor, BehaviorEvent::EVENT_CLAIM, $order->id);

        // 운행이 마켓에서 벗어났으므로 남아 있는 요금 제안은 모두 정리
        $this->offerService->cancelPendingFor($order);

        // 등록자에게 승인 요청 알림 (가져오기 요청 후에도 등록자는 user_id에 그대로 남는다)
        $registrant = User::query()->find($order->user_id);

        if ($registrant !== null && $registrant->id !== $actor->id) {
            $registrant->notify(new OrderNotification(
                '운행 가져오기 요청',
                "드라이버가 {$order->rideSummary()} 운행을 가져오기 요청했습니다. 승인하면 운행이 진행됩니다.",
                $order->id,
            ));

            // 채팅에도 요청을 남긴다 — 등록자 대화방에 승인 요청 이벤트 전송
            $this->notifyViaChat($actor, $registrant, $order, 'approval', ['order_id' => $order->id, 'status' => 'pending']);
        }
    }

    /**
     * 왕복 체인에 포함된 여러 운행을 등록자들에게 일괄로 가져오기 요청한다.
     * 한 건이 실패해도 나머지는 계속 진행하고, 건별 결과를 반환한다.
     *
     * @param  array<int, int>  $orderIds
     * @return array<int, array{id: int, ok: bool, message: string}>
     */
    public function claimBatch(User $actor, array $orderIds): array
    {
        $results = [];

        // 한 번의 일괄 요청은 하나의 그룹 키를 공유한다 — 내 마켓 요청보냄에서 묶어 보여준다
        $batchId = (string) Str::uuid();

        foreach (array_unique(array_filter($orderIds)) as $orderId) {
            $order = Order::query()->find($orderId);

            if ($order === null) {
                $results[] = ['id' => (int) $orderId, 'ok' => false, 'message' => '운행을 찾을 수 없습니다.'];

                continue;
            }

            try {
                $this->claim($actor, $order, $batchId);
                $results[] = ['id' => $order->id, 'ok' => true, 'message' => '가져오기 요청 완료'];
            } catch (HttpException $exception) {
                $results[] = ['id' => $order->id, 'ok' => false, 'message' => $exception->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 운행 기준 등록자-요청자 대화방에 요청/상태 이벤트 메시지를 남긴다.
     * 이미 같은 운행으로 대화가 있으면 재사용하고, 없으면 새로 만든다.
     *
     * @param  array<string, mixed>  $payload
     */
    private function notifyViaChat(User $sender, User $recipient, Order $order, string $type, array $payload): void
    {
        $conversation = Conversation::query()
            ->where('order_id', $order->id)
            ->whereHas('users', fn ($q) => $q->where('users.id', $sender->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $recipient->id))
            ->first();

        if ($conversation === null) {
            $conversation = Conversation::create(['order_id' => $order->id]);
            $conversation->users()->attach([$sender->id, $recipient->id]);
        }

        $conversation->messages()->create([
            'user_id' => $sender->id,
            'type' => $type,
            'payload' => $payload,
            'body' => $this->requestSummary($type, $payload),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    /**
     * 요청/상태 이벤트의 채팅 카드 요약 문구.
     *
     * @param  array<string, mixed>  $payload
     */
    private function requestSummary(string $type, array $payload): string
    {
        return match ($type) {
            'approval' => (($payload['status'] ?? '') === 'approved')
                ? '운행 시작 승인을 수락했습니다'
                : ((($payload['status'] ?? '') === 'rejected')
                    ? '운행 시작 승인을 거절했습니다'
                    : '운행 시작 승인을 요청했습니다'),
            default => '요청',
        };
    }

    /**
     * 해당 등록자-신청자 대화방의 '승인 요청' 카드 상태를 확정으로 갱신한다.
     * 같은 운행에 신청자가 여러 명이어도 각자의 카드만 확정한다.
     */
    private function resolveApprovalCard(Order $order, User $registrant, User $claimant, string $status): void
    {
        $messages = Conversation::query()
            ->where('order_id', $order->id)
            ->whereHas('users', fn ($q) => $q->where('users.id', $registrant->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $claimant->id))
            ->with('messages')
            ->get()
            ->flatMap(fn (Conversation $c) => $c->messages)
            ->filter(fn ($m) => ($m->type ?? '') === 'approval' && ($m->payload['status'] ?? '') === 'pending');

        if ($messages->isEmpty()) {
            return;
        }

        foreach ($messages as $message) {
            $payload = $message->payload ?? [];
            $payload['status'] = $status;
            $message->forceFill([
                'payload' => $payload,
                'body' => $this->requestSummary('approval', ['status' => $status]),
            ])->save();
        }

        $messages->first()->conversation?->forceFill(['last_message_at' => now()])->save();
    }

    /**
     * 가져오기 요청이 자동 만료됐는지 — 첫 요청 시점(claimed_at)부터 CLAIM_EXPIRE_SECONDS가 지나면 만료.
     */
    public function claimExpired(Order $order): bool
    {
        return $order->claimed_at !== null
            && $order->claimed_at->addSeconds(self::CLAIM_EXPIRE_SECONDS)->isPast();
    }

    /**
     * 가져오기 신청을 등록자가 승인한다 — 해당 신청자의 드라이버에게 운행이 넘어간다.
     * 다른 신청자들의 대기 신청은 모두 자동 거절 처리된다.
     */
    public function approve(User $registrant, Order $order, ?OrderClaim $claim = null): void
    {
        abort_unless($order->user_id === $registrant->id, 403, '운행 등록자만 승인할 수 있습니다.');

        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        // 특정 신청을 지정하지 않으면 대표 신청자(가장 최근)의 신청을 승인한다 (하위 호환)
        $claim ??= OrderClaim::query()
            ->where('order_id', $order->id)
            ->pending()
            ->where('driver_id', $order->claimant_user_id)
            ->latest('id')
            ->first();

        abort_unless($claim !== null, 409, '가져오기 신청이 없습니다.');

        abort_unless($claim->order_id === $order->id, 409, '다른 운행의 신청입니다.');

        abort_unless($claim->status === OrderClaim::STATUS_PENDING, 409, '이미 처리된 신청입니다.');

        // 30분 안에 승인하지 않으면 자동 거절 — 만료된 요청은 승인할 수 없다
        abort_if($this->claimExpired($order), 409, '요청이 만료되어 자동 거절되었습니다.');

        $claimant = $claim->driver;

        DB::transaction(function () use ($order, $claim) {
            // 승인된 신청자에게 운행을 넘기고, 나머지 대기 신청은 모두 거절 처리
            $order->pendingClaims()
                ->where('id', '!=', $claim->id)
                ->update(['status' => OrderClaim::STATUS_REJECTED]);

            $claim->forceFill(['status' => OrderClaim::STATUS_APPROVED])->save();

            $order->forceFill([
                'user_id' => $claim->driver_id,
                'claimant_user_id' => null,
                'status' => Order::STATUS_ACCEPTED,
                // 배차 승인(수락) 시점 — 진행중 목록의 '승인받은 시간'에 사용
                'approved_at' => $order->approved_at ?? now(),
                'claim_lock_driver_id' => null,
                'claim_lock_until' => null,
            ])->save();
        });

        // 운행이 확정되었으므로 남아 있는 요금 제안은 모두 정리
        $this->offerService->cancelPendingFor($order);

        // 기사 상태 자동 연동 — 승인된 순간부터 '운행 중'
        if ($claimant !== null) {
            $claimant->driver()->updateOrCreate(
                ['user_id' => $claimant->id],
                ['status' => Driver::STATUS_ON_TRIP, 'status_updated_at' => now()],
            );

            $claimant->addXp(20, 'order_claimed', '운행 가져오기 (수락)');

            $claimant->notify(new OrderNotification(
                '운행 가져오기 승인됨',
                "{$order->rideSummary()} 운행 가져오기가 승인되었습니다. 운행을 진행할 수 있습니다.",
                $order->id,
            ));

            // 채팅의 '승인 요청' 카드를 '승인 확인됨' 카드로 확정
            $this->resolveApprovalCard($order, $registrant, $claimant, 'approved');
        }

        // 아쉽게 승인받지 못한 나머지 신청자들에게 안내
        $losers = OrderClaim::query()
            ->where('order_id', $order->id)
            ->where('status', OrderClaim::STATUS_REJECTED)
            ->with('driver')
            ->get();

        foreach ($losers as $loser) {
            if ($loser->driver !== null && $loser->driver_id !== $claimant?->id) {
                $loser->driver->notify(new OrderNotification(
                    '가져오기 완료됨',
                    "{$order->rideSummary()} 운행이 다른 드라이버에게 가져와졌습니다.",
                    $order->id,
                ));
            }
        }
    }

    /**
     * 가져오기 신청을 요청자(드라이버)가 직접 철회한다.
     * 남은 대기 신청이 없으면 운행이 마켓으로 돌아간다.
     */
    public function withdraw(Order $order, User $actor): void
    {
        $claim = OrderClaim::query()
            ->where('order_id', $order->id)
            ->pending()
            ->where('driver_id', $actor->id)
            ->first();

        abort_unless($claim !== null, 403, '대기 중인 가져오기 신청이 없습니다.');

        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        DB::transaction(function () use ($order, $claim) {
            $claim->forceFill(['status' => OrderClaim::STATUS_WITHDRAWN])->save();
            $this->syncOrderAfterClaimResolved($order, $claim->driver_id);
        });

        // 행동 로그 — 기사가 직접 신청을 철회 (개인화: 이 운행에 대한 관심 포기 신호)
        $this->behaviorService->record($actor, BehaviorEvent::EVENT_CLAIM_WITHDRAWN, $order->id, ['cause' => 'manual']);
    }

    /**
     * 만료된 가져오기 신청을 자동 철회한다 — 운행이 마켓으로 돌아간다.
     * 재신청 잠금을 걸지 않아 30분 대기 후 바로 다시 요청할 수 있다.
     */
    public function withdrawExpired(Order $order, User $actor): void
    {
        $claim = OrderClaim::query()
            ->where('order_id', $order->id)
            ->pending()
            ->where('driver_id', $actor->id)
            ->first();

        abort_unless($claim !== null, 403, '대기 중인 가져오기 신청이 없습니다.');

        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        abort_if(! $this->claimExpired($order), 409, '아직 만료되지 않은 요청입니다.');

        DB::transaction(function () use ($order, $claim) {
            $claim->forceFill(['status' => OrderClaim::STATUS_WITHDRAWN])->save();
            $this->syncOrderAfterClaimResolved($order, $claim->driver_id, withLock: false);
        });

        // 행동 로그 — 만료된 요청을 기사가 직접 정리 (수동·만료와 구분: cause=expired)
        $this->behaviorService->record($actor, BehaviorEvent::EVENT_CLAIM_WITHDRAWN, $order->id, ['cause' => 'expired']);
    }

    /**
     * 가져오기 신청을 등록자가 거절한다 — 해당 신청만 거절되고,
     * 남은 대기 신청이 없으면 운행이 마켓으로 돌아간다.
     * 거절 사유(선택)는 기록되어 기사에게 전달된다.
     */
    public function reject(User $registrant, Order $order, OrderClaim $claim, ?string $reason = null): void
    {
        abort_unless($order->user_id === $registrant->id, 403, '운행 등록자만 거절할 수 있습니다.');

        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        abort_unless($claim->order_id === $order->id, 409, '다른 운행의 신청입니다.');

        abort_unless($claim->status === OrderClaim::STATUS_PENDING, 409, '이미 처리된 신청입니다.');

        $claimant = $claim->driver;

        DB::transaction(function () use ($order, $claim, $reason) {
            $claim->forceFill([
                'status' => OrderClaim::STATUS_REJECTED,
                'reject_reason' => $reason,
            ])->save();
            $this->syncOrderAfterClaimResolved($order, $claim->driver_id);
        });

        if ($claimant !== null) {
            $message = "{$order->rideSummary()} 운행 가져오기 요청이 거절되었습니다.";

            if ($reason !== null && $reason !== '') {
                $message .= ' 사유: '.$reason;
            }

            $claimant->notify(new OrderNotification(
                '가져오기 요청 거절됨',
                $message,
                $order->id,
            ));

            // 채팅의 '승인 요청' 카드를 '승인 거절됨' 카드로 확정
            $this->resolveApprovalCard($order, $registrant, $claimant, 'rejected');

            // 행동 로그 — 등록자가 이 기사의 신청을 거절 (운행·조건 불일치 피드백 신호)
            $this->behaviorService->record($claimant, BehaviorEvent::EVENT_CLAIM_REJECTED, $order->id);
        }
    }

    /**
     * 신청이 처리(거절/철회)된 뒤 운행 상태를 재계산한다.
     * 남은 대기 신청이 있으면 승인 대기 유지(대표 신청자 갱신),
     * 없으면 마켓(published)으로 복귀하고 처리된 드라이버에 재신청 잠금을 건다.
     */
    private function syncOrderAfterClaimResolved(Order $order, int $resolvedDriverId, bool $withLock = true): void
    {
        $remaining = $order->pendingClaims()->get();

        if ($remaining->isNotEmpty()) {
            // 다른 신청자가 아직 대기 중 — 승인 대기 유지, 대표 신청자만 가장 최근 신청자로 갱신
            $order->forceFill([
                'claimant_user_id' => $remaining->sortByDesc('id')->first()->driver_id,
            ])->save();

            return;
        }

        $fill = [
            'status' => Order::STATUS_PUBLISHED,
            'claimant_user_id' => null,
            'claimed_at' => null,
        ];

        if ($withLock) {
            // 처리된 드라이버는 30초 동안 같은 운행에 재신청할 수 없다
            $fill['claim_lock_driver_id'] = $resolvedDriverId;
            $fill['claim_lock_until'] = now()->addSeconds(self::CLAIM_LOCK_SECONDS);
        }

        $order->forceFill($fill)->save();
    }
}
