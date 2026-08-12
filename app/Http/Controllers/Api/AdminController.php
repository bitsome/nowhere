<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * 운영 관리용 사용자 목록 (관리자/슈퍼 관리자 전용).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $users = User::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        // 완료/정산 운행 수 집계 (User에 orders 관계가 없어 직접 계산)
        $completedCounts = Order::query()
            ->whereIn('user_id', collect($users->items())->pluck('id'))
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
                'is_license_verified' => (bool) $user->is_license_verified,
                'created_at' => $user->created_at?->format('Y-m-d'),
                'completed_count' => (int) ($completedCounts[$user->id] ?? 0),
            ]),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    private function authorizeAdmin(User $user): void
    {
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true), 403, '관리자만 접근할 수 있습니다.');
    }
}
