<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'data' => $notifications->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '알림',
                'message' => $notification->data['message'] ?? '',
                'order_id' => $notification->data['order_id'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->diffForHumans(),
            ]),
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
