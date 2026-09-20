<?php

use App\Models\Order;
use App\Models\User;
use App\Support\Orders\OrderListRowBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 시각 고정(KST 오전 6시) — 경계 판정이 실행 시각에 흔들리지 않게 한다
    $this->travelTo(Carbon::parse('2026-09-13 06:00:00', 'Asia/Seoul'));

    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
    $this->owner = User::factory()->create();

    Sanctum::actingAs($this->driver);
});

/**
 * 오늘 KST 기준으로 시각을 옮긴 운행을 만든다 (분 단위, 음수면 이미 지난 시각).
 */
function urgencyOrder(int $userId, string $status, int $minutesFromNow): Order
{
    $now = now('Asia/Seoul');

    return Order::factory()->create([
        'user_id' => $userId,
        'status' => $status,
        'service_date' => $now->format('Y-m-d'),
        'service_time' => $now->copy()->addMinutes($minutesFromNow)->format('H:i'),
    ]);
}

test('서비스 시각이 안 지났으면 남은 시간과 무관하게 임박이다', function () {
    $soon = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, 30);
    // 15시간 뒤(21:00) — 2시간 상한을 없앴으므로 오늘 남은 운행은 모두 임박이다
    $later = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, 900);

    $builder = app(OrderListRowBuilder::class);

    expect($builder->isUrgent($soon))->toBeTrue()
        ->and($builder->isUrgent($later))->toBeTrue()
        ->and($builder->isPriority($later))->toBeFalse();
});

test('서비스 시각이 지난 공개 운행은 긴급으로 올린다', function () {
    $order = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, -30);

    $builder = app(OrderListRowBuilder::class);

    expect($builder->isUrgent($order))->toBeFalse()
        ->and($builder->isPriority($order))->toBeTrue();
});

test('지난 뒤에는 시간 제한 없이 긴급이다', function () {
    // 5시간 전(01:00) — 하한을 없앴으므로 오늘 지난 운행은 모두 긴급이다
    $order = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, -300);

    $builder = app(OrderListRowBuilder::class);

    expect($builder->isUrgent($order))->toBeFalse()
        ->and($builder->isPriority($order))->toBeTrue();
});

test('이미 기사가 정해진 운행은 시각이 지나도 긴급이 아니다', function () {
    $order = urgencyOrder($this->owner->id, Order::STATUS_DRIVING, -30);

    expect(app(OrderListRowBuilder::class)->isPriority($order))->toBeFalse();
});

test('등록자가 긴급으로 올린 운행은 시각과 무관하게 긴급이다', function () {
    $order = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, 600);

    $order->forceFill(['is_priority' => true])->save();

    expect(app(OrderListRowBuilder::class)->isPriority($order->fresh()))->toBeTrue();
});

test('긴급 빠른보기는 시각이 지난 공개 운행도 함께 보여준다', function () {
    $soon = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, 30);
    $late = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, -30);

    $ids = collect($this->getJson('/api/orders?scope=market&quick=priority&per_page=100')
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($ids)->toContain($late->id)
        ->and($ids)->not->toContain($soon->id);
});

test('임박 빠른보기는 오늘 남은 운행을 시간 제한 없이 보여준다', function () {
    $soon = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, 30);
    $later = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, 900);
    $past = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, -30);

    $ids = collect($this->getJson('/api/orders?scope=market&quick=urgent&per_page=100')
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($ids)->toContain($soon->id, $later->id)
        ->and($ids)->not->toContain($past->id);
});

test('시작 시각이 1시간 넘게 지난 운행은 마켓 목록에서 빠진다', function () {
    $recent = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, -30);
    $older = urgencyOrder($this->owner->id, Order::STATUS_PUBLISHED, -90);

    $ids = collect($this->getJson('/api/orders?scope=market&per_page=100')
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($ids)->toContain($recent->id)
        ->and($ids)->not->toContain($older->id);
});

test('단자리 시각도 두 자리로 맞춰 저장한다', function () {
    // 저장은 문자열 컬럼이고 컷·자동 취소가 문자열로 비교한다 — `4:30`은 `05:00`보다 뒤로 읽힌다
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '7:30',
    ]);

    expect($order->fresh()->service_time)->toBe('07:30');
});

test('단자리 시각으로 저장된 지난 운행도 마켓 목록에서 빠진다', function () {
    // 04:30 — 컷(05:00)보다 이전이라 빠져야 한다. 두 자리로 맞추지 않으면 `'4' > '0'` 으로 남는다
    $past = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '4:30',
    ]);

    $ids = collect($this->getJson('/api/orders?scope=market&per_page=100')
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($ids)->not->toContain($past->id);
});

test('단자리 시각으로 저장된 지난 운행도 자동 취소된다', function () {
    $past = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '4:30',
    ]);

    $this->artisan('orders:close-published')->assertSuccessful();

    expect($past->fresh()->status)->not->toBe(Order::STATUS_PUBLISHED);
});
