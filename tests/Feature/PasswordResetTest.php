<?php

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('guests can view the forgot password screen', function () {
    $this->get(route('password.request'))
        ->assertSuccessful()
        ->assertSee('비밀번호 찾기');
});

test('users can request a password reset code', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), [
        'email' => $user->email,
    ])->assertSessionHas('status');

    Notification::assertSentTo($user, PasswordResetCodeNotification::class);
});

test('users can reset their password with a valid verification code', function () {
    Notification::fake();

    $user = User::factory()->create();
    $code = null;

    $this->post(route('password.email'), [
        'email' => $user->email,
    ])->assertSessionHas('status');

    Notification::assertSentTo($user, PasswordResetCodeNotification::class, function (PasswordResetCodeNotification $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});
