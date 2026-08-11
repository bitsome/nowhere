<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StatsController extends Controller
{
    /**
     * 오더 운영 통계 — 기간별 건수·매출·상태 분포·정산 현황.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function orders(Request $request): JsonResponse
    {
        $days = min(max((int) $request->integer('days', 7), 1), 90);

        $from = Carbon::today()->subDays($days - 1);
        $to = Carbon::today()->endOfDay();

        $orders = Order::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->get();

        // 일별 시리즈 (날짜 → 건수/매출)
        $series = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $dayKey = $cursor->format('Y-m-d');
            $dayOrders = $orders->filter(fn (Order $order) => $order->created_at?->format('Y-m-d') === $dayKey);

            $series[] = [
                'date' => $cursor->format('n/j'),
                'count' => $dayOrders->count(),
                'revenue' => $this->revenueOf($dayOrders),
            ];

            $cursor->addDay();
        }

        // 상태 분포
        $statusDistribution = Order::statusOptions();
        $distribution = collect($statusDistribution)
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'label' => $label,
                'count' => $orders->where('status', $status)->count(),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();

        // 정산 현황
        $completedRevenue = $orders
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->sum(fn (Order $order) => (int) ($order->expected_revenue ?? $order->amount_value ?? 0));

        $settledRevenue = $orders
            ->where('status', Order::STATUS_SETTLED)
            ->sum(fn (Order $order) => (int) ($order->expected_revenue ?? $order->amount_value ?? 0));

        // 오늘/내일 운행 건수 (서비스일 기준, 취소 제외)
        $activeStatuses = array_values(array_diff(
            array_keys(Order::statusOptions()),
            [Order::STATUS_CANCELLED, Order::STATUS_DRAFT],
        ));

        $upcomingQuery = Order::query()
            ->with('user:id,name')
            ->whereIn('status', $activeStatuses)
            ->where('service_date', '!=', '')
            ->whereNotNull('service_date');

        $todayOrders = (clone $upcomingQuery)->whereDate('service_date', Carbon::today())->get();
        $tomorrowOrders = (clone $upcomingQuery)->whereDate('service_date', Carbon::tomorrow())->get();

        $upcoming = [
            'today' => $todayOrders->count(),
            'tomorrow' => $tomorrowOrders->count(),
            'todayList' => $this->scheduleList($todayOrders),
            'tomorrowList' => $this->scheduleList($tomorrowOrders),
        ];

        // 월별 시리즈 (최근 6개월, 생성일 기준)
        $monthFrom = Carbon::today()->startOfMonth()->subMonths(5);
        $monthOrders = Order::query()
            ->where('created_at', '>=', $monthFrom)
            ->where('created_at', '<=', $to)
            ->get();

        $monthly = [];
        $monthCursor = $monthFrom->copy();

        for ($i = 0; $i < 6; $i++) {
            $monthKey = $monthCursor->format('Y-m');
            $monthOrdersList = $monthOrders->filter(fn (Order $order) => $order->created_at?->format('Y-m') === $monthKey);

            $monthly[] = [
                'month' => $monthCursor->format('n월'),
                'count' => $monthOrdersList->count(),
                'revenue' => $this->revenueOf($monthOrdersList),
            ];

            $monthCursor->addMonth();
        }

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from->format('n/j'),
                    'to' => $to->format('n/j'),
                    'days' => $days,
                ],
                'upcoming' => $upcoming,
                'summary' => [
                    'total' => $orders->count(),
                    'revenue' => $this->revenueOf($orders),
                    'completed' => $orders->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])->count(),
                    'settled' => $settledRevenue,
                    'settlementPending' => $completedRevenue - $settledRevenue,
                    'inProgress' => $orders
                        ->whereNotIn('status', [
                            Order::STATUS_COMPLETED,
                            Order::STATUS_SETTLED,
                            Order::STATUS_CANCELLED,
                        ])
                        ->count(),
                ],
                'daily' => $series,
                'monthly' => $monthly,
                'statusDistribution' => $distribution,
            ],
        ]);
    }

    /**
     * 매출 합계 (취소 제외).
     *
     * @param  Collection<int, Order>  $orders
     */
    private function revenueOf($orders): int
    {
        return $orders
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->sum(fn (Order $order) => (int) ($order->expected_revenue ?? $order->amount_value ?? 0));
    }

    /**
     * 오늘/내일 운행 오더 미니리스트 — 시간순 정렬, 최대 8건.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function scheduleList(Collection $orders): array
    {
        $labels = [
            'pickup' => '픽업',
            'sending' => '공항샌딩',
            'landing' => '공항랜딩',
        ];

        return $orders
            ->sortBy(fn (Order $order) => ($order->service_date ?? '').' '.($order->service_time ?? ''))
            ->take(8)
            ->values()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'time' => $order->service_time ?: '-',
                'route' => ($order->pickup_location ?: '-').' → '.($order->dropoff_location ?: '-'),
                'serviceLabel' => $labels[$order->service_type] ?? $order->service_type ?? '-',
                'passengerCount' => $order->passenger_count ?: 0,
                'statusLabel' => Order::statusOptions()[$order->status] ?? $order->status,
                'customerName' => $order->customer_name ?: ($order->user?->name ?: ''),
            ])
            ->all();
    }
}
