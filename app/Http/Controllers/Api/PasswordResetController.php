<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\Auth\LoginThrottleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 비밀번호 찾기/재설정 — 이메일로 6자리 인증코드를 보내고, 코드 확인 후 새 비밀번호를 설정한다.
 *
 * - 인증코드는 `password_reset_tokens`에 해시로 저장되고 config 유효시간(auth.passwords.users.expire) 후 만료된다.
 * - 계정 존재 여부는 노출하지 않는다(가입 여부 스캔 방지).
 * - 완료 시 로그인 실패 기록을 비우고 기존 토큰을 폐기해 재로그인을 유도한다.
 */
class PasswordResetController extends Controller
{
    /**
     * 인증코드 재발송 최소 간격(초) — 메일 폭탄/스팸 방지.
     */
    public const RESEND_INTERVAL_SECONDS = 60;

    /**
     * 비밀번호 찾기 — 인증코드를 이메일로 보낸다.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $message = '인증코드가 담긴 이메일을 보냈습니다. 메일함을 확인해 주세요.';

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            // 가입 여부 노출 방지 — 미가입 이메일에도 같은 안내만 반환한다
            return response()->json(['message' => $message]);
        }

        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($existing !== null
            && Carbon::parse($existing->created_at)->gt(now()->subSeconds(self::RESEND_INTERVAL_SECONDS))) {
            abort(429, '인증코드를 방금 보냈습니다. 1분 후 다시 요청해 주세요.');
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        $user->notify(new PasswordResetCodeNotification($code));

        return response()->json(['message' => $message]);
    }

    /**
     * 비밀번호 재설정 — 인증코드 확인 후 새 비밀번호로 변경한다.
     */
    public function reset(Request $request, LoginThrottleService $throttle): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'code.digits' => '인증코드는 6자리 숫자입니다.',
            'password.min' => '비밀번호는 8자 이상이어야 합니다.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
        ]);

        $email = mb_strtolower(trim($data['email']));

        if (! $this->codeIsValid($email, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['인증코드가 올바르지 않거나 만료되었습니다.'],
            ]);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => ['인증코드가 올바르지 않거나 만료되었습니다.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // 로그인 실패 기록 해제 + 기존 접속 토큰 폐기(전 기기 재로그인 유도)
        $throttle->clear($email);
        $user->tokens()->delete();

        return response()->json(['message' => '비밀번호가 변경되었습니다. 새 비밀번호로 로그인해 주세요.']);
    }

    /**
     * 저장된 인증코드가 맞고 유효 시간 안인지 검사한다.
     */
    private function codeIsValid(string $email, string $code): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($row === null || ! Hash::check($code, $row->token)) {
            return false;
        }

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return Carbon::parse($row->created_at)->addMinutes($expireMinutes)->isFuture();
    }
}
