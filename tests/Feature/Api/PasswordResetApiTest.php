<?php

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('oldpass123'),
    ]);
});

test('api password forgot sends a 6-digit code and stores a hashed token', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/password/forgot', [
        'email' => 'reset@example.com',
    ])->assertOk();

    expect($response->json('message'))->toContain('이메일을 보냈습니다');

    // 인증코드 토큰이 해시로 저장된다 (원본 코드가 그대로 남지 않음)
    $row = DB::table('password_reset_tokens')->where('email', 'reset@example.com')->first();
    expect($row)->not->toBeNull();
    expect($row->token)->toStartWith('$2y$');

    Notification::assertSentTo($this->user, PasswordResetCodeNotification::class, function ($notification) {
        return preg_match('/^\d{6}$/', $notification->code) === 1;
    });
});

test('api password forgot hides whether the email is registered', function () {
    $this->postJson('/api/auth/password/forgot', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('message', fn ($message) => str_contains($message, '이메일을 보냈습니다'));

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('api password forgot throttles resend requests within a minute', function () {
    $this->postJson('/api/auth/password/forgot', ['email' => 'reset@example.com'])->assertOk();

    $this->postJson('/api/auth/password/forgot', ['email' => 'reset@example.com'])
        ->assertStatus(429);
});

test('api password reset changes the password with a valid code', function () {
    DB::table('password_reset_tokens')->insert([
        'email' => 'reset@example.com',
        'token' => Hash::make('123456'),
        'created_at' => now(),
    ]);

    // 기존 접속 토큰 — 재설정 후 폐기 확인용
    $this->user->createToken('old-device');

    $this->postJson('/api/auth/password/reset', [
        'email' => 'reset@example.com',
        'code' => '123456',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertOk();

    expect(Hash::check('newpass123', $this->user->fresh()?->password))->toBeTrue();
    expect(Hash::check('oldpass123', $this->user->fresh()?->password))->toBeFalse();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
    expect($this->user->fresh()?->tokens()->count())->toBe(0);
});

test('api password reset rejects a wrong code', function () {
    DB::table('password_reset_tokens')->insert([
        'email' => 'reset@example.com',
        'token' => Hash::make('123456'),
        'created_at' => now(),
    ]);

    $this->postJson('/api/auth/password/reset', [
        'email' => 'reset@example.com',
        'code' => '999999',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertStatus(422)->assertJsonValidationErrors(['code']);

    expect(Hash::check('oldpass123', $this->user->fresh()?->password))->toBeTrue();
});

test('api password reset rejects an expired code', function () {
    DB::table('password_reset_tokens')->insert([
        'email' => 'reset@example.com',
        'token' => Hash::make('123456'),
        'created_at' => now()->subMinutes((int) config('auth.passwords.users.expire', 60) + 5),
    ]);

    $this->postJson('/api/auth/password/reset', [
        'email' => 'reset@example.com',
        'code' => '123456',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertStatus(422)->assertJsonValidationErrors(['code']);
});

test('api password reset validates code and password format', function () {
    $this->postJson('/api/auth/password/reset', [
        'email' => 'reset@example.com',
        'code' => 'abc',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'password']);
});

test('api password reset clears login failure lock', function () {
    DB::table('password_reset_tokens')->insert([
        'email' => 'reset@example.com',
        'token' => Hash::make('123456'),
        'created_at' => now(),
    ]);

    // 잠긴 상태(연속 5회 실패)를 만들어 둔다
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/auth/password/reset', [
        'email' => 'reset@example.com',
        'code' => '123456',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertOk();

    // 재설정 후 잠금이 풀려 새 비밀번호로 바로 로그인할 수 있다
    $this->postJson('/api/auth/login', [
        'email' => 'reset@example.com',
        'password' => 'newpass123',
    ])->assertOk();
});
