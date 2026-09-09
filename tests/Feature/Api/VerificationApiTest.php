<?php

use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
        'name' => '홍기사',
    ]);

    $this->admin = User::factory()->create([
        'id' => 88,
        'role' => User::ROLE_ADMIN,
        'name' => '관리자',
    ]);
});

test('driver submits vehicle proof image -> pending request + admin notified', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'vehicle',
        'image' => UploadedFile::fake()->image('vehicle.jpg'),
        'note' => '차량등록증 원본',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'vehicle')
        ->assertJsonPath('data.type_label', '차량')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.status_label', '심사 대기');

    expect($this->admin->notifications()->where('data->title', '인증 심사 요청')->count())->toBe(1);

    // 저장된 경로 기준으로 파일 존재 확인 (파일명은 내용 해시)
    $saved = VerificationRequest::query()->firstOrFail();
    expect(Storage::disk('public')->exists($saved->image_path))->toBeTrue();
});

test('duplicate pending request for same type is rejected', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'license',
        'image' => UploadedFile::fake()->image('license.jpg'),
    ])->assertCreated();

    $this->post('/api/verification/request', [
        'type' => 'license',
        'image' => UploadedFile::fake()->image('license2.jpg'),
    ])->assertStatus(422);
});

test('already verified item cannot be requested again', function () {
    Storage::fake('public');
    $verified = User::factory()->create(['role' => User::ROLE_DRIVER, 'is_license_verified' => true]);
    Sanctum::actingAs($verified);

    $this->post('/api/verification/request', [
        'type' => 'license',
        'image' => UploadedFile::fake()->image('license.jpg'),
    ])->assertStatus(422);
});

test('image is required for verification request', function () {
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', ['type' => 'vehicle'])
        ->assertStatus(422);
});

test('my verification summary returns latest status per type', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'vehicle',
        'image' => UploadedFile::fake()->image('vehicle.jpg'),
    ])->assertCreated();

    $this->getJson('/api/verification/requests/mine')
        ->assertOk()
        ->assertJsonPath('data.vehicle.status', 'pending')
        ->assertJsonPath('data.license', null);
});

test('admin list requires admin role and returns pending first', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'vehicle',
        'image' => UploadedFile::fake()->image('vehicle.jpg'),
    ])->assertCreated();

    // 비관리자는 접근 불가
    $this->getJson('/api/admin/verifications')->assertStatus(403);

    // 관리자는 심사 목록 조회
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/verifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type_label', '차량')
        ->assertJsonPath('data.0.user.name', '홍기사');
});

test('admin approves request -> user verification enabled + result notified', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'vehicle',
        'image' => UploadedFile::fake()->image('vehicle.jpg'),
    ])->assertCreated();

    $requestId = VerificationRequest::query()->firstOrFail()->id;

    Sanctum::actingAs($this->admin);
    $this->post("/api/admin/verifications/{$requestId}/review", [
        'status' => 'approved',
        'note' => '서류 확인 완료',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($this->driver->fresh()?->is_vehicle_verified)->toBeTrue();
    expect($this->driver->notifications()->where('data->title', '인증 심사 결과')->count())->toBe(1);
});

test('admin rejects request -> reason required + verification stays off', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'license',
        'image' => UploadedFile::fake()->image('license.jpg'),
    ])->assertCreated();

    $requestId = VerificationRequest::query()->firstOrFail()->id;

    // 거절 사유 없으면 422
    Sanctum::actingAs($this->admin);
    $this->post("/api/admin/verifications/{$requestId}/review", ['status' => 'rejected'])
        ->assertStatus(422);

    $this->post("/api/admin/verifications/{$requestId}/review", [
        'status' => 'rejected',
        'note' => '차량 등록번호가 보이지 않습니다. 다시 찍어 주세요.',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($this->driver->fresh()?->is_license_verified)->toBeFalse();
    expect($this->driver->notifications()->where('data->title', '인증 심사 결과')->count())->toBe(1);
});

test('already reviewed request cannot be reviewed again', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'vehicle',
        'image' => UploadedFile::fake()->image('vehicle.jpg'),
    ])->assertCreated();

    $requestId = VerificationRequest::query()->firstOrFail()->id;

    Sanctum::actingAs($this->admin);
    $this->post("/api/admin/verifications/{$requestId}/review", ['status' => 'approved'])
        ->assertOk();

    $this->post("/api/admin/verifications/{$requestId}/review", ['status' => 'rejected', 'note' => '재심사'])
        ->assertStatus(422);
});

// ── Q-4: 등록자(Customer) 사업자등록증·대표 계좌 인증 ──

test('customer submits business registration proof -> pending request + own summary has business type', function () {
    Storage::fake('public');
    $customer = User::factory()->create(['id' => 3, 'role' => User::ROLE_CUSTOMER, 'name' => '서울투어']);
    Sanctum::actingAs($customer);

    $this->post('/api/verification/request', [
        'type' => 'business',
        'image' => UploadedFile::fake()->image('business.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'business')
        ->assertJsonPath('data.type_label', '사업자등록증');

    $this->getJson('/api/verification/requests/mine')
        ->assertOk()
        ->assertJsonPath('data.business.status', 'pending')
        ->assertJsonPath('data.account', null);
});

test('role mismatch verification type is rejected', function () {
    Storage::fake('public');
    // 기사(Driver)는 사업자등록증(business)을 신청할 수 없다
    Sanctum::actingAs($this->driver);

    $this->post('/api/verification/request', [
        'type' => 'business',
        'image' => UploadedFile::fake()->image('business.jpg'),
    ])->assertStatus(422);
});

test('admin approves customer business proof -> is_business_verified enabled', function () {
    Storage::fake('public');
    $customer = User::factory()->create(['id' => 3, 'role' => User::ROLE_CUSTOMER, 'name' => '서울투어']);
    Sanctum::actingAs($customer);

    $this->post('/api/verification/request', [
        'type' => 'business',
        'image' => UploadedFile::fake()->image('business.jpg'),
    ])->assertCreated();

    $requestId = VerificationRequest::query()->firstOrFail()->id;

    Sanctum::actingAs($this->admin);
    $this->post("/api/admin/verifications/{$requestId}/review", [
        'status' => 'approved',
        'note' => '사업자등록증 확인 완료',
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    expect($customer->fresh()?->is_business_verified)->toBeTrue();
    expect($customer->notifications()->where('data->title', '인증 심사 결과')->count())->toBe(1);
});

test('admin rejects customer account proof -> is_account_verified stays off', function () {
    Storage::fake('public');
    $customer = User::factory()->create(['id' => 3, 'role' => User::ROLE_CUSTOMER, 'name' => '서울투어']);
    Sanctum::actingAs($customer);

    $this->post('/api/verification/request', [
        'type' => 'account',
        'image' => UploadedFile::fake()->image('account.jpg'),
    ])->assertCreated();

    $requestId = VerificationRequest::query()->firstOrFail()->id;

    Sanctum::actingAs($this->admin);
    $this->post("/api/admin/verifications/{$requestId}/review", [
        'status' => 'rejected',
        'note' => '예금주가 업체명과 다릅니다.',
    ])->assertOk();

    expect($customer->fresh()?->is_account_verified)->toBeFalse();
});
