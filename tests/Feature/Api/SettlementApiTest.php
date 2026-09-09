<?php

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Settlement\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->registrant = User::factory()->create(['name' => '등록자']);
    $this->driver = User::factory()->create(['name' => '수행기사', 'role' => User::ROLE_DRIVER]);
});

function completedOrder($driver, $registrant, int $amount = 100000): Order
{
    return Order::factory()->create([
        'user_id' => $driver->id,
        'original_owner_id' => $registrant->id,
        'status' => Order::STATUS_COMPLETED,
        'amount_value' => $amount,
        'expected_revenue' => $amount,
    ]);
}

test('settling an order creates the settlement ledger with a 5% fee', function () {
    $order = completedOrder($this->driver, $this->registrant);

    Sanctum::actingAs($this->registrant);

    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])
        ->assertOk()
        ->assertJsonPath('data.settled', 1);

    $settlement = Settlement::query()->firstOrFail();

    expect($settlement->order_id)->toBe($order->id);
    expect($settlement->driver_id)->toBe($this->driver->id);
    expect($settlement->gross_amount)->toBe(100000);
    expect($settlement->fee_amount)->toBe(5000);
    expect($settlement->net_amount)->toBe(95000);
    expect($settlement->status)->toBe(Settlement::STATUS_PENDING);
    expect($order->fresh()?->status)->toBe(Order::STATUS_SETTLED);
});

test('driver settlement summary reports pending payout amount and this month stats', function () {
    completedOrder($this->driver, $this->registrant, 200000);
    $order2 = completedOrder($this->driver, $this->registrant, 60000);
    $order2->update(['actual_revenue' => 60000]);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order2->id]])->assertOk();

    Sanctum::actingAs($this->driver);

    $this->getJson('/api/me/settlement')
        ->assertOk()
        ->assertJsonPath('data.pending_total', 57000) // 60000 - 5%
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonPath('data.this_month.count', 1)
        ->assertJsonPath('data.this_month.net', 57000);
});

test('driver can register an account and request a payout of the pending amount', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    Sanctum::actingAs($this->driver);

    // 계좌 등록
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();

    // 출금 신청 — 출금 가능 금액(95000) 전체
    $this->postJson('/api/me/payouts')
        ->assertCreated()
        ->assertJsonPath('data.amount', 95000);

    $payout = PayoutRequest::query()->firstOrFail();
    $settlement = Settlement::query()->firstOrFail();

    expect($payout->status)->toBe(PayoutRequest::STATUS_PENDING);
    expect($payout->bank_name)->toBe('국민은행');
    expect($settlement->payout_id)->toBe($payout->id);

    // 출금 가능 금액이 0이 되므로 재신청은 거절된다
    $this->postJson('/api/me/payouts')->assertStatus(409);
});

test('driver cannot request a payout without a registered account', function () {
    completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $order = Order::where('status', Order::STATUS_COMPLETED)->firstOrFail();
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    Sanctum::actingAs($this->driver);

    $this->postJson('/api/me/payouts')->assertStatus(422);
});

test('admin can pay out a payout request and mark its settlements paid', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();
    $this->postJson('/api/me/payouts')->assertCreated();

    $payout = PayoutRequest::query()->firstOrFail();

    // 관리자가 지급 처리
    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/payouts')->assertOk()->assertJsonCount(1, 'data');

    $this->postJson("/api/admin/payouts/{$payout->id}/pay")->assertOk();

    expect($payout->fresh()?->status)->toBe(PayoutRequest::STATUS_PAID);
    expect(Settlement::query()->first()?->status)->toBe(Settlement::STATUS_PAID);
    expect(Settlement::query()->first()?->paid_at)->not->toBeNull();
});

test('admin can reject a payout and free its settlements for a later request', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();
    $this->postJson('/api/me/payouts')->assertCreated();

    $payout = PayoutRequest::query()->firstOrFail();

    Sanctum::actingAs($admin);

    $this->postJson("/api/admin/payouts/{$payout->id}/reject", ['reason' => '계좌 정보 확인 필요'])
        ->assertOk();

    expect($payout->fresh()?->status)->toBe(PayoutRequest::STATUS_REJECTED);
    expect(Settlement::query()->first()?->payout_id)->toBeNull();

    // 거절된 정산은 다시 출금 신청할 수 있다
    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/payouts')->assertCreated();
});

test('non-admin cannot access payout processing', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();
    $this->postJson('/api/me/payouts')->assertCreated();

    $payout = PayoutRequest::query()->firstOrFail();

    // 관리자 전용 처리를 일반 기사가 호출하면 거부된다
    $this->getJson('/api/admin/payouts')->assertForbidden();
    $this->postJson("/api/admin/payouts/{$payout->id}/pay")->assertForbidden();
    $this->postJson("/api/admin/payouts/{$payout->id}/reject")->assertForbidden();
});

test('settlement fee rate constant is applied via the service', function () {
    expect(SettlementService::FEE_RATE)->toBe(0.05);
});
