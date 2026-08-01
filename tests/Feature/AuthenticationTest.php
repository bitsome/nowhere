<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests can view the login screen', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('로그인')
        ->assertSee('비밀번호 찾기')
        ->assertSee('회원가입');
});

test('users can authenticate with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $user->refresh();

    $this->assertAuthenticatedAs($user);
    expect($user->last_login_at)->not->toBeNull()
        ->and($user->login_count)->toBeGreaterThan(0);
});

test('users can be remembered when requested', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ])->assertRedirect(route('dashboard'))
        ->assertCookie(app('auth')->guard()->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with invalid credentials', function () {
    $user = User::factory()->create();

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated users can log out', function () {
    $user = User::factory()->create([
        'remember_token' => 'existing-token',
    ]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    expect($user->fresh()->remember_token)->toBeNull();
    $this->assertGuest();
});

test('guests are redirected to the login screen from the dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});
