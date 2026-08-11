<?php

namespace App\Support\Orders;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 오더 목록 행(row)의 공용 데이터 계약을 만든다.
 *
 * Blade, Vue, API 응답이 이 구조를 그대로 사용하며,
 * 화면별로 row를 다시 조립하지 않는다.
 */
class OrderListRowBuilder
{
    /**
     * @var array<string, string>
     */
    private array $serviceLabels;

    /**
     * @var array<string, string>
     */
    private array $statusOptions;

    /**
     * @param  array<string, string>|null  $statusOptions
     */
    public function __construct(?array $statusOptions = null)
    {
        $this->serviceLabels = [
            'pickup' => '픽업',
            'sending' => '공항샌딩',
            'landing' => '공항랜딩',
        ];

        $this->statusOptions = $statusOptions ?? Order::statusOptions();
    }

    /**
     * 단일 오더 행 계약을 만든다.
     *
     * @return array<string, mixed>
     */
    public function build(Order $order): array
    {
        return [
            'key' => 'order-'.$order->id,
            'kind' => 'single',
            'id' => $order->id,
            'orderNumber' => $order->order_number ?: '#'.$order->id,
            'customerName' => $order->customer_name ?: '',
            'serviceIcon' => $this->serviceIcon($order),
            'serviceLabel' => $this->serviceLabel($order),
            'vehicle' => $order->vehicle_type ?: '-',
            'flightNumber' => $order->flight_number ?: '',
            'passengerCount' => $order->passenger_count ?: 0,
            'date' => $this->formatDate($order),
            'time' => $this->formatTime($order),
            'pickupDateTime' => $this->formatPickupDateTime($order),
            'route' => ($order->pickup_location ?: '-').' → '.($order->dropoff_location ?: '-'),
            'amount' => $this->formatAmount($order),
            'status' => $order->status,
            'statusLabel' => $this->statusOptions[$order->status] ?? $order->status,
            'isToday' => $this->isToday($order),
            'isTomorrow' => $this->isTomorrow($order),
            'isNew' => $this->isNew($order),
            'showUrl' => route('dashboard.business.order.show', $order),
            'sortDate' => $order->service_date ?: '',
            'sortTime' => $order->service_time ?: '',
        ];
    }

    /**
     * 여러 오더를 행 계약 목록으로 만든다.
     *
     * @param  Collection<int, Order>|array<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function buildMany(Collection|array $orders): array
    {
        $rows = [];

        foreach ($orders as $order) {
            $rows[] = $this->build($order);
        }

        return $rows;
    }

    public function serviceLabel(Order $order): string
    {
        $type = $order->service_type;

        if ($type === null || $type === '') {
            return '-';
        }

        return $this->serviceLabels[$type] ?? '-';
    }

    public function serviceIcon(Order $order): string
    {
        return match ($order->service_type) {
            'pickup' => 'pickup',
            'sending' => 'sending',
            'landing' => 'landing',
            default => '',
        };
    }

    public function formatDate(Order $order): ?string
    {
        if (! $order->service_date) {
            return null;
        }

        $date = Carbon::parse($order->service_date);
        $weekdays = ['일', '월', '화', '수', '목', '금', '토'];

        return $date->format('n/j').'('.$weekdays[$date->dayOfWeek].')';
    }

    public function formatTime(Order $order): string
    {
        return $order->service_time ?: '';
    }

    public function formatPickupDateTime(Order $order): string
    {
        $date = $this->formatDate($order);
        $time = $this->formatTime($order);

        return trim(($date ?? '').' '.$time);
    }

    public function formatAmount(Order $order): string
    {
        $amount = $order->expected_revenue ?? $order->amount_value;

        return $amount ? number_format((int) $amount).'원' : '-';
    }

    /**
     * 오늘 운행 여부 (카드 리스트에서 "오늘" 라벨로 사용).
     */
    private function isToday(Order $order): bool
    {
        if (! $order->service_date) {
            return false;
        }

        return Carbon::parse($order->service_date)->isToday();
    }

    /**
     * 내일 운행 여부 (카드 리스트에서 "내일" 라벨로 사용).
     */
    private function isTomorrow(Order $order): bool
    {
        if (! $order->service_date) {
            return false;
        }

        return Carbon::parse($order->service_date)->isTomorrow();
    }

    /**
     * 등록 후 1시간 이내 새 오더 여부 (목록에서 N 배지로 표시).
     */
    private function isNew(Order $order): bool
    {
        return $order->created_at !== null && $order->created_at->gte(now()->subHours(2));
    }
}
