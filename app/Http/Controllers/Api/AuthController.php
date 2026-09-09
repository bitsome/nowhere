<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginThrottleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 회원가입 — 새 사용자 등록 후 바로 토큰을 발급한다.
     * 가입 시 역할을 선택한다: 기사(Driver, 기본) 또는 운행 등록자(Customer).
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in([User::ROLE_DRIVER, User::ROLE_CUSTOMER])],
        ], [
            'email.unique' => '이미 등록된 이메일입니다.',
            'password.min' => '비밀번호는 8자 이상이어야 합니다.',
            'role.in' => '가입 역할이 올바르지 않습니다.',
        ]);

        $role = $data['role'] ?? User::ROLE_DRIVER;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role,
        ]);

        return response()->json([
            'data' => [
                'token' => $user->createToken('frontend')->plainTextToken,
                'user' => $this->userPayload($user),
            ],
        ], 201);
    }

    /**
     * 아이디(이메일)·전화번호 + 비밀번호로 로그인하고 Sanctum 토큰을 발급한다.
     * `login` 필드에 이메일 또는 전화번호(하이픈 유무 무관)를 넣으면 된다.
     */
    public function login(Request $request, LoginThrottleService $throttle): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required_without:email', 'string', 'max:255'],
            'email' => ['required_without:login', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($credentials['login'] ?? $credentials['email']));

        // 브루트포스 방어 — 잠긴 계정은 맞는 비밀번호여도 거부한다 (시도 기록도 남기지 않음)
        $lockKey = $throttle->keyFor($identifier);
        $throttle->assertNotLocked($lockKey);

        // 이메일 또는 전화번호로 사용자 검색 (전화번호는 하이픈·공백·점 무시하고 비교)
        // 같은 번호를 여러 계정이 쓰는 테스트 계정의 경우 관리자(Admin)를 우선 매칭한다
        $normalized = str_replace([' ', '-', '.'], '', $identifier);

        $user = User::query()
            ->where('email', $identifier)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '.', '') = ?", [$normalized])
            ->orderByRaw('CASE WHEN role IN (?, ?) THEN 0 ELSE 1 END', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $remaining = $throttle->recordFailure($lockKey);

            throw ValidationException::withMessages([
                'login' => [$remaining > 0
                    ? "아이디 또는 비밀번호가 올바르지 않습니다. (남은 시도 {$remaining}회)"
                    : '로그인 시도가 너무 많아 잠겼습니다. 잠시 후 다시 시도해 주세요.'],
            ]);
        }

        // 로그인 성공 — 실패 기록을 비워 정상 사용자의 잠금을 방지한다
        $throttle->clear($lockKey);

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
     * 프로필(이름·연락처·업체명)을 수정한다.
     * 업체명은 등록자(업체) 역할만 사용하고, 기사 등 다른 역할에서는 무시된다.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($user->role !== User::ROLE_CUSTOMER) {
            unset($data['company_name']);
        }

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
            'company_name' => $user->company_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'permissions' => $user->resolvedPermissions(),
            'xp' => (int) $user->xp,
            'level' => $user->levelInfo(),
            'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
            'is_license_verified' => (bool) $user->is_license_verified,
            'is_business_verified' => (bool) $user->is_business_verified,
            'is_account_verified' => (bool) $user->is_account_verified,
            'is_vip' => (bool) $user->is_vip,
            'vehicle_info' => $user->vehicle_info,
            'recent_xp_events' => $user->levelEvents()
                ->latest()
                ->limit(10)
                ->get(['type', 'label', 'xp', 'created_at']),
        ];
    }
}
