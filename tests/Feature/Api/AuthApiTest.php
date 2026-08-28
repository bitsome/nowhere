<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('api login returns a sanctum token', function () {
    User::factory()->create([
        'email' => 'driver@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'driver@example.com',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['token', 'user' => ['id', 'name', 'email', 'role', 'permissions']],
        ]);
});

test('api login rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'driver@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'driver@example.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

test('api login supports phone number', function () {
    User::factory()->create([
        'email' => 'driver@example.com',
        'phone' => '010-1234-5678',
        'password' => Hash::make('secret123'),
    ]);

    // 하이픈 있는 그대로 입력
    $this->postJson('/api/auth/login', [
        'login' => '010-1234-5678',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'driver@example.com');

    // 하이픈·공백 제거한 입력도 동일 매칭
    $this->postJson('/api/auth/login', [
        'login' => '010 1234 5678',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'driver@example.com');

    // 아무 표기 없이 숫자만 입력
    $this->postJson('/api/auth/login', [
        'login' => '01012345678',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'driver@example.com');
});

test('api login fails when phone does not match any account', function () {
    User::factory()->create([
        'email' => 'driver@example.com',
        'phone' => '010-1234-5678',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/auth/login', [
        'login' => '010-9999-9999',
        'password' => 'secret123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

test('api me returns the authenticated user', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('api profile updates name and phone', function () {
    $user = User::factory()->create([
        'id' => 2,
        'phone' => '01011112222',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/auth/me', [
            'name' => '새이름',
            'phone' => '01099998888',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', '새이름')
        ->assertJsonPath('data.phone', '01099998888')
        ->assertJsonPath('data.email', $user->email);

    expect($user->fresh()?->name)->toBe('새이름');
    expect($user->fresh()?->phone)->toBe('01099998888');
});

test('api profile validates required name', function () {
    $user = User::factory()->create(['id' => 2]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/auth/me', ['name' => '', 'phone' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('api logout revokes the current token', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

test('api routes require authentication', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->getJson('/api/orders')->assertUnauthorized();
    $this->getJson('/api/options/orders')->assertUnauthorized();
});
