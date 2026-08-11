<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    /**
     * 회원가입 — 새 사용자 등록 후 바로 토큰을 발급한다.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.unique' => '이미 등록된 이메일입니다.',
            'password.min' => '비밀번호는 8자 이상이어야 합니다.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'driver', // 신규 가입자는 드라이버로 시작
        ]);

        return response()->json([
            'data' => [
                'token' => $user->createToken('frontend')->plainTextToken,
                'user' => $this->userPayload($user),
            ],
        ], 201);
    }
    /**
     * 이메일·비밀번호로 로그인하고 Sanctum 토큰을 발급한다.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['아이디 또는 비밀번호가 올바르지 않습니다.'],
            ]);
        }

        return response()->json([
            'data' => [
                'token' => $user->createToken('frontend')->plainTextToken,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /**
     * 현재 사용자의 토큰을 폐기한다.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['data' => null]);
    }

    /**
     * 현재 사용자 정보와 권한을 반환한다.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * 프로필(이름·연락처)을 수정한다.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'permissions' => $user->resolvedPermissions(),
            'xp' => (int) $user->xp,
            'level' => $user->levelInfo(),
            'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
            'is_license_verified' => (bool) $user->is_license_verified,
            'is_vip' => (bool) $user->is_vip,
            'vehicle_info' => $user->vehicle_info,
            'recent_xp_events' => $user->levelEvents()
                ->latest()
                ->limit(10)
                ->get(['type', 'label', 'xp', 'created_at']),
        ];
    }
}
