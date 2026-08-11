<?php

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Database\Seeders\OrderDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('nowhere dashboard module page links to order skeleton page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.nowhere'))
        ->assertSuccessful()
        ->assertSee('오더 관리')
        ->assertSee('골격 보기')
        ->assertSee(route('dashboard.business.order'), false);
});

test('order dashboard workspace renders filter bar and single order cards', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    Order::factory()->create([
        'customer_name' => '홍길동',
        'reservation_company' => 'KLOOK',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-08-10',
        'service_time' => '09:00',
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'expected_revenue' => 120000,
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order'))
        ->assertSuccessful()
        ->assertSee('오더 워크스페이스')
        ->assertSee('오더 등록')
        ->assertSee('오더 목록')
        ->assertSee('"orderNumber":', false)
        ->assertSee('"pickupDateTime":', false)
        ->assertSee('픽업')
        ->assertSee('09:00')
        ->assertSee('인천공항 T1 → 명동')
        ->assertSee('120,000원')
        ->assertSee('공개')
        ->assertSee('카니발')
        ->assertSee('선행 참조 데이터')
        ->assertSee('Business Foundation 연결')
        ->assertSee('data-dashboard-order-list', false)
        ->assertSee('data-dashboard-order-skeleton', false)
        ->assertSee('data-order-datatable', false);
});

test('order dashboard workspace renders set groups with grouped order json rows', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $group = OrderGroup::factory()->create([
        'name' => 'KLOOK 셋트',
    ]);

    Order::factory()->create([
        'customer_name' => '이순신',
        'group_id' => $group->id,
        'reservation_company' => 'KLOOK',
        'vehicle_type' => '스타리아 9인승',
        'service_type' => 'pickup',
        'service_date' => '2026-08-10',
        'service_time' => '09:00',
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '이순신',
        'group_id' => $group->id,
        'reservation_company' => 'KLOOK',
        'vehicle_type' => '스타리아 9인승',
        'service_type' => 'sending',
        'service_date' => '2026-08-12',
        'service_time' => '17:00',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order'))
        ->assertSuccessful()
        ->assertSee('픽업')
        ->assertSee('공항샌딩')
        ->assertSee('KLOOK 셋트')
        ->assertSee('스타리아 9인승')
        ->assertSee('data-order-rows', false)
        ->assertSee('"pickupDateTime":', false)
        ->assertSee('"routes":', false)
        ->assertSee('"totalAmount":', false)
        ->assertSee('showUrl', false)
        ->assertSee('vehicle', false);
});

test('order dashboard workspace mixes set and single rows by schedule order', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $group = OrderGroup::factory()->create([
        'name' => '중간 셋트',
    ]);

    Order::factory()->create([
        'customer_name' => '앞단일',
        'reservation_company' => '직접예약',
        'service_date' => '2026-08-03',
        'service_time' => '08:00',
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '가운데셋트',
        'group_id' => $group->id,
        'reservation_company' => 'KLOOK',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-08-03',
        'service_time' => '10:00',
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '뒤단일',
        'reservation_company' => 'KKDAY',
        'service_date' => '2026-08-03',
        'service_time' => '12:00',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.business.order'));

    $response->assertSuccessful();

    $content = $response->getContent();

    expect($content)->not->toBeFalse();
    expect(strpos($content, 'order-1'))->toBeLessThan(strpos($content, 'set-'.$group->id));
    expect(strpos($content, 'set-'.$group->id))->toBeLessThan(strpos($content, 'order-3'));
});

test('order dashboard workspace filters orders by search input above list', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    Order::factory()->create([
        'customer_name' => '홍길동',
        'pickup_location' => '서울역',
        'dropoff_location' => '명동',
        'reservation_company' => 'KLOOK',
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '김철수',
        'pickup_location' => '부산역',
        'dropoff_location' => '해운대',
        'reservation_company' => 'KKDAY',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order', ['search' => '서울역']))
        ->assertSuccessful()
        ->assertSee('서울역 → 명동')
        ->assertDontSee('부산역 → 해운대');
});

test('order demo seeder creates expanded fake reservation data for order workspace', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->seed(OrderDemoSeeder::class);

    expect(Order::query()->count())->toBe(72);
    expect(OrderGroup::query()->count())->toBe(3);
    expect(Order::query()->whereNull('group_id')->count())->toBe(63);
    expect(Order::query()->whereNotNull('group_id')->count())->toBe(9);

    $singleOrder = Order::query()
        ->where('customer_name', '김도윤')
        ->first();

    expect($singleOrder)->not->toBeNull();
    expect($singleOrder?->group_id)->toBeNull();
    expect($singleOrder?->reservation_company)->toBe('직접예약');
    expect($singleOrder?->vehicle_type)->toBe('카니발');
    expect($singleOrder?->lineItems()->count())->toBe(1);

    $setOrder = Order::query()
        ->where('customer_name', '장현서')
        ->first();

    expect($setOrder)->not->toBeNull();
    expect($setOrder?->group_type)->toBe('셋트');
    expect($setOrder?->vehicle_type)->toBe('스타리아 11인승');
    expect($setOrder?->pickup_location)->toBe('인천공항 T2');
    expect($setOrder?->dropoff_location)->toBe('서울 서초구');
    expect($setOrder?->lineItems()->count())->toBe(1);
    expect($setOrder?->expected_revenue)->toBe(30000);

    $this->actingAs($user)
        ->get(route('dashboard.business.order'))
        ->assertSuccessful()
        ->assertSee('인천공항 T1 → 서울 강남구')
        ->assertSee('18,000원')
        ->assertSee('8월 7일 비즈니스 셋트')
        ->assertSee('카니발')
        ->assertSee('스타리아 11인승')
        ->assertSee('87,000원');
});

test('order workspace list builder produces the shared data contract for backend and frontend', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $group = OrderGroup::factory()->create([
        'name' => '공용 계약 셋트',
    ]);

    $single = Order::factory()->create([
        'customer_name' => '계약단일',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-08-03',
        'service_time' => '08:00',
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'flight_number' => 'KE999',
        'expected_revenue' => 120000,
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $user->id,
    ]);

    $setOrder = Order::factory()->create([
        'customer_name' => '계약셋트',
        'group_id' => $group->id,
        'vehicle_type' => '스타리아 9인승',
        'service_type' => 'mixed',
        'service_date' => '2026-08-04',
        'service_time' => '10:00',
        'pickup_location' => '서울 중구',
        'dropoff_location' => '김포공항',
        'expected_revenue' => 64000,
        'status' => Order::STATUS_ACCEPTED,
        'user_id' => $user->id,
    ]);

    $builder = app(OrderWorkspaceListBuilder::class);
    $rows = $builder->build(Order::query()->get());

    expect($rows)->toHaveCount(2);

    $singleRow = collect($rows)->firstWhere('kind', 'single');
    $setRow = collect($rows)->firstWhere('kind', 'set');

    expect($singleRow)->toMatchArray([
        'key' => 'order-'.$single->id,
        'kind' => 'single',
        'id' => $single->id,
        'orderNumber' => $single->order_number,
        'serviceIcon' => 'pickup',
        'serviceLabel' => '픽업',
        'vehicle' => '카니발',
        'flightNumber' => 'KE999',
        'passengerCount' => $single->passenger_count,
        'date' => '8/3(월)',
        'time' => '08:00',
        'pickupDateTime' => '8/3(월) 08:00',
        'route' => '인천공항 T1 → 명동',
        'amount' => '120,000원',
        'statusLabel' => '공개',
    ]);
    expect($singleRow['showUrl'])->toContain('/dashboard/business/order/'.$single->id);

    expect($setRow)->toMatchArray([
        'key' => 'set-'.$group->id,
        'kind' => 'set',
        'id' => $group->id,
        'name' => '공용 계약 셋트',
        'count' => 1,
        'statusLabel' => '수락',
        'routes' => [[
            'route' => '서울 중구 → 김포공항',
            'time' => '10:00',
            'date' => '8/4(화)',
            'serviceLabel' => '-',
            'id' => $setOrder->id,
            'vehicle' => '스타리아 9인승',
            'passengerCount' => $setOrder->passenger_count,
        ]],
        'pickupDateTime' => '8/4(화) 10:00',
        'totalAmount' => '64,000원',
    ]);
    expect($setRow['orders'][0]['vehicle'])->toBe('스타리아 9인승');
    expect($setRow['orders'][0]['statusLabel'])->toBe('수락');
    expect($setRow['showUrl'])->toContain('/dashboard/business/order/'.$setOrder->id);
});
