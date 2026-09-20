<?php

use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderFavorite;
use App\Models\OrderGroup;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 찜한 운행(즐겨찾기) 개수 — 사용자 + 운행 기준.
 */
function favoriteCount(int $userId, int $orderId): int
{
    return OrderFavorite::query()
        ->where('user_id', $userId)
        ->where('order_id', $orderId)
        ->count();
}

/**
 * 특정 운행에 남은 찜 알림 개수 — 제목으로 구분해 검증한다.
 */
function favoriteNotificationCount(User $user, Order $order, string $title): int
{
    return $user->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->order_id', $order->id)
        ->where('data->title', $title)
        ->count();
}

function favoriteMarketOrder(int $ownerId, ?string $date = null, string $time = '10:00'): Order
{
    // 날짜를 고정하면 그 시각이 지나는 순간 마켓에서 만료되어 테스트가 깨진다 → 항상 미래 날짜를 쓴다
    return Order::factory()->create([
        'user_id' => $ownerId,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
        'service_date' => $date ?? now('Asia/Seoul')->addDays(2)->format('Y-m-d'),
        'service_time' => $time,
    ]);
}

beforeEach(function () {
    // 찜하는 기사 — 마켓에서 운행을 보관하는 주체
    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
    ]);

    // 운행 등록자 — 등록/취소/조건 변경을 하는 주체
    $this->owner = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_OPERATOR,
    ]);

    // 이 운행을 실제로 가져가는 다른 기사
    $this->otherDriver = User::factory()->create([
        'id' => 3,
        'role' => User::ROLE_DRIVER,
    ]);

    $this->admin = User::factory()->create([
        'id' => 1,
        'role' => User::ROLE_ADMIN,
    ]);
});

test('찜 토글 — 하트를 누르면 찜되고 다시 누르면 해제된다 (목록에도 반영)', function () {
    $order = favoriteMarketOrder($this->owner->id);

    Sanctum::actingAs($this->driver);

    // 처음엔 찜 상태 아님
    $this->postJson("/api/orders/{$order->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', true);

    expect(favoriteCount($this->driver->id, $order->id))->toBe(1);

    // 찜 목록에 노출되고, 카드 하트 초기값(is_favorited)도 true로 내려온다
    $this->getJson('/api/orders/favorites')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $row = collect($this->getJson('/api/orders/favorites')->json('data'))->first();
    expect($row['is_favorited'])->toBeTrue();

    // 다시 누르면 해제
    $this->postJson("/api/orders/{$order->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', false);

    expect(favoriteCount($this->driver->id, $order->id))->toBe(0);

    $this->getJson('/api/orders/favorites')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);
});

test('셋트 행의 찜 여부는 대표 다리(첫 다리) 기준으로 내려온다', function () {
    $group = OrderGroup::query()->create(['name' => '찜 셋트', 'type' => '셋트']);

    // 같은 묶음의 두 다리 — 이른 시각(10:00)이 대표 다리가 된다
    $date = now('Asia/Seoul')->addDays(2)->format('Y-m-d');
    $first = favoriteMarketOrder($this->owner->id, $date, '10:00');
    $second = favoriteMarketOrder($this->owner->id, $date, '14:00');
    $first->forceFill(['group_id' => $group->id])->save();
    $second->forceFill(['group_id' => $group->id])->save();

    OrderFavorite::query()->create(['user_id' => $this->driver->id, 'order_id' => $first->id]);

    Sanctum::actingAs($this->driver);

    $row = collect($this->getJson('/api/orders?scope=market&per_page=100')->json('data'))
        ->firstWhere('kind', 'set');

    expect($row)->not->toBeNull()
        ->and($row['firstOrderId'])->toBe($first->id)
        ->and($row['is_favorited'])->toBeTrue();
});

test('상세 응답 — 찜 여부(favorited)를 함께 내려준다', function () {
    $order = favoriteMarketOrder($this->owner->id);

    Sanctum::actingAs($this->driver);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.favorited', false);

    $this->postJson("/api/orders/{$order->id}/favorite")->assertOk();

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.favorited', true);
});

test('찜 목록 — 마켓에 없는 운행(가져감·취소·숨김)은 조회 시 자동 정리된다', function () {
    $market = favoriteMarketOrder($this->owner->id, now('Asia/Seoul')->addDays(2)->format('Y-m-d'), '10:00');
    $cancelled = favoriteMarketOrder($this->owner->id, now('Asia/Seoul')->addDays(3)->format('Y-m-d'), '10:00');
    $cancelled->transitionTo(Order::STATUS_CANCELLED);

    Sanctum::actingAs($this->driver);

    // 정리 전 찜은 2건
    OrderFavorite::query()->create(['user_id' => $this->driver->id, 'order_id' => $market->id]);
    OrderFavorite::query()->create(['user_id' => $this->driver->id, 'order_id' => $cancelled->id]);

    // 취소된 운행의 찜은 목록 조회 시점에 삭제되고, 살아있는 찜만 보인다
    $this->getJson('/api/orders/favorites')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    expect(collect($this->getJson('/api/orders/favorites')->json('data'))->pluck('id'))
        ->toContain($market->id)
        ->not->toContain($cancelled->id);

    expect(favoriteCount($this->driver->id, $cancelled->id))->toBe(0);
    expect(favoriteCount($this->driver->id, $market->id))->toBe(1);
});

test('다른 기사가 가져가면(승인) 찜한 기사에게 알리고 찜이 정리된다', function () {
    $order = favoriteMarketOrder($this->owner->id);

    // 다른 기사가 신청 → 등록자가 승인 (운행이 마켓에서 빠진다)
    Sanctum::actingAs($this->otherDriver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/favorite")->assertOk();
    expect(favoriteCount($this->driver->id, $order->id))->toBe(1);

    $claim = OrderClaim::query()->where('order_id', $order->id)->pending()->firstOrFail();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/approve")->assertOk();

    // 찜한 기사에게 '배차 완료' 알림 + 찜 정리
    expect(favoriteNotificationCount($this->driver, $order->fresh(), '찜한 운행 배차 완료'))->toBe(1);
    expect(favoriteCount($this->driver->id, $order->id))->toBe(0);
});

test('운행이 취소되면 찜한 기사에게 알리고 찜이 정리된다', function () {
    $order = favoriteMarketOrder($this->owner->id);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/favorite")->assertOk();

    // 등록자가 취소
    Sanctum::actingAs($this->owner);
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_CANCELLED])->assertOk();

    expect(favoriteNotificationCount($this->driver, $order->fresh(), '찜한 운행 취소'))->toBe(1);
    expect(favoriteCount($this->driver->id, $order->id))->toBe(0);
});

test('관리자가 운행을 숨기면 찜한 기사에게 알리고 찜이 정리된다', function () {
    $order = favoriteMarketOrder($this->owner->id);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/favorite")->assertOk();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/hide", [
        'hidden' => true,
        'reason' => '중복 등록으로 숨김',
    ])->assertOk();

    expect(favoriteNotificationCount($this->driver, $order->fresh(), '찜한 운행 숨김'))->toBe(1);
    expect(favoriteCount($this->driver->id, $order->id))->toBe(0);
});

test('등록자가 조건을 바꾸면 찜한 기사에게 알리되 찜은 유지된다', function () {
    $order = favoriteMarketOrder($this->owner->id);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/favorite")->assertOk();

    // 등록자가 출발지를 변경 (노선 조건 변화)
    Sanctum::actingAs($this->owner);
    $this->patchJson("/api/orders/{$order->id}", [
        'pickup_location' => '김포공항 국제선',
    ])->assertOk();

    expect(favoriteNotificationCount($this->driver, $order->fresh(), '찜한 운행 조건 변경'))->toBe(1);

    // 찜은 그대로 유지
    expect(favoriteCount($this->driver->id, $order->id))->toBe(1);
});

test('마켓에 없는 운행(이미 가져감)은 찜할 수 없다', function () {
    $order = favoriteMarketOrder($this->owner->id);

    // 다른 기사에게 이미 가져가진 상태 (마켓에서 빠짐)
    $order->forceFill(['status' => Order::STATUS_ACCEPTED, 'user_id' => $this->otherDriver->id])->save();

    Sanctum::actingAs($this->driver);

    $this->postJson("/api/orders/{$order->id}/favorite")->assertStatus(409);
    expect(favoriteCount($this->driver->id, $order->id))->toBe(0);
});
