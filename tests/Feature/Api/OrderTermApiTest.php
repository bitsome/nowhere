<?php

use App\Models\Order;
use App\Models\OrderTerm;
use App\Models\User;
use App\Support\Orders\ChineseTextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 미매핑 용어 관리 — 사전에 없던 중국어 표기를 모아 관리자가 한국어로 매핑한다.
 */
function termTestRegistrant(): User
{
    // id=1 은 루트 특례(전체 권한)를 받는다 — 먼저 채워 실제 권한 검사를 받게 한다
    User::factory()->create();

    return User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'permissions' => ['order.create'],
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function termTestPayload(array $overrides = []): array
{
    return array_merge([
        'pickup_location' => '回击',
        'dropoff_location' => '机场',
        'service_type' => 'sending',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'expected_revenue' => 90000,
    ], $overrides);
}

test('관리자가 아니면 용어 목록을 볼 수 없다', function () {
    User::factory()->create();
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DRIVER, 'permissions' => []]));

    $this->getJson('/api/admin/order-terms')->assertForbidden();
});

test('사전에 없는 중국어 표기는 미매핑으로 쌓인다', function () {
    Sanctum::actingAs(termTestRegistrant());

    $this->postJson('/api/orders', termTestPayload())->assertCreated();

    $term = OrderTerm::query()->where('term', '回击')->firstOrFail();

    expect($term->field)->toBe(OrderTerm::FIELD_PICKUP)
        ->and($term->status)->toBe(OrderTerm::STATUS_PENDING)
        ->and($term->occurrences)->toBe(1)
        ->and($term->mapped_to)->toBeNull()
        // 내장 사전에 있는 값은 쌓이지 않는다
        ->and(OrderTerm::query()->where('term', '机场')->exists())->toBeFalse();
});

test('같은 표기가 다시 들어오면 발생 횟수가 늘어난다', function () {
    Sanctum::actingAs(termTestRegistrant());

    $this->postJson('/api/orders', termTestPayload())->assertCreated();
    $this->postJson('/api/orders', termTestPayload())->assertCreated();

    expect(OrderTerm::query()->where('term', '回击')->firstOrFail()->occurrences)->toBe(2)
        ->and(OrderTerm::query()->where('term', '回击')->count())->toBe(1);
});

test('관리자가 매핑하면 목록에 매핑 완료로 보인다', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $term = OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '回击',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 3,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/order-terms?status=pending')
        ->assertOk()
        ->assertJsonPath('data.0.term', '回击')
        ->assertJsonPath('data.0.status_label', '미매핑')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.fields.'.OrderTerm::FIELD_PICKUP, '출발지');

    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'mapped', 'mapped_to' => '회기'])
        ->assertOk()
        ->assertJsonPath('data.mapped_to', '회기')
        ->assertJsonPath('data.status_label', '매핑 완료')
        ->assertJsonPath('data.mapped_by_name', $admin->name);

    expect($term->fresh()->mapped_at)->not->toBeNull();
});

test('매핑 후 들어온 값은 사전을 타고 한국어로 저장된다', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $registrant = termTestRegistrant();

    Sanctum::actingAs($registrant);

    $this->postJson('/api/orders', termTestPayload())->assertCreated();

    $term = OrderTerm::query()->where('term', '回击')->firstOrFail();

    Sanctum::actingAs($admin);
    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'mapped', 'mapped_to' => '회기'])
        ->assertOk();

    Sanctum::actingAs($registrant);
    $this->postJson('/api/orders', termTestPayload())->assertCreated();

    expect(Order::query()->orderByDesc('id')->firstOrFail()->pickup_location)->toBe('회기');
});

test('매핑하면 이미 저장된 운행도 함께 바뀐다', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $registrant = termTestRegistrant();

    Sanctum::actingAs($registrant);
    $this->postJson('/api/orders', termTestPayload())->assertCreated();

    $order = Order::query()->firstOrFail();
    $term = OrderTerm::query()->where('term', '回击')->firstOrFail();

    expect($order->pickup_location)->toBe('回击');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'mapped', 'mapped_to' => '회기'])
        ->assertOk();

    expect($order->fresh()->pickup_location)->toBe('회기');
});

test('무시하면 매핑값 없이 무시 상태가 된다', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $term = OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_DROPOFF,
        'term' => '回击',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 1,
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'ignored'])
        ->assertOk()
        ->assertJsonPath('data.status_label', '무시')
        ->assertJsonPath('data.mapped_to', null);
});

test('매핑 완료로 바꾸면서 한국어를 비우면 검증에 실패한다', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $term = OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '回击',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 1,
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'mapped'])
        ->assertStatus(422);

    expect($term->fresh()->status)->toBe(OrderTerm::STATUS_PENDING);
});

test('관리자가 아니면 용어를 직접 등록할 수 없다', function () {
    User::factory()->create();
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DRIVER, 'permissions' => []]));

    $this->postJson('/api/admin/order-terms', [
        'field' => OrderTerm::FIELD_DROPOFF,
        'term' => '新村',
        'mapped_to' => '신촌',
    ])->assertForbidden();
});

test('직접 등록한 용어는 사전에 들어가 다음 유입부터 적용된다', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->postJson('/api/admin/order-terms', [
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '之后',
        'mapped_to' => '이후',
    ])
        ->assertCreated()
        ->assertJsonPath('data.term', '之后')
        ->assertJsonPath('data.mapped_to', '이후')
        ->assertJsonPath('data.status_label', '매핑 완료')
        // 아직 유입되지 않은 용어라 관측 횟수는 0이다
        ->assertJsonPath('data.occurrences', 0)
        ->assertJsonPath('meta.created', true);

    Sanctum::actingAs(termTestRegistrant());
    $this->postJson('/api/orders', termTestPayload(['pickup_location' => '之后']))->assertCreated();

    expect(Order::query()->orderByDesc('id')->firstOrFail()->pickup_location)->toBe('이후');
});

test('직접 등록한 용어는 이미 저장된 운행에도 함께 반영된다', function () {
    // 수집기를 거치지 않고 저장된 운행 — 사전 도입 전에 쌓여 있던 값과 같은 상황
    $order = Order::factory()->create(['dropoff_location' => '新村']);

    expect(OrderTerm::query()->where('term', '新村')->exists())->toBeFalse();

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->postJson('/api/admin/order-terms', [
        'field' => OrderTerm::FIELD_DROPOFF,
        'term' => '新村',
        'mapped_to' => '신촌',
    ])->assertCreated();

    expect($order->fresh()->dropoff_location)->toBe('신촌');
});

test('이미 있는 용어를 다시 등록하면 매핑값만 바뀐다', function () {
    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '回击',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 4,
    ]);

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->postJson('/api/admin/order-terms', [
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '回击',
        'mapped_to' => '회기',
    ])
        ->assertOk()
        ->assertJsonPath('data.mapped_to', '회기')
        ->assertJsonPath('meta.created', false);

    $term = OrderTerm::query()->where('term', '回击')->firstOrFail();

    expect(OrderTerm::query()->where('term', '回击')->count())->toBe(1)
        // 유입 이력은 매핑과 별개이므로 관측 횟수는 유지된다
        ->and($term->occurrences)->toBe(4);
});

test('용어를 한국어 없이 등록하면 검증에 실패한다', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->postJson('/api/admin/order-terms', [
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '回击',
    ])->assertStatus(422);

    expect(OrderTerm::query()->count())->toBe(0);
});

test('태그 분야가 용어 목록 meta에 포함된다', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->getJson('/api/admin/order-terms')
        ->assertOk()
        ->assertJsonPath('meta.fields.'.OrderTerm::FIELD_TAG, '태그');
});

test('태그로 매핑한 표기는 신규 운행에 태그로 붙는다', function () {
    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_TAG,
        'term' => '帮划客路',
        'mapped_to' => '클록스텝진행',
        'status' => OrderTerm::STATUS_MAPPED,
    ]);
    ChineseTextNormalizer::forgetCache();

    Sanctum::actingAs(termTestRegistrant());

    $this->postJson('/api/orders', termTestPayload(['pickup_location' => '帮划客路']))->assertCreated();

    $order = Order::query()->orderByDesc('id')->firstOrFail();

    expect($order->tags)->toContain('클록스텝진행')
        // 지명이 아니므로 값은 그대로 두고 태그로만 옮긴다
        ->and($order->pickup_location)->toBe('帮划客路');
});

test('태그로 매핑하면 이미 저장된 운행에도 태그가 붙는다', function () {
    $order = Order::factory()->create([
        'pickup_location' => '帮划客路',
        'tags' => ['wechat-monitor'],
    ]);

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->postJson('/api/admin/order-terms', [
        'field' => OrderTerm::FIELD_TAG,
        'term' => '帮划客路',
        'mapped_to' => '클록스텝진행',
    ])->assertCreated();

    expect($order->fresh()->tags)->toBe(['클록스텝진행'])
        ->and($order->fresh()->pickup_location)->toBe('帮划客路');
});

test('태그로 등록한 표기는 미매핑으로 다시 쌓이지 않는다', function () {
    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_TAG,
        'term' => '帮划客路',
        'mapped_to' => '클록스텝진행',
        'status' => OrderTerm::STATUS_MAPPED,
    ]);
    ChineseTextNormalizer::forgetCache();

    Sanctum::actingAs(termTestRegistrant());

    $this->postJson('/api/orders', termTestPayload(['pickup_location' => '帮划客路']))->assertCreated();

    expect(OrderTerm::query()
        ->where('term', '帮划客路')
        ->where('status', OrderTerm::STATUS_PENDING)
        ->exists())->toBeFalse();
});

test('차량 용어를 매핑하면 기존 운행의 차종도 바뀐다', function () {
    // 일정(order_line_items)에는 차종 열이 없다 — 운행만 갱신해야 한다
    $order = Order::factory()->create(['vehicle_type' => '利亚9']);

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $term = OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_VEHICLE,
        'term' => '利亚9',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 1,
    ]);

    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'mapped', 'mapped_to' => '스타리아 9인승'])
        ->assertOk();

    expect($order->fresh()->vehicle_type)->toBe('스타리아 9인승');
});

test('한 번만 나온 표기는 기본 목록에서 숨긴다', function () {
    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '회기',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 1,
    ]);
    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_DROPOFF,
        'term' => '성수',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 2,
    ]);

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->getJson('/api/admin/order-terms?status=pending')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.term', '성수');

    $this->getJson('/api/admin/order-terms?status=pending&min_occurrences=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

test('문장부호나 이모지가 섞인 값은 미매핑으로 쌓지 않는다', function () {
    Sanctum::actingAs(termTestRegistrant());

    $this->postJson('/api/orders', termTestPayload([
        'pickup_location' => '明洞来个车…',
        'dropoff_location' => '半斤面粉🇰🇷',
    ]))->assertCreated();

    expect(OrderTerm::query()->where('term', '明洞来个车…')->exists())->toBeFalse()
        ->and(OrderTerm::query()->where('term', '半斤面粉🇰🇷')->exists())->toBeFalse();
});

test('차종 분야가 용어 목록 meta에 포함된다', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->getJson('/api/admin/order-terms')
        ->assertOk()
        ->assertJsonPath('meta.fields.'.OrderTerm::FIELD_MODEL, '차종');
});

test('차종 용어를 매핑하면 기존 운행의 차종도 바뀐다', function () {
    $order = Order::factory()->create(['vehicle_type' => '利亚']);

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $term = OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_MODEL,
        'term' => '利亚',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 1,
    ]);

    $this->patchJson("/api/admin/order-terms/{$term->id}", ['status' => 'mapped', 'mapped_to' => '스타리아'])
        ->assertOk();

    expect($order->fresh()->vehicle_type)->toBe('스타리아');
});
