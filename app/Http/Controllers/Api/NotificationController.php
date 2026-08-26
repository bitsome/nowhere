<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 현재 사용자의 알림 목록을 반환한다 (최신순).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, unread_count: int, total: int}
     */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 20), 50));

        $notifications = $request->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        // 알림의 운행 카드용 정보 — 저장된 문구 대신 최신 경로·상태를 함께 내려준다.
        $orderIds = $notifications
            ->map(fn ($notification) => $notification->data['order_id'] ?? null)
            ->filter()
            ->unique();

        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->get(['id', 'pickup_location', 'dropoff_location', 'status'])
            ->keyBy('id');

        return response()->json([
            'data' => $notifications->map(function ($notification) use ($orders) {
                $order = $orders->get($notification->data['order_id'] ?? null);

                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? '알림',
                    'message' => $notification->data['message'] ?? '',
                    'order_id' => $notification->data['order_id'] ?? null,
                    'offer_id' => $notification->data['offer_id'] ?? null,
                    'offer_amount' => $notification->data['offer_amount'] ?? null,
                    'order_route' => $order !== null
                        ? trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: ''))
                        : '',
                    'order_status' => $order->status ?? '',
                    'order_status_label' => $order !== null
                        ? (Order::statusOptions()[$order->status] ?? $order->status)
                        : '',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->diffForHumans(),
                    'created_at_iso' => $notification->created_at?->toISOString(),
                ];
            }),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'total' => $request->user()->notifications()->count(),
        ]);
    }

    /**
     * 알림을 읽음 처리한다. all=true면 전체, 아니면 ids 목록만.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->boolean('all')) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        } else {
            $user->notifications()
                ->whereIn('id', $request->input('ids', []))
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'data' => [
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }
}
