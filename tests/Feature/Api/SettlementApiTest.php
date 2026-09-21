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

// 등록자 입금을 수금 확정 처리한다 (관리자 입금 확인에 해당)
function collectSettlement(Settlement $settlement): void
{
    $settlement->forceFill([
        'collection_status' => Settlement::COLLECTION_PAID,
        'collected_at' => now(),
        'collected_by' => 1,
    ])->save();
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
    expect($settlement->collection_status)->toBe(Settlement::COLLECTION_PENDING);
    expect($order->fresh()?->status)->toBe(Order::STATUS_SETTLED);
});

test('driver settlement summary reports pending payout amount and this month stats', function () {
    completedOrder($this->driver, $this->registrant, 200000);
    $order2 = completedOrder($this->driver, $this->registrant, 60000);
    $order2->update(['actual_revenue' => 60000]);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order2->id]])->assertOk();

    Sanctum::actingAs($this->driver);

    // 입금 전에는 출금 가능 금액이 0이고, 수금 대기로 집계된다
    $this->getJson('/api/me/settlement')
        ->assertOk()
        ->assertJsonPath('data.pending_total', 0)
        ->assertJsonPath('data.awaiting_collection_total', 57000)
        ->assertJsonPath('data.this_month.count', 1)
        ->assertJsonPath('data.this_month.net', 57000);

    // 등록자 입금이 확인되면 출금 가능 금액이 된다
    collectSettlement(Settlement::query()->firstOrFail());

    $this->getJson('/api/me/settlement')
        ->assertOk()
        ->assertJsonPath('data.pending_total', 57000)
        ->assertJsonPath('data.pending_count', 1);
});

test('driver can register an account and request a payout of the pending amount', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();
    collectSettlement(Settlement::query()->firstOrFail());

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
    collectSettlement(Settlement::query()->firstOrFail());

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
    collectSettlement(Settlement::query()->firstOrFail());

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
    collectSettlement(Settlement::query()->firstOrFail());

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

test('fee rate comes from config and is snapshotted on the ledger', function () {
    config(['settlement.fee_rate' => 0.1]);

    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    $settlement = Settlement::query()->firstOrFail();

    expect($settlement->fee_amount)->toBe(10000);
    expect($settlement->net_amount)->toBe(90000);
    expect($settlement->fee_rate)->toBe(0.1); // 정산 시점 요율이 원장에 남는다
});

test('past settlement keeps its rate snapshot when the policy changes', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    // 운영 중 요율을 올려도 이미 확정된 정산 금액은 그대로 유지된다
    config(['settlement.fee_rate' => 0.2]);

    Sanctum::actingAs($this->driver);
    $this->getJson('/api/me/settlement')
        ->assertOk()
        ->assertJsonPath('data.recent.0.fee_amount', 5000)
        ->assertJsonPath('data.recent.0.fee_rate', 0.05)
        ->assertJsonPath('data.fee_rate', 0.2); // 화면 안내용 현재 요율은 새 값
});

test('fee policy applies the rate, the minimum fee, and the gross cap', function () {
    config(['settlement.fee_rate' => 0.07, 'settlement.min_fee' => 2000]);

    $service = app(SettlementService::class);

    expect($service->feeRate())->toBe(0.07);
    expect($service->calculateFee(100000))->toBe(7000); // 요율 적용
    expect($service->calculateFee(10000))->toBe(2000);  // 최소 수수료 보장 (7% = 700원)
    expect($service->calculateFee(1000))->toBe(1000);   // 수수료가 운행금액을 넘지 않는다
});

test('registrant specific fee rate overrides the global policy', function () {
    $this->registrant->forceFill(['fee_rate' => 0.02])->save();

    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    $settlement = Settlement::query()->firstOrFail();

    expect($settlement->fee_amount)->toBe(2000);
    expect($settlement->net_amount)->toBe(98000);
    expect($settlement->fee_rate)->toBe(0.02);
});

test('registrant without a fee rate uses the global policy rate', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    expect(Settlement::query()->firstOrFail()->fee_rate)->toBe(0.05);
});

test('driver cannot request a payout until the collection is confirmed', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();

    // 입금 확인 전에는 출금할 정산 금액이 없다
    $this->postJson('/api/me/payouts')->assertStatus(409);
});

test('admin confirms collection through the api which unlocks driver payout', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    $settlement = Settlement::query()->firstOrFail();

    Sanctum::actingAs($admin);

    // 입금 확인 대기 목록에 보인다
    $this->getJson('/api/admin/settlements/pending-collection')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.gross_amount', 100000);

    // 입금 확인 처리
    $this->postJson("/api/admin/settlements/{$settlement->id}/collect", ['note' => '입금 확인'])
        ->assertOk();

    expect($settlement->fresh()?->collection_status)->toBe(Settlement::COLLECTION_PAID);
    expect($settlement->fresh()?->collected_at)->not->toBeNull();
    expect($settlement->fresh()?->collected_by)->toBe($admin->id);

    // 재확인 시도는 거절된다
    $this->postJson("/api/admin/settlements/{$settlement->id}/collect")->assertStatus(409);
});

test('non-admin cannot confirm collection', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    $settlement = Settlement::query()->firstOrFail();

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/admin/settlements/{$settlement->id}/collect")->assertForbidden();

    expect($settlement->fresh()?->collection_status)->toBe(Settlement::COLLECTION_PENDING);
});

test('registrant payables lists unpaid settlements and totals', function () {
    $order = completedOrder($this->driver, $this->registrant, 100000);
    $order2 = completedOrder($this->driver, $this->registrant, 60000);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id, $order2->id]])->assertOk();

    $this->getJson('/api/me/payables')
        ->assertOk()
        ->assertJsonPath('data.unpaid_count', 2)
        ->assertJsonPath('data.unpaid_total', 160000)
        ->assertJsonCount(2, 'data.recent');

    // 1건 입금 확인 후 미수금 합계가 줄어든다
    collectSettlement(Settlement::query()->firstOrFail());

    $this->getJson('/api/me/payables')
        ->assertOk()
        ->assertJsonPath('data.unpaid_count', 1)
        ->assertJsonPath('data.unpaid_total', 60000);
});

test('first revenue lifecycle completes the full money flow', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = completedOrder($this->driver, $this->registrant, 100000);

    // 1) 등록자가 운행을 정산 → 원장(gross 10만 / fee 5천 / net 9.5만), 입금 대기
    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    $settlement = Settlement::query()->firstOrFail();
    expect($settlement->collection_status)->toBe(Settlement::COLLECTION_PENDING);

    // 2) 등록자에게 입금 안내, 기사에게 정산 완료(입금 확인 후 출금) 알림
    expect($this->registrant->notifications()->where('data->title', '운행 대금 입금 안내')->count())->toBe(1);
    expect($this->driver->notifications()->where('data->title', '정산 완료')->count())->toBe(1);

    // 3) 기사는 입금 전 출금 불가
    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();
    $this->postJson('/api/me/payouts')->assertStatus(409);

    // 4) 관리자가 등록자 입금을 확인 → 수금 확정 (첫 수익 발생 지점)
    Sanctum::actingAs($admin);
    $this->getJson('/api/admin/settlements/pending-collection')->assertJsonCount(1, 'data');
    $this->postJson("/api/admin/settlements/{$settlement->id}/collect")->assertOk();

    expect($settlement->fresh()?->collection_status)->toBe(Settlement::COLLECTION_PAID);

    // 5) 기사 출금 가능 금액 반영 → 출금 신청
    Sanctum::actingAs($this->driver);
    $this->getJson('/api/me/settlement')->assertJsonPath('data.pending_total', 95000);
    $this->postJson('/api/me/payouts')->assertCreated()->assertJsonPath('data.amount', 95000);

    // 6) 관리자가 기사 출금을 지급 → 정산 지급 완료
    $payout = PayoutRequest::query()->firstOrFail();
    Sanctum::actingAs($admin);
    $this->postJson("/api/admin/payouts/{$payout->id}/pay")->assertOk();

    expect($settlement->fresh()?->status)->toBe(Settlement::STATUS_PAID);
    expect($settlement->fresh()?->paid_at)->not->toBeNull();

    // 7) 등록자 미수금 0 + 관리자 매출 지표에 수수료 5천 반영
    Sanctum::actingAs($this->registrant);
    $this->getJson('/api/me/payables')->assertJsonPath('data.unpaid_count', 0);

    Sanctum::actingAs($admin);
    $this->getJson('/api/admin/operations/metrics')
        ->assertOk()
        ->assertJsonPath('data.revenue.month_fee', 5000)
        ->assertJsonPath('data.revenue.month_gross', 100000);
});

test('수금 확인과 출금 지급이 감사 로그에 라벨과 함께 남는다', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => '운영자']);
    $order = completedOrder($this->driver, $this->registrant);

    Sanctum::actingAs($this->registrant);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();
    $settlement = Settlement::query()->firstOrFail();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '홍길동',
    ])->assertOk();
    $this->postJson('/api/me/payouts')->assertStatus(409);

    Sanctum::actingAs($admin);
    $this->postJson("/api/admin/settlements/{$settlement->id}/collect")->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/payouts')->assertCreated();
    $payout = PayoutRequest::query()->firstOrFail();

    Sanctum::actingAs($admin);
    $this->postJson("/api/admin/payouts/{$payout->id}/pay")->assertOk();

    // 돈이 움직인 두 지점이 누가·언제 했는지와 함께 남고, 화면에는 한국어 라벨로 보인다
    $this->getJson('/api/admin/operations/audit')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'payout.pay')
        ->assertJsonPath('data.0.action_label', '출금 지급')
        ->assertJsonPath('data.0.admin_name', '운영자')
        ->assertJsonPath('data.0.meta.amount', 95000)
        ->assertJsonPath('data.1.action', 'settlement.collect')
        ->assertJsonPath('data.1.action_label', '수금 확인')
        ->assertJsonPath('data.1.meta.settlement_id', $settlement->id);
});
