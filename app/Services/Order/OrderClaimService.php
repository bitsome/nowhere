<?php

namespace App\Services\Order;

use App\Models\Conversation;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;

/**
 * 운행 가져오기(claim) 라이프사이클 — 요청/승인/거절의 비즈니스 규칙을 담당한다.
 */
class OrderClaimService
{
    public function __construct(
        private readonly OrderOfferService $offerService,
    ) {}

    /**
     * 마켓의 공개 운행을 가져오기 요청한다.
     * 요청 시점에 '수락 대기'가 되고, 등록자가 승인해야 내 운행이 된다.
     */
    public function claim(User $actor, Order $order): void
    {
        abort_unless(in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
        ], true), 403, '현재 상태에서는 가져올 수 없습니다.');

        abort_unless($order->user_id !== $actor->id, 403, '본인 운행은 가져올 수 없습니다.');

        abort_unless($order->claimant_user_id === null, 403, '이미 다른 드라이버가 가져오기를 요청했습니다.');

        $registrantId = $order->user_id;

        // 첫 가져오기라면 원 등록자를 기록 (상호 리뷰 대상 식별)
        if ($order->original_owner_id === null) {
            $order->forceFill(['original_owner_id' => $registrantId]);
        }

        $order->forceFill([
            'status' => Order::STATUS_ACCEPTANCE_PENDING,
            'claimed_at' => now(),
            'claimant_user_id' => $actor->id,
        ])->save();

        // 운행이 마켓에서 벗어났으므로 남아 있는 요금 제안은 모두 정리
        $this->offerService->cancelPendingFor($order);

        // 등록자에게 승인 요청 알림
        $registrant = User::query()->find($registrantId);

        if ($registrant !== null && $registrantId !== $actor->id) {
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
     * 승인 요청 카드 메시지의 상태를 확정으로 갱신한다.
     * 같은 운행으로 쌓인 모든 '승인 요청' 카드를 한 번에 확정해
     * 이미 처리된 카드에서 승인 버튼이 남아 있지 않도록 한다.
     */
    private function resolveApprovalCard(Order $order, User $registrant, User $claimant, string $status): void
    {
        $messages = Conversation::query()
            ->where('order_id', $order->id)
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
     * 가져오기 요청을 등록자가 승인한다 — 요청한 드라이버에게 운행이 넘어간다.
     */
    public function approve(User $registrant, Order $order): void
    {
        abort_unless($order->user_id === $registrant->id, 403, '운행 등록자만 승인할 수 있습니다.');

        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        abort_unless($order->claimant_user_id !== null, 403, '가져오기 요청이 없습니다.');

        $claimant = User::query()->find($order->claimant_user_id);

        $order->forceFill([
            'user_id' => $order->claimant_user_id,
            'claimant_user_id' => null,
            'status' => Order::STATUS_ACCEPTED,
        ])->save();

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
        }

        // 채팅의 '승인 요청' 카드를 '승인 확인됨' 카드로 확정
        if ($claimant !== null) {
            $this->resolveApprovalCard($order, $registrant, $claimant, 'approved');
        }
    }

    /**
     * 가져오기 요청을 요청자(드라이버)가 직접 철회한다 — 운행이 마켓으로 돌아간다.
     */
    public function withdraw(Order $order): void
    {
        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        $order->forceFill([
            'status' => Order::STATUS_PUBLISHED,
            'claimant_user_id' => null,
            'claimed_at' => null,
        ])->save();
    }

    /**
     * 가져오기 요청을 등록자가 거절한다 — 운행이 마켓으로 돌아간다.
     */
    public function reject(User $registrant, Order $order): void
    {
        abort_unless($order->user_id === $registrant->id, 403, '운행 등록자만 거절할 수 있습니다.');

        abort_unless($order->status === Order::STATUS_ACCEPTANCE_PENDING, 403, '승인 대기 상태가 아닙니다.');

        $claimant = User::query()->find($order->claimant_user_id);

        $order->forceFill([
            'status' => Order::STATUS_PUBLISHED,
            'claimant_user_id' => null,
            'claimed_at' => null,
        ])->save();

        if ($claimant !== null) {
            $claimant->notify(new OrderNotification(
                '가져오기 요청 거절됨',
                "{$order->rideSummary()} 운행 가져오기 요청이 거절되었습니다.",
                $order->id,
            ));

            // 채팅의 '승인 요청' 카드를 '승인 거절됨' 카드로 확정
            $this->resolveApprovalCard($order, $registrant, $claimant, 'rejected');
        }
    }
}
