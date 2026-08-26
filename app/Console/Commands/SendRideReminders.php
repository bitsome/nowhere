<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\OrderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rides:remind')]
#[Description('서비스 1시간 전인 확정 운행의 기사에게 출발 준비 알림을 보낸다.')]
class SendRideReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now('Asia/Seoul');

        // 서비스 시작이 1시간 전 ~ 3분 전 구간인 운행 (스케줄 지연에도 놓치지 않도록 여유)
        $from = $now->copy()->addHour()->subMinutes(3);
        $to = $now->copy()->addHour();
        [$fromDate, $fromTime] = explode(' ', $from->format('Y-m-d H:i'));
        [$toDate, $toTime] = explode(' ', $to->format('Y-m-d H:i'));

        $orders = Order::query()
            ->where('status', Order::STATUS_ACCEPTED)
            ->whereNotNull('service_date')
            ->where('service_date', '!=', '')
            ->whereNotNull('service_time')
            ->where('service_time', '!=', '')
            // 날짜+시간 문자열 비교 대신 날짜·시간을 분리 비교 (SQLite/MySQL 공용)
            ->where(function ($query) use ($fromDate, $fromTime) {
                $query->where('service_date', '>', $fromDate)
                    ->orWhere(function ($q) use ($fromDate, $fromTime) {
                        $q->where('service_date', $fromDate)
                            ->where('service_time', '>=', $fromTime);
                    });
            })
            ->where(function ($query) use ($toDate, $toTime) {
                $query->where('service_date', '<', $toDate)
                    ->orWhere(function ($q) use ($toDate, $toTime) {
                        $q->where('service_date', $toDate)
                            ->where('service_time', '<', $toTime);
                    });
            })
            ->limit(50)
            ->get();

        $sent = 0;
        $title = '출발 준비 알림';

        foreach ($orders as $order) {
            $owner = $order->user;

            if ($owner === null) {
                continue;
            }

            // 같은 운행의 리마인더가 이미 있으면 중복 발송하지 않는다
            $already = $owner->notifications()
                ->where('type', OrderNotification::class)
                ->where('data->order_id', $order->id)
                ->where('data->title', $title)
                ->exists();

            if ($already) {
                continue;
            }

            $owner->notify(new OrderNotification(
                title: $title,
                message: "{$order->rideSummary()} 운행이 1시간 후 시작됩니다. 출발을 준비해 주세요.",
                orderId: $order->id,
            ));

            $sent++;
        }

        $this->info("운행 전 리마인더를 {$sent}건 보냈습니다.");

        return self::SUCCESS;
    }
}
