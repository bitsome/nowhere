<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 차량·면허 인증 플로우.
 * - 사용자: 인증 신청 (관리자에게 알림)
 * - 관리자(Admin/Super Admin): 인증 상태 승인/변경
 */
class VerificationController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'vehicle' => ['sometimes', 'boolean'],
            'license' => ['sometimes', 'boolean'],
        ]);

        $requests = [];

        if (($data['vehicle'] ?? false) && ! $user->is_vehicle_verified) {
            $requests[] = '차량';
        }

        if (($data['license'] ?? false) && ! $user->is_license_verified) {
            $requests[] = '면허';
        }

        if ($requests === []) {
            throw ValidationException::withMessages([
                'vehicle' => ['신청할 인증 항목이 없습니다. (이미 인증 완료)'],
            ]);
        }

        // 관리자(Admin/Super Admin)에게 알림
        $admins = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new OrderNotification(
                '인증 신청',
                $user->name.'님이 '.implode('·', $requests).' 인증을 신청했습니다.',
            ));
        }

        return response()->json([
            'data' => ['requested' => $requests],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            abort(403, '관리자만 인증 상태를 변경할 수 있습니다.');
        }

        $data = $request->validate([
            'vehicle' => ['sometimes', 'boolean'],
            'license' => ['sometimes', 'boolean'],
        ]);

        $user->forceFill([
            'is_vehicle_verified' => $data['vehicle'] ?? $user->is_vehicle_verified,
            'is_license_verified' => $data['license'] ?? $user->is_license_verified,
        ])->save();

        $user->notify(new OrderNotification(
            '인증 처리 완료',
            '차량·면허 인증 상태가 업데이트되었습니다.',
        ));

        return response()->json([
            'data' => [
                'id' => $user->id,
                'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
                'is_license_verified' => (bool) $user->is_license_verified,
            ],
        ]);
    }
}
