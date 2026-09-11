<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->registrant = User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'company_name' => '노웨어투어',
    ]);

    $this->stranger = User::factory()->create([
        'role' => User::ROLE_DRIVER,
    ]);
});

/**
 * 공유 가능한(마켓 공개) 운행을 만든다.
 */
function sharedOrder(User $owner, array $attributes = []): Order
{
    return Order::factory()->create([
        'user_id' => $owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
        'customer_name' => '홍길동',
        'customer_phone' => '010-1234-5678',
        ...$attributes,
    ]);
}

test('registrant can issue a share link for their own order', function () {
    $order = sharedOrder($this->registrant);

    Sanctum::actingAs($this->registrant);

    $response = $this->postJson("/api/orders/{$order->id}/share")
        ->assertOk()
        ->assertJsonPath('data.token', fn ($token) => is_string($token) && strlen($token) === 16);

    $token = $response->json('data.token');

    expect($response->json('data.path'))->toBe("/s/order/{$token}");
    expect($order->fresh()->share_token)->toBe($token);
});

test('share token is stable across repeated requests', function () {
    $order = sharedOrder($this->registrant);

    Sanctum::actingAs($this->registrant);

    $first = $this->postJson("/api/orders/{$order->id}/share")->json('data.token');
    $second = $this->postJson("/api/orders/{$order->id}/share")->json('data.token');

    expect($second)->toBe($first);
});

test('a user who does not own the order cannot issue a share link', function () {
    $order = sharedOrder($this->registrant);

    Sanctum::actingAs($this->stranger);

    $this->postJson("/api/orders/{$order->id}/share")->assertForbidden();

    expect($order->fresh()->share_token)->toBeNull();
});

test('a shared order is viewable without logging in', function () {
    $order = sharedOrder($this->registrant, ['share_token' => 'abc123abc123abcd']);

    $this->getJson('/api/public/orders/abc123abc123abcd')
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.row.route', '인천공항 T1 → 강남')
        ->assertJsonPath('data.registrant_company', '노웨어투어');
});

test('the public payload never exposes customer identity or internal identifiers', function () {
    $order = sharedOrder($this->registrant, ['share_token' => 'secret00000000ab']);

    $response = $this->getJson('/api/public/orders/secret00000000ab')->assertOk();

    $row = $response->json('data.row');

    // 고객 실명·연락처, 운행번호, 내부 사용자 id는 공개 화면에 내려가지 않는다
    expect($row)->not->toHaveKey('customerName');
    expect($row)->not->toHaveKey('orderNumber');
    expect($row)->not->toHaveKey('userId');
    expect($row)->not->toHaveKey('claimantName');

    expect($response->json())->not->toContain('홍길동');
    expect($response->json())->not->toContain('010-1234-5678');
});

test('an order that is no longer available is reported as closed', function () {
    sharedOrder($this->registrant, [
        'share_token' => 'closed000000abcd',
        'status' => Order::STATUS_ACCEPTED,
    ]);

    $this->getJson('/api/public/orders/closed000000abcd')
        ->assertOk()
        ->assertJsonPath('data.available', false);
});

test('an unknown share token returns not found', function () {
    $this->getJson('/api/public/orders/doesnotexist0000')
        ->assertNotFound()
        ->assertJsonPath('data', null);
});

test('the share link page renders preview metadata for the order', function () {
    sharedOrder($this->registrant, ['share_token' => 'ogogogogogog0000']);

    $response = $this->get('/s/order/ogogogogogog0000')
        ->assertOk()
        ->assertSee('인천공항 T1 → 강남', false)
        ->assertSee('og:image', false)
        ->assertSee('/og-cover.jpg', false)
        // 사람은 SPA 공개 화면으로 넘어간다
        ->assertSee('/share/order/ogogogogogog0000', false);
});
