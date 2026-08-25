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
    ) {}

    /**
     * 라이프사이클 규칙에 따라 운행 상태를 전환한다.
     */
    public function transition(User $actor, Order $order, string $status, ?string $cancelReason = null): void
    {
        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => ['전환할 수 없는 상태입니다.'],
            ]);
        }

        $order->transitionTo($status);

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
