<?php

namespace App\Services\Order;

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Review;
use App\Models\User;
use App\Notifications\OrderNotification;

/**
 * 요금 제안(오퍼) 라이프사이클 — 기사가 운임을 제안하면 등록자가 비교 후 수락/거절한다.
 * 수락 전에는 제안 기사의 연락처(전화/이메일)를 노출하지 않아 연락처를 보호한다.
 */
class OrderOfferService
{
    /**
     * 기사가 공개 운행에 운임을 제안한다.
     */
    public function propose(User $driver, Order $order, int $amount, ?string $message): OrderOffer
    {
        abort_unless(in_array($order->status, [Order::STATUS_PUBLISHED, Order::STATUS_TRADING], true), 403, '현재 상태에서는 제안할 수 없습니다.');

        abort_unless($order->claimant_user_id === null, 403, '이미 가져오기 요청이 걸린 운행입니다.');

        abort_unless($order->user_id !== $driver->id, 403, '본인 운행에는 제안할 수 없습니다.');

        $duplicate = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->exists();

        abort_unless(! $duplicate, 422, '이미 이 운행에 제안을 보냈습니다.');

        $offer = OrderOffer::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'amount' => $amount,
            'message' => $message,
            'status' => OrderOffer::STATUS_PENDING,
        ]);

        $registrant = User::query()->find($order->user_id);

        if ($registrant !== null) {
            $registrant->notify(new OrderNotification(
                '요금 제안 도착',
                "드라이버가 {$order->rideSummary()} 운행에 {$amount}원을 제안했습니다. 상세에서 확인하고 수락할 수 있습니다.",
                $order->id,
                $offer->id,
                $amount,
            ));
        }

        return $offer;
    }

    /**
     * 운행의 제안 목록 — 등록자(모든 제안) 또는 본인 제안만 조회한다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listFor(User $user, Order $order): array
    {
        $query = OrderOffer::query()
            ->where('order_id', $order->id)
            ->with('driver')
            ->orderByDesc('created_at');

        // 등록자가 아니면 본인이 낸 제안만 보여준다 (다른 기사의 제안/연락처 보호)
        if ($order->user_id !== $user->id) {
            $query->where('driver_id', $user->id);
        }

        return $query->get()
            ->map(fn (OrderOffer $offer) => $this->payload($offer))
            ->values()
            ->all();
    }

    /**
     * 운행 id 목록의 대기 제안 건수 집계 — 내 운행 목록 카드 배지용.
     *
     * @param  iterable<int>  $orderIds
     * @return array<int, int> [order_id => 대기 제안 수]
     */
    public function pendingCountsByOrder(iterable $orderIds): array
    {
        $orderIds = collect($orderIds)->filter()->unique()->values();

        if ($orderIds->isEmpty()) {
            return [];
        }

        return OrderOffer::query()
            ->whereIn('order_id', $orderIds)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->selectRaw('order_id, COUNT(*) as cnt')
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();
    }

    /**
     * 등록자의 제안 받은 편지함 — 대기 제안이 있는 내 공개 운행 목록을 제안과 함께 돌려준다.
     * 제안은 금액 내림차순으로 정렬해 등록자가 비교하기 쉽게 한다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inboxFor(User $user): array
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING])
            ->whereNull('claimed_at')
            ->whereHas('offers', fn ($query) => $query->where('status', OrderOffer::STATUS_PENDING))
            ->with(['offers' => fn ($query) => $query
                ->where('status', OrderOffer::STATUS_PENDING)
                ->with('driver')
                ->orderByDesc('amount'),
            ])
            ->orderByDesc('created_at')
            ->get();

        return $orders
            ->map(function (Order $order) {
                $offers = $order->offers
                    ->map(fn (OrderOffer $offer) => $this->payload($offer))
                    ->values()
                    ->all();

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?: '#'.$order->id,
                    'route' => trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: '')),
                    'service_date' => $order->service_date,
                    'service_time' => $order->service_time,
                    'expected_revenue' => (int) ($order->expected_revenue ?? $order->amount_value ?? 0),
                    'status' => $order->status,
                    'status_label' => Order::statusOptions()[$order->status] ?? $order->status,
                    'pending_count' => count($offers),
                    'offers' => $offers,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 등록자가 제안을 수락한다 — 제안한 기사에게 운행이 넘어간다.
     */
    public function accept(User $registrant, Order $order, OrderOffer $offer): void
    {
        abort_unless($order->user_id === $registrant->id, 403, '운행 등록자만 수락할 수 있습니다.');

        abort_unless($offer->order_id === $order->id && $offer->status === OrderOffer::STATUS_PENDING, 403, '수락할 수 없는 제안입니다.');

        abort_unless(in_array($order->status, [Order::STATUS_PUBLISHED, Order::STATUS_TRADING], true), 403, '현재 상태에서는 수락할 수 없습니다.');

        // 첫 제안 수락이면 원 등록자를 기록 (상호 리뷰 대상 식별)
        if ($order->original_owner_id === null) {
            $order->forceFill(['original_owner_id' => $order->user_id]);
        }

        // 딜 성사 — 수락된 제안 금액이 운행의 계약 금액이 된다.
        // 등록자가 6만에 등록했어도 기사가 6만5천에 제안하고 수락하면 계약 금액은 6만5천이다.
        $order->forceFill([
            'user_id' => $offer->driver_id,
            'claimant_user_id' => null,
            'claimed_at' => now(),
            'expected_revenue' => $offer->amount,
            'amount_value' => $offer->amount,
            'status' => Order::STATUS_ACCEPTED,
        ])->save();

        $offer->forceFill(['status' => OrderOffer::STATUS_ACCEPTED])->save();

        // 다른 대기 제안은 모두 자동 거절 — 이미 확정된 운행에 이중 수락 방지
        OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereKeyNot($offer->id)
            ->update(['status' => OrderOffer::STATUS_REJECTED]);

        $driver = User::query()->find($offer->driver_id);

        if ($driver !== null) {
            // 기사 상태 자동 연동 — 수락된 순간부터 '운행 중'
            $driver->driver()->updateOrCreate(
                ['user_id' => $driver->id],
                ['status' => Driver::STATUS_ON_TRIP, 'status_updated_at' => now()],
            );

            $driver->addXp(20, 'order_claimed', '운행 가져오기 (수락)');

            $driver->notify(new OrderNotification(
                '요금 제안 수락됨',
                "{$order->rideSummary()} 운행의 요금 제안이 수락되어 ".number_format($offer->amount).'원에 딜이 성사되었습니다. 운행을 진행할 수 있습니다.',
                $order->id,
            ));
        }
    }

    /**
     * 등록자가 제안을 거절한다 — 운행은 마켓에 남는다.
     */
    public function reject(User $registrant, Order $order, OrderOffer $offer): void
    {
        abort_unless($order->user_id === $registrant->id, 403, '운행 등록자만 거절할 수 있습니다.');

        abort_unless($offer->order_id === $order->id && $offer->status === OrderOffer::STATUS_PENDING, 403, '거절할 수 없는 제안입니다.');

        $offer->forceFill(['status' => OrderOffer::STATUS_REJECTED])->save();

        $driver = User::query()->find($offer->driver_id);

        if ($driver !== null) {
            $driver->notify(new OrderNotification(
                '요금 제안 거절됨',
                "{$order->rideSummary()} 운행의 요금 제안이 거절되었습니다.",
                $order->id,
            ));
        }
    }

    /**
     * 제안한 기사가 본인 대기 제안을 철회한다.
     */
    public function withdraw(User $driver, Order $order, OrderOffer $offer): void
    {
        abort_unless($offer->driver_id === $driver->id, 403, '본인 제안만 철회할 수 있습니다.');

        abort_unless($offer->order_id === $order->id && $offer->status === OrderOffer::STATUS_PENDING, 403, '철회할 수 없는 제안입니다.');

        $offer->forceFill(['status' => OrderOffer::STATUS_CANCELLED])->save();

        $registrant = User::query()->find($order->user_id);

        if ($registrant !== null) {
            $registrant->notify(new OrderNotification(
                '요금 제안 철회됨',
                "{$order->rideSummary()} 운행의 요금 제안이 철회되었습니다.",
                $order->id,
            ));
        }
    }

    /**
     * 운행이 마켓에서 벗어나면(가져오기 요청·수락 등) 남아 있는 대기 제안을 정리한다.
     */
    public function cancelPendingFor(Order $order): void
    {
        OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->update(['status' => OrderOffer::STATUS_CANCELLED]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(OrderOffer $offer): array
    {
        $driver = $offer->driver;
        $rating = Review::query()
            ->where('reviewee_id', $offer->driver_id)
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')
            ->groupBy('reviewee_id')
            ->first();

        return [
            'id' => $offer->id,
            'order_id' => $offer->order_id,
            // 수락 전 연락처 보호 — 이름과 평판만 노출, 전화/이메일은 비공개
            'driver' => [
                'id' => $offer->driver_id,
                'name' => $driver?->name ?? '',
                'rating' => $rating ? round((float) $rating->avg, 1) : 0,
                'review_count' => (int) ($rating->cnt ?? 0),
            ],
            'amount' => $offer->amount,
            'message' => $offer->message,
            'status' => $offer->status,
            'status_label' => OrderOffer::statusOptions()[$offer->status] ?? $offer->status,
            'created_at' => $offer->created_at?->toIso8601String(),
        ];
    }
}
