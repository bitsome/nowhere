<?php

namespace App\Services\Auth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 로그인 시도 제한(브루트포스 방어) — 계정 식별자 단위로
 * 연속 실패 횟수를 세고 일정 횟수를 넘으면 잠시 잠근다.
 */
class LoginThrottleService
{
    /** 잠금을 유발하는 연속 실패 횟수 */
    public const MAX_ATTEMPTS = 5;

    /** 잠금 시간(분) */
    public const LOCK_MINUTES = 15;

    /**
     * 계정 식별자를 잠금 키로 정규화한다 (이메일은 소문자, 전화번호는 부호 제거).
     */
    public function keyFor(string $identifier): string
    {
        $trimmed = trim($identifier);

        if (str_contains($trimmed, '@')) {
            return mb_strtolower($trimmed);
        }

        return preg_replace('/[^0-9]/', '', $trimmed) ?: $trimmed;
    }

    /**
     * 계정이 잠겨 있으면 예외를 던진다 (로그인 진입 시 먼저 검사).
     *
     * @throws ValidationException
     */
    public function assertNotLocked(string $key): void
    {
        $row = DB::table('login_attempts')->where('key', $key)->first();

        if ($row === null || $row->locked_until === null) {
            return;
        }

        $lockedUntil = Carbon::parse($row->locked_until);

        if (! $lockedUntil->isFuture()) {
            return;
        }

        $minutes = max(1, (int) ceil($lockedUntil->diffInMinutes(now())));

        throw ValidationException::withMessages([
            'login' => ["로그인 시도가 너무 많아 잠겼습니다. {$minutes}분 후 다시 시도해 주세요."],
        ]);
    }

    /**
     * 로그인 실패를 기록하고, 연속 실패가 한계를 넘으면 계정을 잠근다.
     *
     * @return int 남은 시도 횟수 (0이면 방금 잠김)
     */
    public function recordFailure(string $key): int
    {
        $now = now();

        $attempts = DB::table('login_attempts')
            ->where('key', $key)
            ->value('attempts') ?? 0;

        $attempts++;

        $lockedUntil = null;
        $remaining = self::MAX_ATTEMPTS - $attempts;

        if ($remaining <= 0) {
            $remaining = 0;
            $lockedUntil = $now->copy()->addMinutes(self::LOCK_MINUTES);
        }

        DB::table('login_attempts')->updateOrInsert(
            ['key' => $key],
            [
                'attempts' => $attempts,
                'locked_until' => $lockedUntil,
                'updated_at' => $now,
            ],
        );

        return $remaining;
    }

    /**
     * 로그인 성공 시 시도 기록을 비운다 (정상 사용자도 같이 리셋 방지 겸용).
     */
    public function clear(string $key): void
    {
        DB::table('login_attempts')->where('key', $key)->delete();
    }
}
