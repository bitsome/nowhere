<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 테스트 계정에 운행과 연계된 알림을 심는다 (픽업/샌딩/랜딩 운행 기반).
 */
class NotificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first();

        if ($user === null) {
            return;
        }

        DatabaseNotification::query()->where('notifiable_id', $user->id)->delete();

        $items = [];

        // 새 운행 도착 — 가장 최근 1건은 안 읽음, 나머지는 읽음
        $marketOrders = Order::query()
            ->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING])
            ->orderByDesc('service_date')
            ->limit(5)
            ->get();

        foreach ($marketOrders as $index => $order) {
            $items[] = $this->item(
                user: $user,
                title: '새 운행 도착',
                message: "{$order->pickup_location} → {$order->dropoff_location} 운행이 등록되었습니다.",
                orderId: $order->id,
                createdAt: now()->subHours($index),
                readAt: $index === 0 ? null : now()->subMinutes(30),
            );
        }

        // 운행 가져오기 완료 — 최근 1건은 안 읽음
        $claimedOrders = Order::query()
            ->whereNotNull('claimed_at')
            ->orderByDesc('claimed_at')
            ->limit(4)
            ->get();

        foreach ($claimedOrders as $index => $order) {
            $items[] = $this->item(
                user: $user,
                title: '운행 가져오기 완료',
                message: "{$order->customer_name}님의 운행({$order->order_number})를 내 운행으로 가져왔습니다.",
                orderId: $order->id,
                createdAt: now()->subHours(6 + $index * 2),
                readAt: $index === 0 ? null : now()->subDays(1),
            );
        }

        $items[] = $this->item(
            user: $user,
            title: '환영합니다',
            message: 'NoWhere 운행 마켓에 오신 것을 환영합니다.',
            orderId: null,
            createdAt: now()->subDays(3),
            readAt: now()->subDays(2),
        );

        DatabaseNotification::insert($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        User $user,
        string $title,
        string $message,
        ?int $orderId,
        Carbon $createdAt,
        ?Carbon $readAt = null,
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\OrderNotification',
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'data' => json_encode([
                'title' => $title,
                'message' => $message,
                'order_id' => $orderId,
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
