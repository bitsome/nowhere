<?php

namespace App\Services\Order;

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\MatchService;
use Illuminate\Validation\ValidationException;

/**
 * 운행 상태 전이 — 라이프사이클 규칙 검증, 알림, XP, 기사 상태 연동, 자동 매칭을 담당한다.
 */
class OrderTransitionService
{
    public function __construct(
        private readonly MatchService $matchService,
        private readonly OrderClaimService $claimService,
        private readonly OrderOfferService $offerService,
    ) {}

    /**
     * 라이프사이클 규칙에 따라 운행 상태를 전환한다.
     */
    public function transition(User $actor, Order $order, string $status, ?string $cancelReason = null, ?int $actualRevenue = null): void
    {
        // 수락 대기(가져오기 요청) 상태의 승인/복귀는 claim 서비스가 전담한다 —
        // claimant 정리·알림·채팅 카드 확정까지 함께 처리되어야 하므로 이 경로로 위임한다.
        if ($order->status === Order::STATUS_ACCEPTANCE_PENDING
            && in_array($status, [Order::STATUS_ACCEPTED, Order::STATUS_PUBLISHED], true)) {
            if ($status === Order::STATUS_ACCEPTED) {
                $this->claimService->approve($actor, $order);
            } elseif ($order->claimant_user_id === $actor->id) {
                $this->claimService->withdraw($order);
            } else {
                $this->claimService->reject($actor, $order);
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

        $order->transitionTo($status);

        // 운행이 확정(수락)되면 마켓에서 벗어나므로 남은 요금 제안을 정리한다
        if ($order->status === Order::STATUS_ACCEPTED) {
            $this->offerService->cancelPendingFor($order);
        }

        // 운행 시간·실제 수익 기록 — 운행 시작 시각, 완료 시각(+실제 수익)을 남긴다
        if ($status === Order::STATUS_DRIVING) {
            $order->forceFill(['started_at' => now()])->save();
        } elseif ($status === Order::STATUS_COMPLETED) {
            $order->forceFill([
                'completed_at' => now(),
                'actual_revenue' => $actualRevenue ?? $order->actual_revenue,
            ])->save();
        }

        // 취소 사유 기록
        if ($status === Order::STATUS_CANCELLED && filled($cancelReason)) {
            $order->forceFill(['cancel_reason' => $cancelReason])->save();
        }

        $owner = User::query()->find($order->user_id);

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
    }

    /**
     * 완료된 운행을 선택해 일괄 정산 처리한다.
     * 정산은 운행 등록자(원 등록자)만 할 수 있다 — 진행자는 '정산 진행중'으로 대기한다.
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
            $order->transitionTo(Order::STATUS_SETTLED);
        }

        return $orders->count();
    }
}
