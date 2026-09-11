<?php

namespace App\Services\Order;

use App\Models\BehaviorEvent;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderOffer;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\BehaviorEventService;
use App\Services\MatchService;
use App\Services\Settlement\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 운행 상태 전이 — 라이프사이클 규칙 검증, 알림, XP, 기사 상태 연동, 자동 매칭을 담당한다.
 */
class OrderTransitionService
{
    /**
     * 미매칭 공개 운행의 유예 시간 — 운행 시작 시각이 이 시간만큼 지나도 매칭되지 않으면
     * 자동 취소한다. 마켓 커트오프(시작 후 2시간)와 같은 기준이라 화면에서 이미 사라진
     * 운행만 정리되고, 등록자는 안내와 함께 다시 등록할 수 있다.
     */
    public const PUBLISHED_CLOSE_AFTER_HOURS = 2;

    public function __construct(
        private readonly MatchService $matchService,
        private readonly OrderClaimService $claimService,
        private readonly OrderOfferService $offerService,
        private readonly SettlementService $settlementService,
        private readonly BehaviorEventService $behaviorService,
    ) {}

    /**
     * 라이프사이클 규칙에 따라 운행 상태를 전환한다.
     */
    public function transition(User $actor, Order $order, string $status, ?string $cancelReason = null, ?int $actualRevenue = null): void
    {
        // 관리자 보류(B-2) — 진행을 동결한다. 해제 전까지 일반 사용자는 어떤 상태 변경도 할 수 없다.
        abort_if((bool) $order->admin_hold, 409, '관리자가 보류한 운행입니다. 해결 전까지 진행할 수 없습니다.');

        // 등록자가 자기 공개 운행을 직접 수행 — 기사 모집 없이 바로 운행 확정으로 넘긴다.
        // 가져오기 신청·승인 단계를 건너뛰므로 claim 서비스가 전담한다.
        if ($order->status === Order::STATUS_PUBLISHED && $status === Order::STATUS_ACCEPTED) {
            $this->claimService->selfDrive($actor, $order);

            return;
        }

        // 수락 대기(가져오기 요청) 상태의 승인/복귀는 claim 서비스가 전담한다 —
        // 신청 건 정리·알림·채팅 카드 확정까지 함께 처리되어야 하므로 이 경로로 위임한다.
        if ($order->status === Order::STATUS_ACCEPTANCE_PENDING
            && in_array($status, [Order::STATUS_ACCEPTED, Order::STATUS_PUBLISHED], true)) {
            if ($status === Order::STATUS_ACCEPTED) {
                // 등록자 승인 — 특정 신청을 지정하지 않으면 대표(가장 최근) 신청을 승인한다
                $this->claimService->approve($actor, $order);
            } elseif ($order->hasPendingClaimBy($actor->id)) {
                // 요청자 본인의 신청 철회
                $this->claimService->withdraw($order, $actor);
            } else {
                // 등록자가 대표 신청을 거절
                $representative = OrderClaim::query()
                    ->where('order_id', $order->id)
                    ->pending()
                    ->where('driver_id', $order->claimant_user_id)
                    ->latest('id')
                    ->first();

                if ($representative !== null) {
                    $this->claimService->reject($actor, $order, $representative);
                }
            }

            return;
        }

        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => ['전환할 수 없는 상태입니다.'],
            ]);
        }

        // 마켓 공개 전 필수 입력 검증 — 빈 운행이 마켓에 노출되는 것을 막는다
        if ($status === Order::STATUS_PUBLISHED) {
            $publishError = $order->publishRequirementError();

            if ($publishError !== null) {
                throw ValidationException::withMessages([
                    'status' => [$publishError],
                ]);
            }
        }

        // 취소 사유를 먼저 반영 — 상태 전이 이벤트(타임라인)에 사유가 함께 남도록 한다
        if ($status === Order::STATUS_CANCELLED && filled($cancelReason)) {
            $order->forceFill(['cancel_reason' => $cancelReason])->save();
        }

        $order->transitionTo($status);

        // 운행이 확정(수락)되면 마켓에서 벗어나므로 남은 요금 제안을 정리한다
        if ($order->status === Order::STATUS_ACCEPTED) {
            $this->offerService->cancelPendingFor($order);
        }

        // 운행 시간·실제 수익 기록 — 운행 시작 시각, 완료 시각(+실제 수익)을 남긴다
        if ($status === Order::STATUS_DRIVING) {
            $times = $order->ride_step_times ?? [];

            // 첫 시작이면 '운행시작' 단계의 기록 시각을 함께 남긴다 (재시작 시 이미 기록된 단계는 유지)
            if ($order->ride_step === null) {
                $times[Order::RIDE_STEP_START] = now()->toIso8601String();
            }

            $order->forceFill([
                'started_at' => now(),
                // 운행 시작 단계 기록 — 카드 단계 스테퍼의 첫 단계(운행시작). 재시작 시 이미 기록된 단계는 유지
                'ride_step' => $order->ride_step ?? Order::RIDE_STEP_START,
                'ride_step_times' => $times,
            ])->save();
        } elseif ($status === Order::STATUS_COMPLETED) {
            $order->forceFill([
                'completed_at' => now(),
                'actual_revenue' => $actualRevenue ?? $order->actual_revenue,
            ])->save();
        }

        // 취소 사유는 전이 전에 함께 반영해 타임라인 이벤트에 남긴다 (위에서 처리)
        $owner = User::query()->find($order->user_id);

        // 정산 확정 — 완료된 운행이 정산(settled)되면 원장에 금액·수수료를 확정한다
        if ($status === Order::STATUS_SETTLED) {
            $this->settlementService->createFor($order);
        }

        // 자동 매칭 — 운행이 마켓에 공개되면 조건에 맞는 기사에게 제안 알림
        if ($status === Order::STATUS_PUBLISHED) {
            $this->matchService->matchForOrder($order);
        }

        // 기사 상태 자동 연동 — 완료/취소 시 '운행 중' 해제 → 온라인 복귀
        if ($owner !== null && in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            $owner->driver()->where('status', Driver::STATUS_ON_TRIP)->update([
                'status' => Driver::STATUS_ONLINE,
                'status_updated_at' => now(),
            ]);
        }

        // 레벨링: 운행 완료 +50, 정산 완료 +30 XP (운행 소유자에게)
        if ($owner !== null) {
            if ($status === Order::STATUS_COMPLETED) {
                $owner->addXp(50, 'order_completed', '운행 완료');
            } elseif ($status === Order::STATUS_SETTLED) {
                $owner->addXp(30, 'order_settled', '정산 완료');
            }

            $statusLabel = Order::statusOptions()[$order->status] ?? $order->status;

            $owner->notify(new OrderNotification(
                '운행 상태 변경',
                "{$order->rideSummary()} 운행의 상태가 '{$statusLabel}'(으)로 변경되었습니다.",
                $order->id,
            ));
        }

        // 행동 로그 — 완료/취소는 개인화 학습의 핵심 결과 신호 (행위 주체·취소 사유 기록)
        if ($status === Order::STATUS_COMPLETED) {
            $this->behaviorService->record($actor, BehaviorEvent::EVENT_RIDE_COMPLETED, $order->id, [
                'role' => $actor->role,
            ]);
        } elseif ($status === Order::STATUS_CANCELLED) {
            $this->behaviorService->record($actor, BehaviorEvent::EVENT_RIDE_CANCELLED, $order->id, [
                'role' => $actor->role,
                'cancel_reason' => $cancelReason,
            ]);
        }
    }

    /**
     * 미매칭 공개 운행 자동 취소 — 시작 시각 + 유예(PUBLISHED_CLOSE_AFTER_HOURS)가 지나도록
     * 아무도 가져가지 않은 공개/거래중 운행을 자동으로 취소한다 (스케줄러).
     *
     * 정상 운행은 자동 처리·문제만 관리자 개입 원칙에 따라, 오래 방치된 미매칭 운행을 정리해
     * 등록자 대기열과 마켓 상태를 깨끗하게 유지한다. 관리자 보류/숨김 운행은 시스템이 건드리지
     * 않고, 처리 시 등록자에게 알림과 함께 대기 제안 기사에게도 만료 안내를 보낸다.
     */
    public function autoClosePastDuePublished(int $limit = 50): int
    {
        $cutoff = now('Asia/Seoul')->subHours(self::PUBLISHED_CLOSE_AFTER_HOURS);
        $cutoffDate = $cutoff->format('Y-m-d');
        $cutoffTime = $cutoff->format('H:i');

        $orders = Order::query()
            ->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING])
            ->where('admin_hold', false)
            ->where('is_hidden', false)
            ->whereNull('claimed_at')
            ->whereNotNull('service_date')
            ->where('service_date', '!=', '')
            ->whereNotNull('service_time')
            ->where('service_time', '!=', '')
            ->where(function ($date) use ($cutoffDate, $cutoffTime) {
                $date->where('service_date', '<', $cutoffDate)
                    ->orWhere(function ($sameDay) use ($cutoffDate, $cutoffTime) {
                        $sameDay->where('service_date', $cutoffDate)
                            ->where('service_time', '<', $cutoffTime);
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($orders as $order) {
            // 대기 제안 기사 — 취소 직전 목록을 미리 확보해 만료 안내를 보낸다
            $offerDrivers = User::query()
                ->whereIn(
                    'id',
                    OrderOffer::query()
                        ->where('order_id', $order->id)
                        ->where('status', OrderOffer::STATUS_PENDING)
                        ->pluck('driver_id'),
                )
                ->get();

            $registrant = User::query()->find($order->original_owner_id ?? $order->user_id);

            DB::transaction(function () use ($order) {
                // 취소 사유를 먼저 반영 — 상태 전이 타임라인 이벤트에 사유가 함께 남는다
                $order->forceFill(['cancel_reason' => '시작 시각이 지나도록 매칭되지 않아 자동 취소되었습니다.'])->save();
                $order->transitionTo(Order::STATUS_CANCELLED);

                // 남은 대기 요금 제안을 정리한다 (기사 안내는 아래에서 별도 발송)
                $this->offerService->cancelPendingFor($order);
            });

            foreach ($offerDrivers as $driver) {
                $driver->notify(new OrderNotification(
                    '요금 제안 만료',
                    "{$order->rideSummary()} 운행이 매칭되지 않아 취소되어 요금 제안이 자동으로 철회되었습니다.",
                    $order->id,
                ));
            }

            if ($registrant !== null) {
                $registrant->notify(new OrderNotification(
                    '운행 자동 취소',
                    "{$order->rideSummary()} 운행이 시작 시각이 지나도록 매칭되지 않아 자동으로 취소되었습니다. 필요하면 같은 일정으로 다시 등록할 수 있습니다.",
                    $order->id,
                ));
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * 운행중 세부 단계를 다음 단계로 진행한다 — 운행 수행자(기사) 본인만 가능.
     * 운행시작 → 픽업장소 도착 → 승객 도착 → 출발 → 도착지로 이동중 순으로 진행하고,
     * 마지막 '도착지 도착'을 기록하는 순간 운행이 완료 처리된다 (목적지 도착 = 완료).
     */
    public function advanceRideStep(User $actor, Order $order, ?int $actualRevenue = null): string
    {
        abort_unless($order->user_id === $actor->id, 403, '운행 수행자만 단계를 진행할 수 있습니다.');

        abort_unless($order->status === Order::STATUS_DRIVING, 403, '운행중 상태에서만 단계를 진행할 수 있습니다.');

        $next = $order->nextRideStep();

        abort_if($next === null, 409, '이미 마지막 단계입니다.');

        // 각 단계가 기록된 시각을 함께 남긴다 — 스테퍼 아래 추적 시간 표시용
        $times = $order->ride_step_times ?? [];
        $times[$next] = now()->toIso8601String();

        $order->forceFill([
            'ride_step' => $next,
            'ride_step_times' => $times,
        ])->save();

        // 목적지 도착 = 운행 완료 — 마지막 단계를 기록하는 순간 완료 전이(시각·수익·기사 복귀·XP)까지 처리한다.
        // 수행 기사는 완료가 마지막이고, 이후 정산은 등록자가 처리한다.
        if ($next === Order::RIDE_STEP_ARRIVED) {
            $this->transition($actor, $order, Order::STATUS_COMPLETED, null, $actualRevenue);
        }

        return $next;
    }

    /**
     * 완료된 운행을 선택해 일괄 정산 처리한다.
     * 정산은 운행 등록자(원 등록자)만 할 수 있다 — 진행자는 '정산 대기중'으로 대기한다.
     *
     * @return int 정산 처리된 운행 수
     */
    public function batchSettle(User $user, array $ids): int
    {
        $orders = Order::query()
            ->whereIn('id', $ids)
            ->where(function ($query) use ($user) {
                $query->where('original_owner_id', $user->id)
                    ->orWhere(function ($sub) use ($user) {
                        // 남에게 넘어가지 않은 본인 등록 운행 (직접 수행 포함)
                        $sub->where('user_id', $user->id)->whereNull('original_owner_id');
                    });
            })
            ->where('status', Order::STATUS_COMPLETED)
            ->get();

        foreach ($orders as $order) {
            // 정산 원장 확정 후 상태를 정산(settled)으로 전환한다
            $this->settlementService->createFor($order);
            $order->transitionTo(Order::STATUS_SETTLED);
        }

        return $orders->count();
    }
}
