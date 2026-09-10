<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFavorite;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * 운행 즐겨찾기(찜) — 기사가 마음에 두는 운행을 보관한다.
 *
 * 운행이 마켓에서 빠지면(다른 기사가 가져감·취소·숨김) 찜한 기사에게 알리고
 * 찜 기록을 정리한다. 운행 내용이 바뀌면(조건 변경) 알리고 찜은 유지한다.
 */
class OrderFavoriteService
{
    /**
     * 마켓에서 아직 가져올 수 있는 운행인지 — 찜 보관·알림 발송 기준.
     */
    public function isMarketable(Order $order): bool
    {
        return in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
        ], true)
            && ! (bool) $order->is_hidden
            && ! (bool) $order->admin_hold;
    }

    /**
     * 찜 상태를 뒤집는다 — 이미 찜했으면 해제, 아니면 추가.
     *
     * @return array{favorited: bool}
     */
    public function toggle(User $user, Order $order): array
    {
        abort_unless($this->isMarketable($order), 409, '마켓에 없는 운행은 찜할 수 없습니다.');

        $existing = OrderFavorite::query()
            ->where('user_id', $user->id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ['favorited' => false];
        }

        OrderFavorite::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
        ]);

        return ['favorited' => true];
    }

    /**
     * 이 운행의 찜 여부 — 상세 화면 하트 초기값에 사용한다.
     */
    public function isFavorited(User $user, Order $order): bool
    {
        return OrderFavorite::query()
            ->where('user_id', $user->id)
            ->where('order_id', $order->id)
            ->exists();
    }

    /**
     * 내가 찜해 둔 운행이 몇 건인지 — 마켓 '찜' 필터·빠른 진입 배지에 사용한다.
     */
    public function countForUser(User $user): int
    {
        return OrderFavorite::query()
            ->where('user_id', $user->id)
            ->whereHas('order', fn (Builder $q) => $q
                ->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING, Order::STATUS_ACCEPTANCE_PENDING])
                ->where('is_hidden', false)
                ->where('admin_hold', false))
            ->count();
    }

    /**
     * 운행이 마켓에서 빠졌을 때(배차·취소·숨김) 찜한 기사에게 알리고 찜 기록을 정리한다.
     *
     * @param  int|null  $exceptUserId  알림을 보내지 않을 사용자 (예: 새 수행자 본인)
     */
    public function notifyUnavailable(Order $order, string $title, string $message, ?int $exceptUserId = null): void
    {
        $favorites = OrderFavorite::query()
            ->where('order_id', $order->id)
            ->when($exceptUserId !== null, fn (Builder $q) => $q->where('user_id', '!=', $exceptUserId))
            ->with('user:id,name')
            ->get();

        foreach ($favorites as $favorite) {
            if ($favorite->user !== null) {
                $favorite->user->notify(new OrderNotification($title, $message, $order->id));
            }

            $favorite->delete();
        }
    }

    /**
     * 찜한 운행의 조건(일정·금액·노선 등)이 바뀌었을 때 알린다 — 찜은 유지한다.
     */
    public function notifyChanged(Order $order): void
    {
        OrderFavorite::query()
            ->where('order_id', $order->id)
            ->with('user:id,name')
            ->get()
            ->each(function (OrderFavorite $favorite) use ($order) {
                $favorite->user?->notify(new OrderNotification(
                    '찜한 운행 조건 변경',
                    "찜해 둔 {$order->rideSummary()} 운행의 일정·조건이 변경되었습니다. 다시 확인해 보세요.",
                    $order->id,
                ));
            });
    }
}
