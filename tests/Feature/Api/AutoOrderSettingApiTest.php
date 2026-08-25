<?php

use App\Models\AutoOrderSetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['id' => 2, 'role' => 'Admin']);
    $this->driver = User::factory()->create(['id' => 3, 'role' => 'Driver']);
});

test('admin can fetch auto order settings with defaults', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/auto-order-settings')
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.min_count', 10)
        ->assertJsonPath('data.max_count', 20);
});

test('non-admin cannot access auto order settings', function () {
    Sanctum::actingAs($this->driver);

    $this->getJson('/api/admin/auto-order-settings')->assertForbidden();
    $this->patchJson('/api/admin/auto-order-settings', ['is_active' => true])->assertForbidden();
});

test('admin can update auto order settings and max is clamped to min', function () {
    Sanctum::actingAs($this->admin);

    $this->patchJson('/api/admin/auto-order-settings', [
        'is_active' => true,
        'min_count' => 5,
        'max_count' => 3,
    ])->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.min_count', 5)
        ->assertJsonPath('data.max_count', 5);

    expect(AutoOrderSetting::current()->is_active)->toBeTrue();
});

test('auto register command does nothing when disabled', function () {
    AutoOrderSetting::current()->update(['is_active' => false]);

    $this->artisan('orders:auto-register')->assertSuccessful();

    expect(Order::count())->toBe(0);
});

test('auto register command creates orders as the admin owner', function () {
    $this->admin->update(['name' => '관리자 계정']);

    AutoOrderSetting::current()->update([
        'is_active' => true,
        'min_count' => 3,
        'max_count' => 3,
        'owner_user_id' => $this->admin->id,
    ]);

    $this->artisan('orders:auto-register')->assertSuccessful();

    $orders = Order::all();
    expect($orders)->toHaveCount(3)
        ->and($orders->every(fn ($o) => $o->user_id === $this->admin->id))->toBeTrue()
        ->and($orders->every(fn ($o) => $o->status === Order::STATUS_PUBLISHED))->toBeTrue()
        ->and($orders->every(fn ($o) => $o->order_number !== null))->toBeTrue();

    $setting = AutoOrderSetting::current();
    expect($setting->last_run_at)->not->toBeNull();
});

test('auto register command uses default admin when no owner configured', function () {
    $otherAdmin = User::factory()->create(['id' => 5, 'role' => 'Admin', 'name' => '서브 관리자']);

    AutoOrderSetting::current()->update([
        'is_active' => true,
        'min_count' => 1,
        'max_count' => 1,
        'owner_user_id' => null,
    ]);

    $this->artisan('orders:auto-register')->assertSuccessful();

    // Admin 중 id가 가장 작은 계정(id=2)이 등록자
    expect(Order::first()->user_id)->toBe($this->admin->id);
    expect(Order::where('user_id', $otherAdmin->id)->count())->toBe(0);
});

test('auto register command marks orders as auto registered and notifies owner', function () {
    AutoOrderSetting::current()->update([
        'is_active' => true,
        'min_count' => 2,
        'max_count' => 2,
        'owner_user_id' => $this->admin->id,
    ]);

    $this->artisan('orders:auto-register')->assertSuccessful();

    expect(Order::where('auto_registered', true)->count())->toBe(2)
        ->and($this->admin->notifications()->count())->toBe(1);
});

test('admin can list and delete auto registered orders', function () {
    AutoOrderSetting::current()->update([
        'is_active' => true,
        'min_count' => 3,
        'max_count' => 3,
        'owner_user_id' => $this->admin->id,
    ]);

    $this->artisan('orders:auto-register')->assertSuccessful();

    // 수동 등록 운행은 이력에 포함되지 않는다
    $manual = Order::factory()->create(['user_id' => $this->admin->id, 'auto_registered' => false]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/auto-orders')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissing(['id' => $manual->id]);

    // ids로 개별 삭제
    $first = Order::where('auto_registered', true)->first();

    $this->postJson('/api/admin/auto-orders/delete', ['ids' => [$first->id]])
        ->assertOk()
        ->assertJsonPath('data.deleted', 1);

    expect(Order::find($first->id))->toBeNull();

    // 전체 삭제 — 수동 등록 운행은 유지된다
    $this->postJson('/api/admin/auto-orders/delete', ['all' => true])
        ->assertOk();

    expect(Order::where('auto_registered', true)->count())->toBe(0)
        ->and(Order::find($manual->id))->not->toBeNull();
});

test('non-admin cannot manage auto orders', function () {
    Order::factory()->create(['user_id' => $this->driver->id, 'auto_registered' => true]);

    Sanctum::actingAs($this->driver);

    $this->getJson('/api/admin/auto-orders')->assertForbidden();
    $this->postJson('/api/admin/auto-orders/delete', ['all' => true])->assertForbidden();
});
