<?php

use App\Models\User;
use App\Services\OrderSummaryAiStructurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * AI 구조화 폴백 — 중국 본토 서버에서 OpenAI가 차단되어도 위챗 문구를 등록할 수 있어야 한다.
 *
 * 로컬 규칙 파서의 정확도는 AI보다 낮으므로, 여기서 고정하는 것은 "정형화된 위챗 문구를
 * 사람이 확인·수정할 수 있는 수준까지는 뽑아낸다"는 계약이다.
 */
function structureOffline(string $summary): array
{
    Http::fake(fn () => throw new ConnectionException('연결 차단'));

    return app(OrderSummaryAiStructurer::class)->structure($summary);
}

test('AI가 차단되면 규칙 파서로 대체한다', function () {
    $result = structureOffline('3.30送机 蚕室 3人 2行李 9万');

    expect($result['parsed_by'])->toBe('local')
        ->and($result['service_time'])->toBe('03:30')
        // "3.30"은 시각 표기다 — 날짜(3월 30일)로 잘못 채우지 않는다
        ->and($result['service_date'])->toBe('')
        ->and($result['service_type'])->toBe('샌딩')
        ->and($result['pickup_location'])->toBe('잠실')
        // 샌딩은 도착지가 없어도 공항으로 간다
        ->and($result['dropoff_location'])->toBe('인천')
        ->and($result['passenger_count'])->toBe(3)
        ->and($result['luggage_count'])->toBe(2)
        ->and($result['amount_text'])->toBe('9만')
        ->and($result['amount_value'])->toBe(90000);
});

test('한 줄에 붙은 여러 운행을 일정으로 나눈다', function () {
    $result = structureOffline('3号 卡起 03:00 送机 麻浦区 1人 07:00 送机 明洞 4人');

    expect($result['service_date'])->toBe(now()->month.'월3일')
        // 卡起 → 카니발부터 가능 (차량 요구 조건)
        ->and($result['vehicle_type'])->toBe('카니발부터 가능')
        ->and($result['group_type'])->toBe('셋트')
        ->and($result['line_items'])->toHaveCount(2)
        ->and($result['line_items'][0]['scheduled_time'])->toBe('03:00')
        ->and($result['line_items'][0]['pickup_location'])->toBe('마포구')
        ->and($result['line_items'][0]['passenger_count'])->toBe(1)
        // 문구에 한 번만 나온 날짜는 일정에도 함께 채운다
        ->and($result['line_items'][0]['service_date'])->toBe(now()->month.'월3일')
        ->and($result['line_items'][1]['scheduled_time'])->toBe('07:00')
        ->and($result['line_items'][1]['pickup_location'])->toBe('명동')
        ->and($result['line_items'][1]['passenger_count'])->toBe(4);
});

test('일정마다 금액을 따로 해석한다', function () {
    // 여러 건을 나눠 등록할 때 각 운행 금액이 제값을 가져야 한다
    $result = structureOffline('3号 03:00 送机 마포구 1人 8万 07:00 送机 명동 4人 12万');

    expect($result['line_items'])->toHaveCount(2)
        ->and($result['line_items'][0]['amount_text'])->toBe('8만')
        ->and($result['line_items'][0]['amount_value'])->toBe(80000)
        ->and($result['line_items'][1]['amount_text'])->toBe('12만')
        ->and($result['line_items'][1]['amount_value'])->toBe(120000);
});

test('노선 표기를 출발지·도착지로 나눈다', function () {
    $result = structureOffline('明洞—仁川 3人 9万');

    expect($result['pickup_location'])->toBe('명동')
        ->and($result['dropoff_location'])->toBe('인천')
        ->and($result['passenger_count'])->toBe(3);
});

test('接机는 공항에서 지명으로 가는 픽업으로 해석한다', function () {
    $result = structureOffline('4号 14:00 接机 弘大 2人');

    expect($result['service_type'])->toBe('픽업')
        ->and($result['service_time'])->toBe('14:00')
        ->and($result['pickup_location'])->toBe('인천')
        ->and($result['dropoff_location'])->toBe('홍대');
});

test('차량·묶음 키워드만 있는 문구도 날짜와 셋트로 해석한다', function () {
    $result = structureOffline('2号 一起出 카니발');

    expect($result['service_date'])->toBe(now()->month.'월2일')
        ->and($result['group_type'])->toBe('셋트')
        ->and($result['vehicle_type'])->toBe('카니발');
});

test('套·套出 표기도 셋트로 해석하고 금액과 섞이지 않는다', function () {
    $result = structureOffline('2号 套 카니발 9万');

    expect($result['group_type'])->toBe('셋트')
        ->and($result['vehicle_type'])->toBe('카니발')
        ->and($result['amount_text'])->toBe('9만')
        ->and($result['amount_value'])->toBe(90000);

    // 긴 표기를 먼저 바꾼다 — '셋트出' 같은 잔여가 남으면 안 된다
    $legacy = structureOffline('2号 套出 카니발 9万');

    expect($legacy['group_type'])->toBe('셋트')
        ->and($legacy['amount_text'])->toBe('9만');
});

test('날짜 표기 9.14号 를 시각으로 읽지 않는다', function () {
    // 9.14号 는 9월 14일이다. 이걸 시각(09:14)으로 잡으면 문구 전체의 시간이 덮여
    // 실제 운행 시각(07:30)이 사라진다.
    $result = structureOffline('9.14号订单 07:30 送机 东大门');

    expect($result['service_date'])->toBe('9월14일')
        ->and($result['service_time'])->toBe('07:30');
});

test('接机 뒤 편명은 지명으로 잡지 않는다', function () {
    // `接机oz111有要的没` 의 OZ111 은 편명이다. 앞 두 글자 oz 를 도착지로 잡으면
    // 지명이 아닌 값이 마켓에 뜬다. 다만 接机(공항 픽업)의 '출발지=공항'은 그대로다.
    $result = structureOffline('今天12点接机oz111有要的没');

    expect($result['service_time'])->toBe('12:00')
        ->and($result['pickup_location'])->toBe('인천')
        ->and($result['dropoff_location'])->toBe('');
});

test('送机 뒤 인원 단위는 지명으로 잡지 않는다', function () {
    // `送机 一位` 의 一位 는 '1명'이다 — 지명으로 잡으면 출발지가 '一位' 가 된다
    $result = structureOffline('07:00 送机 一位');

    expect($result['pickup_location'])->toBe('')
        ->and($result['dropoff_location'])->toBe('');
});

test('물결(～)도 노선 구분자로 읽는다', function () {
    // `Coex～江南Voco` 는 Coex → 江南Voco 노선이다
    $result = structureOffline('19:30 Coex～江南Voco 3人');

    expect($result['service_time'])->toBe('19:30')
        ->and($result['pickup_location'])->toBe('Coex')
        ->and($result['dropoff_location'])->toBe('江南Voco')
        ->and($result['passenger_count'])->toBe(3);
});

test('구분자만 남은 값은 도착지로 잡지 않는다', function () {
    // `出发～～` 의 물결이 도착지로 들어온 건이 있었다
    $result = structureOffline('grand 洲际17:00 出发～～');

    expect($result['service_time'])->toBe('17:00')
        ->and($result['dropoff_location'])->toBe('');
});

test('接机 뒤 한 글자 잡음은 지명으로 잡지 않는다', function () {
    // `明天11:35接机 话 中午13点左右有送机` 의 `话` 가 도착지로 들어온 건이 있었다
    $result = structureOffline('明天11:35接机 话 中午13点左右有送机');

    expect($result['service_time'])->toBe('11:35')
        ->and($result['pickup_location'])->toBe('인천')
        ->and($result['dropoff_location'])->toBe('');
});

test('AI가 정상 응답하면 그대로 쓰고 폴백하지 않는다', function () {
    // AI 설정이 비어 있으면 요청을 보내지 않고 로컬 파서로 떨어진다 — 이 테스트는 "AI 경로가
    // 켜져 있을 때"의 계약이므로, 로컬 .env(ORDER_AI_API_KEY)에 기대지 않고 설정을 직접 채운다.
    config(['services.order_ai.api_key' => 'test-key']);

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'request_label' => 'AI 결과',
                'service_date' => '8월 10일',
                'service_type' => '픽업',
                'pickup_location' => '서울 강남구',
                'dropoff_location' => '강릉 정동진',
            ], JSON_UNESCAPED_UNICODE)]]],
        ]),
    ]);

    $result = app(OrderSummaryAiStructurer::class)->structure('8월 10일 오전 9시 강남 픽업');

    expect($result['parsed_by'])->toBe('ai')
        ->and($result['pickup_location'])->toBe('서울 강남구')
        ->and($result['dropoff_location'])->toBe('강릉 정동진');
});

test('구조화 API는 AI가 닿지 않아도 200과 함께 해석 경로를 내려준다', function () {
    Http::fake(fn () => throw new ConnectionException('연결 차단'));

    Sanctum::actingAs(User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'permissions' => ['order.create'],
    ]));

    $this->postJson('/api/orders/structure', ['summary' => '3.30送机 蚕室 3人'])
        ->assertOk()
        ->assertJsonPath('data.structured.parsed_by', 'local')
        ->assertJsonPath('data.structured.service_type', '샌딩')
        ->assertJsonPath('data.structured.pickup_location', '잠실');
});
