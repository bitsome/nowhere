<?php

use App\Models\Order;
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

test('등록자(업체)는 프로필에서 업체명을 저장하고 다시 내려받는다', function () {
    $user = User::factory()->create(['id' => 2, 'role' => User::ROLE_CUSTOMER]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/auth/me', [
            'name' => '홍담당',
            'phone' => '01011112222',
            'company_name' => '서울투어 주식회사',
        ])
        ->assertOk()
        ->assertJsonPath('data.company_name', '서울투어 주식회사');

    expect($user->fresh()?->company_name)->toBe('서울투어 주식회사');
});

test('기사(비등록자) 프로필 변경에 포함된 업체명은 저장되지 않는다', function () {
    $user = User::factory()->create(['id' => 3, 'role' => User::ROLE_DRIVER]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/auth/me', [
            'name' => '김기사',
            'company_name' => '무시되어야 함',
        ])
        ->assertOk();

    expect($user->fresh()?->company_name)->toBeNull();
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

test('api register defaults to driver role', function () {
    $this->postJson('/api/auth/register', [
        'name' => '새기사',
        'email' => 'driver-new@example.com',
        'password' => 'secret123',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.user.role', User::ROLE_DRIVER);

    expect(User::where('email', 'driver-new@example.com')->value('role'))->toBe(User::ROLE_DRIVER);
});

test('api register accepts customer(등록자) role', function () {
    $this->postJson('/api/auth/register', [
        'name' => '새등록자',
        'email' => 'customer-new@example.com',
        'password' => 'secret123',
        'role' => User::ROLE_CUSTOMER,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.user.role', User::ROLE_CUSTOMER)
        // 등록자는 운행을 등록할 수 있어야 한다
        ->assertJsonPath('data.user.permissions', fn ($permissions) => in_array('order.create', $permissions, true));

    expect(User::where('email', 'customer-new@example.com')->value('role'))->toBe(User::ROLE_CUSTOMER);
});

test('api register rejects unknown role', function () {
    $this->postJson('/api/auth/register', [
        'name' => '관리자흉내',
        'email' => 'hacker@example.com',
        'password' => 'secret123',
        'role' => User::ROLE_ADMIN,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('customer(등록자) cannot claim a ride', function () {
    $owner = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);
    $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

    $this->actingAs($customer)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertStatus(403);
});

test('api login locks the account after five consecutive failures', function () {
    $user = User::factory()->create([
        'email' => 'lock@example.com',
        'password' => Hash::make('secret123'),
    ]);

    // 5회 연속 실패 — 마지막 실패에서 잠금
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => 'lock@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    // 잠금 후에는 올바른 비밀번호도 거부된다
    $locked = $this->postJson('/api/auth/login', [
        'email' => 'lock@example.com',
        'password' => 'secret123',
    ])->assertStatus(422);

    expect($locked->json('errors.login.0'))->toContain('잠겼');

    // 비밀번호는 그대로다
    expect(Hash::check('secret123', $user->fresh()?->password))->toBeTrue();
});

test('api login resets the failure counter on success', function () {
    User::factory()->create([
        'email' => 'retry@example.com',
        'password' => Hash::make('secret123'),
    ]);

    // 2회 실패 후 정상 로그인 성공
    for ($i = 0; $i < 2; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => 'retry@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/auth/login', [
        'email' => 'retry@example.com',
        'password' => 'secret123',
    ])->assertOk();

    // 성공했으므로 실패 횟수가 초기화되어 다시 5회의 시도 기회가 주어진다
    $failed = $this->postJson('/api/auth/login', [
        'email' => 'retry@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422);

    expect($failed->json('errors.login.0'))->toContain('남은 시도 4회');
});
