<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('users with order create permission can access order create page', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.create'))
        ->assertSuccessful()
        ->assertSee('예약 등록')
        ->assertSee('AI 구조화')
        ->assertSee('오더 요약 입력')
        ->assertSee('오더 요약을 입력하면 AI가 출발지, 도착지, 시간, 항공편, 차량 형태와 다중 일정까지 구조화합니다.')
        ->assertSee('운행 요약')
        ->assertSee('서울 강남구 - 인천공항 08:42')
        ->assertDontSee('예약업체')
        ->assertDontSee('고객명')
        ->assertDontSee('오더 유형')
        ->assertDontSee('예약 채널')
        ->assertSee('출발지')
        ->assertSee('도착지')
        ->assertSee('항공편')
        ->assertSee('픽업 일시')
        ->assertSee('금액')
        ->assertSee('인원 수')
        ->assertSee('예약 상태')
        ->assertSee('data-order-create-summary', false)
        ->assertSee('data-order-create-page', false)
        ->assertSee('data-order-line-items-editor', false)
        ->assertSee('data-line-items-add', false)
        ->assertSee('data-line-items-remove', false)
        ->assertSee('data-order-line-items-kind', false)
        ->assertSee('예약 구분')
        ->assertSee('data-order-group-type', false)
        ->assertSee('data-order-ai-group-type', false)
        ->assertSee('data-order-group-type-submit', false)
        ->assertDontSee('AI 반환값')
        ->assertDontSee('AI 전체 반환 데이터');
});

test('users can structure order summary through ai api endpoint', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '오늘',
                            'vehicle_type' => '카니발',
                            'service_type' => '혼합',
                            'passenger_count' => 4,
                            'pickup_location' => '강남구',
                            'dropoff_location' => '인천공항',
                            'scheduled_time' => '08:42',
                            'order_type' => '공항 오더',
                            'flight_number' => 'KE123',
                            'amount_text' => '13만',
                            'amount_value' => 130000,
                            'line_items' => [
                                [
                                    'scheduled_time' => '15:20',
                                    'service_type' => '샌딩',
                                    'location' => '강남구',
                                    'passenger_count' => 4,
                                ],
                                [
                                    'scheduled_time' => '17:25',
                                    'service_type' => '픽업',
                                    'location' => '종로구',
                                    'passenger_count' => 2,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '서울 강남구 - 인천공항 08:42 · 일반 오더 KE123',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '오늘')
        ->assertJsonPath('structured.vehicle_type', '카니발')
        ->assertJsonPath('structured.service_type', '혼합')
        ->assertJsonPath('structured.passenger_count', 4)
        ->assertJsonPath('structured.pickup_location', '강남구')
        ->assertJsonPath('structured.dropoff_location', '인천공항')
        ->assertJsonPath('structured.scheduled_time', '08:42')
        ->assertJsonPath('structured.order_type', '공항 오더')
        ->assertJsonPath('structured.flight_number', 'KE123')
        ->assertJsonPath('structured.amount_text', '13만')
        ->assertJsonPath('structured.amount_value', 130000)
        ->assertJsonPath('structured.line_items.0.scheduled_time', '15:20')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.location', '강남구—인천')
        ->assertJsonPath('structured.line_items.0.passenger_count', 4)
        ->assertJsonPath('structured.line_items.1.scheduled_time', '17:25')
        ->assertJsonPath('structured.line_items.1.service_type', '픽업')
        ->assertJsonPath('structured.line_items.1.location', '종로구')
        ->assertJsonPath('structured.line_items.1.passenger_count', 2);
});

test('order summary ai endpoint normalizes grouped departure label meaning', function () {
    $expectedRequestLabel = now()->month.'월2일 셋트';
    $expectedServiceDate = now()->month.'월2일';
    $expectedServiceWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->setMonth(now()->month)->setDay(2)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '2号 一起出',
                            'vehicle_type' => '新卡起',
                            'service_type' => 'mixed',
                            'passenger_count' => 4,
                            'pickup_location' => '江南区',
                            'dropoff_location' => '',
                            'scheduled_time' => '15:20',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'line_items' => [
                                [
                                    'scheduled_time' => '15:20',
                                    'service_type' => 'sending',
                                    'location' => '江南区',
                                    'passenger_count' => 4,
                                ],
                                [
                                    'scheduled_time' => '17:25',
                                    'service_type' => 'pickup',
                                    'location' => '钟路区',
                                    'passenger_count' => 2,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "2号 一起出\n15:20 送机 江南区 4人\n17:25 接机 钟路区 2人",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', $expectedRequestLabel)
        ->assertJsonPath('structured.service_date', $expectedServiceDate)
        ->assertJsonPath('structured.service_weekday', $expectedServiceWeekday)
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.vehicle_type', '더뉴카니발 4세대')
        ->assertJsonPath('structured.service_type', '혼합')
        ->assertJsonPath('structured.pickup_location', '강남구')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.1.service_type', '픽업');
});

test('order summary ai endpoint normalizes extra options and separated date fields', function () {
    $augustSecondWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(2)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '8.2号',
                            'vehicle_type' => '',
                            'service_type' => 'pickup',
                            'passenger_count' => null,
                            'pickup_location' => '客路端',
                            'dropoff_location' => '',
                            'scheduled_time' => '08:00',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '3🌾',
                            'amount_value' => '3🌾',
                            'extra_options' => [],
                            'line_items' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '8.2号 客路端 픽업 3🌾 不加不聊 秒结',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일')
        ->assertJsonPath('structured.service_date', '8월2일')
        ->assertJsonPath('structured.service_month', '8')
        ->assertJsonPath('structured.service_day', '2')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.service_time', '08:00')
        ->assertJsonPath('structured.extra_options', ['추가 연락 없음', '즉시 정산'])
        ->assertJsonPath('structured.pickup_location', '클록')
        ->assertJsonPath('structured.amount_text', '3만')
        ->assertJsonPath('structured.amount_value', 30000);
});

test('order summary ai endpoint normalizes passenger luggage shorthand and bare dates', function () {
    $augustSecondWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(2)->format('w')];
    $augustThirdWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(3)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '8.2',
                            'vehicle_type' => '',
                            'service_type' => 'pickup',
                            'passenger_count' => '3+3',
                            'pickup_location' => '仁川',
                            'dropoff_location' => '明洞',
                            'scheduled_time' => '18:55',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'service_date' => '8.2',
                                    'scheduled_time' => '18:55',
                                    'service_type' => 'pickup',
                                    'location' => '明洞',
                                    'passenger_count' => '3+3',
                                ],
                                [
                                    'service_date' => '8.3',
                                    'scheduled_time' => '20:45',
                                    'service_type' => 'pickup',
                                    'location' => '明洞',
                                    'passenger_count' => '4+4',
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "8.2 18:55仁川—明洞 3+3\n8.3 20:45仁川—明洞 4+4",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일')
        ->assertJsonPath('structured.service_date', '8월2일')
        ->assertJsonPath('structured.service_month', '8')
        ->assertJsonPath('structured.service_day', '2')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.passenger_count', 3)
        ->assertJsonPath('structured.luggage_count', 3)
        ->assertJsonPath('structured.pickup_location', '인천')
        ->assertJsonPath('structured.dropoff_location', '명동')
        ->assertJsonPath('structured.line_items.0.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.line_items.0.passenger_count', 3)
        ->assertJsonPath('structured.line_items.0.luggage_count', 3)
        ->assertJsonPath('structured.line_items.1.service_month', '8')
        ->assertJsonPath('structured.line_items.1.service_day', '3')
        ->assertJsonPath('structured.line_items.1.service_weekday', $augustThirdWeekday)
        ->assertJsonPath('structured.line_items.1.passenger_count', 4)
        ->assertJsonPath('structured.line_items.1.luggage_count', 4);
});

test('order summary ai endpoint interprets set and vehicle shorthand', function () {
    $augustSecondWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(2)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    $rawStructure = function (string $requestLabel, string $serviceDate, string $vehicleType): array {
        return [
            'request_label' => $requestLabel,
            'service_date' => $serviceDate,
            'vehicle_type' => $vehicleType,
            'service_type' => '',
            'passenger_count' => null,
            'pickup_location' => '',
            'dropoff_location' => '',
            'scheduled_time' => '',
            'order_type' => 'airport',
            'flight_number' => '',
            'amount_text' => '',
            'amount_value' => null,
            'extra_options' => [],
            'line_items' => [],
        ];
    };

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::sequence()
            ->push([
                'choices' => [
                    ['message' => ['content' => json_encode($rawStructure('2号 一起出 카니발', '2号', '카니발'), JSON_UNESCAPED_UNICODE)]],
                ],
            ])
            ->push([
                'choices' => [
                    ['message' => ['content' => json_encode($rawStructure('2号 날짜 一起出 셋트 卡起 카니발', '2号', '卡起 카니발'), JSON_UNESCAPED_UNICODE)]],
                ],
            ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $headers = [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '2号 一起出 카니발',
        ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일 셋트 카니발')
        ->assertJsonPath('structured.service_date', '8월2일')
        ->assertJsonPath('structured.service_month', '8')
        ->assertJsonPath('structured.service_day', '2')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.vehicle_type', '카니발');

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '2号 날짜 一起出 셋트 卡起 카니발',
        ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일 셋트 卡起 카니발')
        ->assertJsonPath('structured.service_date', '8월2일')
        ->assertJsonPath('structured.service_month', '8')
        ->assertJsonPath('structured.service_day', '2')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.vehicle_type', '카니발부터 가능');
});

test('order summary ai endpoint defaults sandding dropoff to incheon airport', function () {
    $thirdWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setDay(3)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '3号 卡起 03:00 送机 麻浦区 1人 07:00 送机 明洞 4人',
                            'service_date' => '3号',
                            'vehicle_type' => '卡起',
                            'service_type' => '샌딩',
                            'passenger_count' => 1,
                            'pickup_location' => '麻浦区',
                            'dropoff_location' => '',
                            'scheduled_time' => '03:00',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'service_date' => '3号',
                                    'scheduled_time' => '03:00',
                                    'service_type' => '샌딩',
                                    'pickup_location' => '麻浦区',
                                    'dropoff_location' => '',
                                    'passenger_count' => 1,
                                ],
                                [
                                    'service_date' => '3号',
                                    'scheduled_time' => '07:00',
                                    'service_type' => '샌딩',
                                    'pickup_location' => '明洞',
                                    'dropoff_location' => '',
                                    'passenger_count' => 4,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '3号 卡起 03:00 送机 麻浦区 1人 07:00 送机 明洞 4人',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.service_date', now()->month.'월3일')
        ->assertJsonPath('structured.service_month', (string) now()->month)
        ->assertJsonPath('structured.service_day', '3')
        ->assertJsonPath('structured.service_weekday', $thirdWeekday)
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.vehicle_type', '카니발부터 가능')
        ->assertJsonPath('structured.service_type', '샌딩')
        ->assertJsonPath('structured.pickup_location', '마포구')
        ->assertJsonPath('structured.dropoff_location', '인천')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.pickup_location', '마포구')
        ->assertJsonPath('structured.line_items.0.dropoff_location', '인천')
        ->assertJsonPath('structured.line_items.0.location', '마포구—인천')
        ->assertJsonPath('structured.line_items.1.pickup_location', '명동')
        ->assertJsonPath('structured.line_items.1.dropoff_location', '인천')
        ->assertJsonPath('structured.line_items.1.location', '명동—인천');
});

test('order summary ai endpoint normalizes airport terminal and transfer route shorthands', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '明天',
                            'vehicle_type' => '卡起',
                            'service_type' => 'mixed',
                            'passenger_count' => 5,
                            'pickup_location' => '东大门',
                            'dropoff_location' => 'T1',
                            'scheduled_time' => '05:50',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'service_date' => '',
                                    'scheduled_time' => '05:50',
                                    'service_type' => 'sending',
                                    'location' => 'T1',
                                    'passenger_count' => 5,
                                ],
                                [
                                    'service_date' => '',
                                    'scheduled_time' => '22:10',
                                    'service_type' => 'pickup',
                                    'location' => '东大门',
                                    'passenger_count' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "明天 送机 5:50 东大门送T1 5人 卡起 6\n接机 22:10 T1 接机送东大门 6.5",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '내일')
        ->assertJsonPath('structured.pickup_location', '동대문구')
        ->assertJsonPath('structured.dropoff_location', '인천공항 제1터미널')
        ->assertJsonPath('structured.vehicle_type', '카니발부터 가능')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.location', '인천공항 제1터미널')
        ->assertJsonPath('structured.line_items.1.service_type', '픽업')
        ->assertJsonPath('structured.line_items.1.location', '동대문구');
});

test('order summary ai endpoint normalizes mixed passenger luggage counts and new districts', function () {
    $augustSecondWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(2)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '8.2',
                            'vehicle_type' => '',
                            'service_type' => 'mixed',
                            'passenger_count' => '4+3',
                            'pickup_location' => '仁川',
                            'dropoff_location' => '钟路',
                            'scheduled_time' => '1:00',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '7🌾',
                            'amount_value' => '7🌾',
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'service_date' => '8.2',
                                    'scheduled_time' => '17:55',
                                    'service_type' => 'pickup',
                                    'location' => '钟路',
                                    'passenger_count' => '4+4',
                                ],
                                [
                                    'service_date' => '8.2',
                                    'scheduled_time' => '1:00',
                                    'service_type' => 'sending',
                                    'location' => '仁川',
                                    'passenger_count' => '3+3',
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "8.2 18:55仁川—明洞 3+3\n8.2 17:55仁川—钟路 4+4 7🌾\n8.2 1:00中区—仁川 3+3",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.service_time', '01:00')
        ->assertJsonPath('structured.passenger_count', 4)
        ->assertJsonPath('structured.luggage_count', 3)
        ->assertJsonPath('structured.pickup_location', '인천')
        ->assertJsonPath('structured.dropoff_location', '종로구')
        ->assertJsonPath('structured.amount_text', '7만')
        ->assertJsonPath('structured.amount_value', 70000)
        ->assertJsonPath('structured.line_items.0.location', '종로구')
        ->assertJsonPath('structured.line_items.0.passenger_count', 4)
        ->assertJsonPath('structured.line_items.0.luggage_count', 4)
        ->assertJsonPath('structured.line_items.1.location', '인천')
        ->assertJsonPath('structured.line_items.1.service_time', '01:00');
});

test('order summary ai endpoint normalizes no overtime and vehicle option shorthands', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '',
                            'vehicle_type' => '新卡或利亚',
                            'service_type' => '',
                            'passenger_count' => null,
                            'pickup_location' => '',
                            'dropoff_location' => '',
                            'scheduled_time' => '',
                            'order_type' => '',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'extra_options' => [],
                            'line_items' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '不加不聊 无超时 新卡或利亚',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.vehicle_type', '더뉴카니발 4세대 또는 스타리아')
        ->assertJsonPath('structured.extra_options', ['추가 연락 없음', '초과 시간 없음']);
});

test('order summary ai endpoint normalizes station routes wan amounts and person counts', function () {
    $augustSecondWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(2)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '8/02',
                            'vehicle_type' => '利亚7/333',
                            'service_type' => 'sending',
                            'passenger_count' => '1位',
                            'pickup_location' => '首尔站',
                            'dropoff_location' => '首尔站',
                            'scheduled_time' => '7.30',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '7万',
                            'amount_value' => '7万',
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'service_date' => '8/02',
                                    'scheduled_time' => '7.30',
                                    'service_type' => 'sending',
                                    'location' => '首尔站',
                                    'passenger_count' => '1位',
                                ],
                                [
                                    'service_date' => '',
                                    'scheduled_time' => '11:53',
                                    'service_type' => 'pickup',
                                    'location' => '江西区',
                                    'passenger_count' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "8/02\n7.30送机 江东区 1位 新卡起 7万\n7:30 送首尔站 江西区 利亚333\n8:00 送首尔站 龙山 新卡起\n11:53 接首尔站 江西区 利亚7/333",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일')
        ->assertJsonPath('structured.service_date', '8월2일')
        ->assertJsonPath('structured.service_month', '8')
        ->assertJsonPath('structured.service_day', '2')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.service_time', '07:30')
        ->assertJsonPath('structured.vehicle_type', '스타리아 7인승 또는 9인승(3-3-3)')
        ->assertJsonPath('structured.passenger_count', 1)
        ->assertJsonPath('structured.pickup_location', '서울역')
        ->assertJsonPath('structured.dropoff_location', '서울역')
        ->assertJsonPath('structured.amount_text', '7만')
        ->assertJsonPath('structured.amount_value', 70000)
        ->assertJsonPath('structured.line_items.0.service_time', '07:30')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.passenger_count', 1)
        ->assertJsonPath('structured.line_items.1.service_type', '픽업')
        ->assertJsonPath('structured.line_items.1.location', '강서구');
});

test('order summary ai endpoint normalizes day marker time dot district and decimal amounts', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '2日 套出',
                            'vehicle_type' => '小车🉑',
                            'service_type' => 'sending',
                            'passenger_count' => null,
                            'pickup_location' => '江东',
                            'dropoff_location' => '金浦',
                            'scheduled_time' => '11点',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '6.5🌾',
                            'amount_value' => '6.5🌾',
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'scheduled_time' => '16:30',
                                    'service_type' => 'sending',
                                    'location' => '中区',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '11:00',
                                    'service_type' => 'sending',
                                    'location' => '东大门区',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '17:00',
                                    'service_type' => 'sending',
                                    'location' => '中区',
                                    'passenger_count' => '2人',
                                ],
                                [
                                    'scheduled_time' => '18:55',
                                    'service_type' => 'pickup',
                                    'location' => '中区',
                                    'passenger_count' => '2人',
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $expectedLabel = now()->month.'월2일 셋트';
    $expectedDate = now()->month.'월2일';

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "2日，11点。江东-金浦\n小车🉑6.5🌾\n配下面任意一单 套出",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', $expectedLabel)
        ->assertJsonPath('structured.service_date', $expectedDate)
        ->assertJsonPath('structured.service_time', '11:00')
        ->assertJsonPath('structured.vehicle_type', '소형 승용차(세단/SUV)')
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.pickup_location', '강동구')
        ->assertJsonPath('structured.dropoff_location', '김포')
        ->assertJsonPath('structured.amount_text', '6.5만')
        ->assertJsonPath('structured.amount_value', 65000)
        ->assertJsonPath('structured.line_items.0.location', '중구—인천')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.1.location', '동대문구—인천')
        ->assertJsonPath('structured.line_items.2.passenger_count', 2)
        ->assertJsonPath('structured.line_items.3.service_type', '픽업')
        ->assertJsonPath('structured.line_items.3.location', '중구');
});

test('order summary ai endpoint normalizes four digit times block amounts and district shorthands', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '',
                            'vehicle_type' => '全部新卡',
                            'service_type' => 'pickup',
                            'passenger_count' => null,
                            'pickup_location' => '恩平',
                            'dropoff_location' => '',
                            'scheduled_time' => '14:25',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '6块',
                            'amount_value' => '6块',
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'scheduled_time' => '14:25',
                                    'service_type' => 'pickup',
                                    'location' => '恩平',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '2045',
                                    'service_type' => 'pickup',
                                    'location' => '明洞',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '2119',
                                    'service_type' => 'pickup',
                                    'location' => '明洞',
                                    'passenger_count' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "14:25接机恩平\n2045接机明洞\n2119接机明洞\n不加不聊     全部新卡 每单6块",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.vehicle_type', '더뉴카니발 4세대')
        ->assertJsonPath('structured.extra_options', ['추가 연락 없음'])
        ->assertJsonPath('structured.amount_text', '6만')
        ->assertJsonPath('structured.amount_value', 60000)
        ->assertJsonPath('structured.line_items.0.scheduled_time', '14:25')
        ->assertJsonPath('structured.line_items.0.service_type', '픽업')
        ->assertJsonPath('structured.line_items.0.location', '은평구')
        ->assertJsonPath('structured.line_items.1.scheduled_time', '20:45')
        ->assertJsonPath('structured.line_items.1.location', '명동')
        ->assertJsonPath('structured.line_items.2.scheduled_time', '21:19')
        ->assertJsonPath('structured.line_items.2.location', '명동');
});

test('order summary ai endpoint normalizes time service pairs and shared addresses', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '',
                            'vehicle_type' => '需要333',
                            'service_type' => 'mixed',
                            'passenger_count' => null,
                            'pickup_location' => '',
                            'dropoff_location' => '',
                            'scheduled_time' => '15:00',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'scheduled_time' => '15:00',
                                    'service_type' => 'sending',
                                    'location' => '中区',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '17:00',
                                    'service_type' => 'pickup',
                                    'location' => '中区',
                                    'passenger_count' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '15送17接 需要333 地址都是中区',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.vehicle_type', '스타리아 9인승(3-3-3)')
        ->assertJsonPath('structured.service_type', '혼합')
        ->assertJsonPath('structured.line_items.0.scheduled_time', '15:00')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.location', '중구—인천')
        ->assertJsonPath('structured.line_items.1.scheduled_time', '17:00')
        ->assertJsonPath('structured.line_items.1.service_type', '픽업')
        ->assertJsonPath('structured.line_items.1.location', '중구');
});

test('order summary ai endpoint normalizes shorthand glossary expressions', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '今天',
                            'vehicle_type' => '卡起',
                            'service_type' => 'mixed',
                            'passenger_count' => null,
                            'pickup_location' => '麻浦',
                            'dropoff_location' => '',
                            'scheduled_time' => '08:30',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '套出13w',
                            'amount_value' => 130000,
                            'extra_options' => [],
                            'line_items' => [
                                [
                                    'scheduled_time' => '08:30',
                                    'service_type' => 'sending',
                                    'location' => '麻浦',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '12:30',
                                    'service_type' => 'pickup',
                                    'location' => '麻浦',
                                    'passenger_count' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '今天 8:30麻浦送机 12:30接机麻浦 卡起套出13w',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '오늘')
        ->assertJsonPath('structured.vehicle_type', '카니발부터 가능')
        ->assertJsonPath('structured.service_type', '혼합')
        ->assertJsonPath('structured.pickup_location', '마포')
        ->assertJsonPath('structured.order_type', '공항 오더')
        ->assertJsonPath('structured.amount_text', '13만')
        ->assertJsonPath('structured.amount_value', 130000)
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.1.service_type', '픽업');
});

test('order summary ai endpoint normalizes multi date grouped shorthand expressions', function () {
    $augustSecondWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(2)->format('w')];
    $augustThirdWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(3)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '8.2号 套出',
                            'vehicle_type' => '利亚7',
                            'service_type' => 'mixed',
                            'passenger_count' => null,
                            'pickup_location' => '永登浦',
                            'dropoff_location' => '仁川',
                            'scheduled_time' => '08:00',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '19🥬',
                            'amount_value' => '19🥬',
                            'line_items' => [
                                [
                                    'service_date' => '8.2号',
                                    'scheduled_time' => '08:00',
                                    'service_type' => 'sending',
                                    'location' => '永登浦',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '13:15',
                                    'service_type' => 'pickup',
                                    'location' => '麻浦',
                                    'passenger_count' => null,
                                ],
                                [
                                    'scheduled_time' => '17:55',
                                    'service_type' => 'sending',
                                    'location' => '秃山',
                                    'passenger_count' => null,
                                ],
                                [
                                    'service_date' => '8.3号',
                                    'scheduled_time' => '15:25',
                                    'service_type' => 'pickup',
                                    'location' => '江南',
                                    'passenger_count' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "8.2号\n0800永登浦送仁川\n1315仁川接麻浦\n1755秃山送仁川\n利亚7 套出 19🥬\n\n8.3号\n15:25接江南 新卡起 300 위안화",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월2일 셋트')
        ->assertJsonPath('structured.service_date', '8월2일')
        ->assertJsonPath('structured.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.vehicle_type', '스타리아 7인승')
        ->assertJsonPath('structured.amount_text', '19만')
        ->assertJsonPath('structured.amount_value', 190000)
        ->assertJsonPath('structured.line_items.0.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.line_items.1.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.line_items.2.service_weekday', $augustSecondWeekday)
        ->assertJsonPath('structured.line_items.3.service_weekday', $augustThirdWeekday)
        ->assertJsonPath('structured.line_items.0.location', '영등포—인천')
        ->assertJsonPath('structured.line_items.1.location', '마포')
        ->assertJsonPath('structured.line_items.2.location', '독산—인천')
        ->assertJsonPath('structured.line_items.3.location', '강남구');
});

test('order summary ai endpoint normalizes dispatch waiting shorthand expressions', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '收送机',
                            'vehicle_type' => '',
                            'service_type' => 'mixed',
                            'passenger_count' => null,
                            'pickup_location' => '',
                            'dropoff_location' => '',
                            'scheduled_time' => '',
                            'order_type' => '',
                            'flight_number' => '',
                            'amount_text' => '',
                            'amount_value' => null,
                            'line_items' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '收送机',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '현시간부터 픽업/샌딩 배차대기')
        ->assertJsonPath('structured.service_type', '혼합');
});

test('order summary ai endpoint parses sendoff pickup batches with following locations', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    $systemPrompt = '';

    Http::fake([
        'https://example.test/v1/chat/completions' => function (Request $request) use (&$systemPrompt) {
            $systemPrompt = $request['messages'][0]['content'];

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'request_label' => '套出',
                                'vehicle_type' => '利亚',
                                'service_type' => 'mixed',
                                'passenger_count' => null,
                                'pickup_location' => '蚕室',
                                'dropoff_location' => '',
                                'scheduled_time' => '3.30',
                                'order_type' => 'airport',
                                'flight_number' => '',
                                'amount_text' => '套出30万',
                                'amount_value' => 300000,
                                'extra_options' => [],
                                'line_items' => [
                                    ['scheduled_time' => '3.30', 'service_type' => 'sending', 'location' => '蚕室'],
                                    ['scheduled_time' => '9.30', 'service_type' => 'sending', 'location' => '강서구 호텔'],
                                    ['scheduled_time' => '15.30', 'service_type' => 'sending', 'location' => '明洞'],
                                    ['scheduled_time' => '16.45', 'service_type' => 'pickup', 'location' => '东大门'],
                                    ['scheduled_time' => '22.45', 'service_type' => 'pickup', 'location' => '明洞'],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]);
        },
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "3.30送机 蚕室 利亚\n9.30江西酒店 送 明洞乐天附近\n15.30送机 明洞\n16.45接机 东大门\n22.45接机 明洞 利亚 套出30万",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '셋트')
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.service_type', '혼합')
        ->assertJsonPath('structured.vehicle_type', '스타리아')
        ->assertJsonPath('structured.pickup_location', '잠실')
        ->assertJsonPath('structured.amount_text', '30만')
        ->assertJsonPath('structured.amount_value', 300000)
        ->assertJsonPath('structured.line_items.0.scheduled_time', '03:30')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.location', '잠실—인천')
        ->assertJsonPath('structured.line_items.1.scheduled_time', '09:30')
        ->assertJsonPath('structured.line_items.1.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.1.location', '강서구 호텔—인천')
        ->assertJsonPath('structured.line_items.2.scheduled_time', '15:30')
        ->assertJsonPath('structured.line_items.2.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.2.location', '명동—인천')
        ->assertJsonPath('structured.line_items.3.scheduled_time', '16:45')
        ->assertJsonPath('structured.line_items.3.service_type', '픽업')
        ->assertJsonPath('structured.line_items.3.location', '동대문구')
        ->assertJsonPath('structured.line_items.4.scheduled_time', '22:45')
        ->assertJsonPath('structured.line_items.4.service_type', '픽업')
        ->assertJsonPath('structured.line_items.4.location', '명동');

    expect($systemPrompt)
        ->toContain('送机 뒤에 나오는 장소는 픽업 장소')
        ->toContain('接机 뒤에 나오는 장소는 도착지')
        ->toContain('공항 샌딩')
        ->toContain('잠실');
});

test('order summary ai endpoint parses batch routes into line item locations', function () {
    $augustThirdWeekday = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][(int) now()->copy()->setMonth(8)->setDay(3)->format('w')];

    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '8.3号 套出',
                            'vehicle_type' => '333',
                            'service_type' => 'mixed',
                            'passenger_count' => '6+6',
                            'pickup_location' => '明洞',
                            'dropoff_location' => '仁川',
                            'scheduled_time' => '17:00',
                            'order_type' => 'airport',
                            'flight_number' => '',
                            'amount_text' => '套出24',
                            'amount_value' => '套出24',
                            'extra_options' => [],
                            'line_items' => [
                                ['service_date' => '8.3', 'scheduled_time' => '17:00', 'service_type' => 'sending', 'pickup_location' => '明洞', 'dropoff_location' => '仁川', 'passenger_count' => '6+6'],
                                ['service_date' => '8.3', 'scheduled_time' => '20:45', 'service_type' => 'pickup', 'pickup_location' => '仁川', 'dropoff_location' => '明洞', 'passenger_count' => '3+3'],
                                ['service_date' => '8.3', 'scheduled_time' => '11:55', 'service_type' => 'pickup', 'pickup_location' => '仁川', 'dropoff_location' => '龙山', 'passenger_count' => '7+6'],
                                ['service_date' => '8.3', 'scheduled_time' => '10:30', 'service_type' => 'sending', 'pickup_location' => '弘大', 'dropoff_location' => '仁川', 'passenger_count' => '4+3'],
                                ['service_date' => '8.3', 'scheduled_time' => '13:10', 'service_type' => 'pickup', 'pickup_location' => '仁川', 'dropoff_location' => '明洞', 'passenger_count' => '4+4'],
                                ['service_date' => '8.3', 'scheduled_time' => '8:00', 'service_type' => 'sending', 'pickup_location' => '麻浦', 'dropoff_location' => '仁川', 'passenger_count' => '4+4'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => "8.3 17:00明洞—仁川 6+6\n8.3 20:45仁川—明洞3+3\n8.3 11:55仁川—龙山7+6\n8.3 10:30弘大—仁川 4+3\n   套出 24   来个   333\n8.3 13:10仁川—明洞4+4\n8.3 8:00麻浦—仁川4+4",
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('structured.request_label', '8월3일 셋트')
        ->assertJsonPath('structured.service_date', '8월3일')
        ->assertJsonPath('structured.service_month', '8')
        ->assertJsonPath('structured.service_day', '3')
        ->assertJsonPath('structured.service_weekday', $augustThirdWeekday)
        ->assertJsonPath('structured.service_time', '17:00')
        ->assertJsonPath('structured.group_type', '셋트')
        ->assertJsonPath('structured.vehicle_type', '스타리아 9인승(3-3-3)')
        ->assertJsonPath('structured.service_type', '혼합')
        ->assertJsonPath('structured.passenger_count', 6)
        ->assertJsonPath('structured.luggage_count', 6)
        ->assertJsonPath('structured.pickup_location', '명동')
        ->assertJsonPath('structured.dropoff_location', '인천')
        ->assertJsonPath('structured.amount_text', '24만')
        ->assertJsonPath('structured.amount_value', 240000)
        ->assertJsonPath('structured.line_items.0.service_time', '17:00')
        ->assertJsonPath('structured.line_items.0.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.0.pickup_location', '명동')
        ->assertJsonPath('structured.line_items.0.dropoff_location', '인천')
        ->assertJsonPath('structured.line_items.0.location', '명동—인천')
        ->assertJsonPath('structured.line_items.0.passenger_count', 6)
        ->assertJsonPath('structured.line_items.0.luggage_count', 6)
        ->assertJsonPath('structured.line_items.1.pickup_location', '인천')
        ->assertJsonPath('structured.line_items.1.dropoff_location', '명동')
        ->assertJsonPath('structured.line_items.1.location', '인천—명동')
        ->assertJsonPath('structured.line_items.1.passenger_count', 3)
        ->assertJsonPath('structured.line_items.1.luggage_count', 3)
        ->assertJsonPath('structured.line_items.2.pickup_location', '인천')
        ->assertJsonPath('structured.line_items.2.dropoff_location', '용산구')
        ->assertJsonPath('structured.line_items.2.location', '인천—용산구')
        ->assertJsonPath('structured.line_items.2.passenger_count', 7)
        ->assertJsonPath('structured.line_items.2.luggage_count', 6)
        ->assertJsonPath('structured.line_items.3.pickup_location', '홍대')
        ->assertJsonPath('structured.line_items.3.dropoff_location', '인천')
        ->assertJsonPath('structured.line_items.3.location', '홍대—인천')
        ->assertJsonPath('structured.line_items.3.passenger_count', 4)
        ->assertJsonPath('structured.line_items.3.luggage_count', 3)
        ->assertJsonPath('structured.line_items.4.pickup_location', '인천')
        ->assertJsonPath('structured.line_items.4.dropoff_location', '명동')
        ->assertJsonPath('structured.line_items.4.passenger_count', 4)
        ->assertJsonPath('structured.line_items.4.luggage_count', 4)
        ->assertJsonPath('structured.line_items.5.pickup_location', '마포')
        ->assertJsonPath('structured.line_items.5.dropoff_location', '인천')
        ->assertJsonPath('structured.line_items.5.location', '마포—인천')
        ->assertJsonPath('structured.line_items.5.service_time', '08:00')
        ->assertJsonPath('structured.line_items.5.service_type', '샌딩')
        ->assertJsonPath('structured.line_items.5.passenger_count', 4)
        ->assertJsonPath('structured.line_items.5.luggage_count', 4)
        ->assertJsonPath('structured.line_items.5.service_month', '8')
        ->assertJsonPath('structured.line_items.5.service_day', '3')
        ->assertJsonPath('structured.line_items.5.service_weekday', $augustThirdWeekday);
});

test('order summary ai endpoint returns service unavailable when config is missing', function () {
    config()->set('services.order_ai.api_key', '');
    config()->set('services.order_ai.base_url', '');
    config()->set('services.order_ai.model', '');

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '서울 강남구 - 인천공항 08:42 · 일반 오더 KE123',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'AI 구조화 API 설정이 비어 있습니다.');
});

test('order summary ai endpoint preserves upstream http status when request fails', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Invalid API key provided.',
            ],
        ], 401),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '서울 강남구 - 인천공항 08:42 · 일반 오더 KE123',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Invalid API key provided.');
});

test('order summary ai endpoint preserves upstream quota status when request exceeds quota', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');
    config()->set('services.order_ai.timeout', 10);

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'You exceeded your current quota, please check your plan and billing details.',
            ],
        ], 429),
    ]);

    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.structure'), [
            'summary' => '서울 강남구 - 인천공항 08:42 · 일반 오더 KE123',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertStatus(429)
        ->assertJsonPath('message', 'You exceeded your current quota, please check your plan and billing details.');
});

test('users can register order with route and amount via line items', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.store'), [
            'status' => Order::STATUS_DRAFT,
            'line_items' => [
                'new_1' => [
                    'scheduled_time' => '08:42',
                    'service_month' => 8,
                    'service_day' => 2,
                    'service_weekday' => '일요일',
                    'service_type' => '샌딩',
                    'pickup_location' => '서울 강남구',
                    'dropoff_location' => '인천공항',
                    'flight_number' => 'KE123',
                    'passenger_count' => 4,
                    'luggage_count' => 2,
                    'amount_value' => 18000,
                    'amount_text' => '',
                ],
            ],
        ])
        ->assertSessionHas('status');

    $order = Order::query()->where('pickup_location', '서울 강남구')->first();

    expect($order)->not->toBeNull();
    expect($order?->reservation_company)->toBe('직접예약');
    expect($order?->customer_name)->toBe('미지정');
    expect($order?->pickup_location)->toBe('서울 강남구');
    expect($order?->dropoff_location)->toBe('인천공항');
    expect($order?->flight_number)->toBe('KE123');
    expect($order?->passenger_count)->toBe(4);
    expect($order?->expected_revenue)->toBe(18000);
    expect($order?->scheduled_at?->format('Y-m-d H:i'))->toBe('2026-08-02 08:42');
    expect($order?->order_number)->toStartWith('ORD-');
    expect($order?->lineItems()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $order))
        ->assertSuccessful()
        ->assertSee('서울 강남구 → 인천공항')
        ->assertSee('2026-08-02 08:42')
        ->assertSee('항공편 KE123')
        ->assertSee('18,000원')
        ->assertSee('초안')
        ->assertSee('오더 수정')
        ->assertSee('취소')
        ->assertSee('data-order-detail-actions', false)
        ->assertSee('4명')
        ->assertSee('data-order-show-page', false);
});

test('users can register order without passenger count', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.store'), [
            'status' => Order::STATUS_DRAFT,
            'line_items' => [
                'new_1' => [
                    'scheduled_time' => '09:00',
                    'service_month' => 8,
                    'service_day' => 2,
                    'pickup_location' => '명동',
                    'dropoff_location' => '인천공항',
                    'flight_number' => 'KE999',
                ],
            ],
        ])
        ->assertSessionHas('status');

    $order = Order::query()->where('pickup_location', '명동')->first();

    expect($order)->not->toBeNull();
    expect($order?->passenger_count)->toBe(1);
});

test('users can register a set order via group type selection', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.store'), [
            'status' => Order::STATUS_DRAFT,
            'group_type' => '셋트',
            'line_items' => [
                'new_1' => [
                    'scheduled_time' => '15:20',
                    'service_month' => 8,
                    'service_day' => 2,
                    'pickup_location' => '강남구',
                    'dropoff_location' => '인천공항',
                ],
                'new_2' => [
                    'scheduled_time' => '17:25',
                    'service_month' => 8,
                    'service_day' => 2,
                    'pickup_location' => '종로구',
                    'dropoff_location' => '인천공항',
                ],
            ],
        ])
        ->assertSessionHas('status');

    $order = Order::query()->where('pickup_location', '강남구')->first();

    expect($order)->not->toBeNull();
    expect($order?->group_type)->toBe('셋트');
    expect($order?->lineItems()->count())->toBe(2);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $order))
        ->assertSuccessful()
        ->assertSee('셋트 일정')
        ->assertSee('data-order-set-table', false)
        ->assertDontSee('일정 목록');
});

test('users can register a single order and single group type is not persisted', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.store'), [
            'status' => Order::STATUS_DRAFT,
            'group_type' => '단일',
            'line_items' => [
                'new_1' => [
                    'scheduled_time' => '08:42',
                    'service_month' => 8,
                    'service_day' => 2,
                    'pickup_location' => '서울 강남구',
                    'dropoff_location' => '인천공항',
                ],
            ],
        ])
        ->assertSessionHas('status');

    $order = Order::query()->where('pickup_location', '서울 강남구')->first();

    expect($order)->not->toBeNull();
    expect($order?->group_type)->toBeNull();
    expect($order?->lineItems()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $order))
        ->assertSuccessful()
        ->assertSee('일정 목록')
        ->assertDontSee('data-order-set-table', false);
});

test('users can store structured order summary result into database', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $payload = json_encode([
        'message' => '오더 요약을 구조화했습니다.',
        'structured' => [
            'request_label' => '',
            'service_date' => '',
            'service_month' => '3',
            'service_day' => '3',
            'service_weekday' => '화요일',
            'service_time' => '02:00',
            'extra_options' => [],
            'group_type' => '',
            'vehicle_type' => '',
            'service_type' => '샌딩',
            'passenger_count' => 2,
            'luggage_count' => null,
            'pickup_location' => '중구',
            'dropoff_location' => '',
            'scheduled_time' => '02:00',
            'order_type' => '일반 오더',
            'flight_number' => '',
            'amount_text' => '',
            'amount_value' => null,
            'line_items' => [
                [
                    'scheduled_time' => '02:00',
                    'service_month' => '3',
                    'service_day' => '3',
                    'service_weekday' => '화요일',
                    'service_time' => '02:00',
                    'service_type' => '샌딩',
                    'location' => '',
                    'passenger_count' => 2,
                    'luggage_count' => null,
                ],
                [
                    'scheduled_time' => '02:30',
                    'service_month' => '3',
                    'service_day' => '3',
                    'service_weekday' => '화요일',
                    'service_time' => '02:30',
                    'service_type' => '샌딩',
                    'location' => '',
                    'passenger_count' => 3,
                    'luggage_count' => null,
                ],
                [
                    'scheduled_time' => '05:00',
                    'service_month' => '3',
                    'service_day' => '3',
                    'service_weekday' => '화요일',
                    'service_time' => '05:00',
                    'service_type' => '샌딩',
                    'location' => '',
                    'passenger_count' => 5,
                    'luggage_count' => null,
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.storeStructured'), [
            'structured' => $payload,
        ])
        ->assertRedirect();

    $order = Order::query()->where('pickup_location', '중구')->where('service_time', '02:00')->first();

    expect($order)->not->toBeNull();
    expect($order?->service_type)->toBe('샌딩');
    expect($order?->passenger_count)->toBe(2);
    expect($order?->order_type)->toBe(Order::TYPE_GENERAL);
    expect($order?->status)->toBe(Order::STATUS_DRAFT);
    expect($order?->scheduled_at?->format('H:i'))->toBe('02:00');
    expect($order?->scheduled_at?->toDateString())->toBe(now()->toDateString());
    expect($order?->user_id)->toBe($user->id);

    $lineItems = $order?->lineItems()->orderBy('scheduled_time')->get();

    expect($lineItems)->toHaveCount(3);
    expect($lineItems->pluck('scheduled_time')->all())->toBe(['02:00', '02:30', '05:00']);
    expect($lineItems->pluck('passenger_count')->all())->toBe([2, 3, 5]);
    expect($lineItems->pluck('service_month')->all())->toBe(['3', '3', '3']);
    expect($lineItems->pluck('service_day')->all())->toBe(['3', '3', '3']);
    expect($lineItems->pluck('service_weekday')->all())->toBe(['화요일', '화요일', '화요일']);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $order))
        ->assertSuccessful()
        ->assertSee('AI 구조화')
        ->assertSee('구조화 정보')
        ->assertSee('일정 목록')
        ->assertSee('02:30')
        ->assertSee('data-order-structured-info', false);
});

test('structured order store honors user selected group type over ai detection', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    // AI는 셋트로 판별했지만 사용자가 단일을 선택한 경우 단일로 저장된다.
    $payload = json_encode([
        'summary' => '셋트 오더',
        'structured' => [
            'group_type' => '셋트',
            'service_date' => '8월 2일',
            'service_time' => '10:00',
            'pickup_location' => '강남구',
            'dropoff_location' => '인천공항',
            'line_items' => [
                [
                    'scheduled_time' => '10:00',
                    'service_month' => '8',
                    'service_day' => '2',
                    'pickup_location' => '강남구',
                    'dropoff_location' => '인천공항',
                ],
                [
                    'scheduled_time' => '12:00',
                    'service_month' => '8',
                    'service_day' => '2',
                    'pickup_location' => '종로구',
                    'dropoff_location' => '인천공항',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.storeStructured'), [
            'structured' => $payload,
            'group_type' => '단일',
        ])
        ->assertRedirect();

    $order = Order::query()->where('pickup_location', '강남구')->first();

    expect($order)->not->toBeNull();
    expect($order?->group_type)->toBeNull();

    // AI가 셋트를 판별하지 못했어도 사용자가 셋트를 선택하면 셋트로 저장된다.
    $setPayload = json_encode([
        'summary' => '셋트 오더',
        'structured' => [
            'service_date' => '8월 3일',
            'service_time' => '11:00',
            'pickup_location' => '서초구',
            'dropoff_location' => '인천공항',
            'line_items' => [
                [
                    'scheduled_time' => '11:00',
                    'service_month' => '8',
                    'service_day' => '3',
                    'pickup_location' => '서초구',
                    'dropoff_location' => '인천공항',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.storeStructured'), [
            'structured' => $setPayload,
            'group_type' => '셋트',
        ])
        ->assertRedirect();

    $setOrder = Order::query()->where('pickup_location', '서초구')->first();

    expect($setOrder)->not->toBeNull();
    expect($setOrder?->group_type)->toBe('셋트');
});

test('order detail shows set orders in a grouped table and single schedules in a single table', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $setOrder = Order::factory()->create([
        'user_id' => $user->id,
        'group_type' => '셋트',
        'amount_text' => '24만',
        'amount_value' => 240000,
        'pickup_location' => '명동',
        'dropoff_location' => '인천',
    ]);

    $setOrder->lineItems()->create([
        'scheduled_time' => '17:00',
        'service_month' => 8,
        'service_day' => 3,
        'service_weekday' => '월요일',
        'service_type' => '샌딩',
        'location' => '명동—인천',
        'passenger_count' => 6,
        'luggage_count' => 6,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $setOrder))
        ->assertSuccessful()
        ->assertSee('셋트 일정')
        ->assertSee('24만')
        ->assertSee('data-order-set-table', false)
        ->assertDontSee('일정 목록');

    $singleOrder = Order::factory()->create([
        'user_id' => $user->id,
        'group_type' => null,
    ]);

    $singleOrder->lineItems()->create([
        'scheduled_time' => '08:00',
        'service_month' => 8,
        'service_day' => 3,
        'service_weekday' => '월요일',
        'service_type' => '샌딩',
        'location' => '마포—인천',
        'passenger_count' => 4,
        'luggage_count' => 4,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $singleOrder))
        ->assertSuccessful()
        ->assertSee('일정 목록')
        ->assertDontSee('data-order-set-table')
        ->assertDontSee('셋트 일정');
});

test('structured order saves with default passenger count when not provided', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    // 인원 수가 언급되지 않은 입력 ("15:30 스타리아 혼합 30만 명동")
    $payload = json_encode([
        'message' => '오더 요약을 구조화했습니다.',
        'structured' => [
            'request_label' => '',
            'service_date' => '8월2일',
            'service_time' => '15:30',
            'group_type' => '',
            'vehicle_type' => '스타리아',
            'service_type' => '혼합',
            'passenger_count' => null,
            'luggage_count' => null,
            'amount_text' => '30만',
            'amount_value' => 300000,
            'extra_options' => [],
            'pickup_location' => '명동',
            'dropoff_location' => '',
            'order_type' => '일반 오더',
        ],
    ], JSON_UNESCAPED_UNICODE);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.storeStructured'), [
            'structured' => $payload,
        ])
        ->assertRedirect();

    $order = Order::query()->where('pickup_location', '명동')->where('service_time', '15:30')->first();

    expect($order)->not->toBeNull();
    expect($order?->passenger_count)->toBe(1);
    expect($order?->vehicle_type)->toBe('스타리아');
    expect($order?->amount_value)->toBe(300000);
});

test('structured order derives passenger count from line items', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    // 최상위 인원 수는 없고, 일정별 인원만 있는 경우
    $payload = json_encode([
        'message' => '오더 요약을 구조화했습니다.',
        'structured' => [
            'request_label' => '',
            'service_date' => '3월3일',
            'service_time' => '02:00',
            'vehicle_type' => '스타리아',
            'service_type' => '샌딩',
            'passenger_count' => null,
            'amount_text' => '',
            'amount_value' => null,
            'extra_options' => [],
            'pickup_location' => '중구',
            'line_items' => [
                ['scheduled_time' => '02:00', 'passenger_count' => 2, 'service_month' => 3, 'service_day' => 3],
                ['scheduled_time' => '02:30', 'passenger_count' => 3, 'service_month' => 3, 'service_day' => 3],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.storeStructured'), [
            'structured' => $payload,
        ])
        ->assertRedirect();

    $order = Order::query()->where('pickup_location', '중구')->first();

    expect($order)->not->toBeNull();
    expect($order?->passenger_count)->toBe(5);
});

test('structured order preserves original summary and ai response payload', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $originalSummary = "3.30送机 蚕室 利亚\n9.30江西酒店 送 明洞乐天附近\n15.30送机 明洞";
    $structuredArray = [
        'message' => '오더 요약을 구조화했습니다.',
        'structured' => [
            'request_label' => '셋트',
            'service_date' => '8월2일',
            'service_time' => '03:30',
            'group_type' => '셋트',
            'vehicle_type' => '스타리아',
            'service_type' => '혼합',
            'passenger_count' => 1,
            'luggage_count' => null,
            'amount_text' => '30만',
            'amount_value' => 300000,
            'extra_options' => [],
            'pickup_location' => '잠실',
            'dropoff_location' => '',
            'flight_number' => '',
            'scheduled_time' => '03:30',
            'order_type' => '공항 오더',
            'line_items' => [
                ['scheduled_time' => '03:30', 'service_type' => '샌딩', 'location' => '잠실'],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('dashboard.business.order.storeStructured'), [
            'structured' => json_encode($structuredArray, JSON_UNESCAPED_UNICODE),
            'original_summary' => $originalSummary,
        ])
        ->assertRedirect();

    $order = Order::query()->where('pickup_location', '잠실')->first();

    expect($order)->not->toBeNull();
    expect($order?->original_summary)->toBe($originalSummary);
    expect($order?->structured_payload)->toBe($structuredArray);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $order))
        ->assertSuccessful()
        ->assertSee('원본 입력 내용')
        ->assertSee('3.30送机 蚕室 利亚', false)
        ->assertSee('AI 반환 JSON')
        ->assertSee('data-order-original-summary', false)
        ->assertSee('data-order-structured-payload', false)
        ->assertSee('셋트 일정')
        ->assertSee('30만')
        ->assertSee('03:30')
        ->assertSee('잠실');
});

test('users can view order edit page with prefilled values', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'customer_name' => '홍길동',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
    ]);

    $order->lineItems()->create([
        'scheduled_time' => '02:00',
        'service_month' => 3,
        'service_day' => 3,
        'service_weekday' => '화요일',
        'service_type' => '샌딩',
        'location' => '중구',
        'passenger_count' => 2,
        'luggage_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.edit', $order))
        ->assertSuccessful()
        ->assertSee('예약 수정')
        ->assertSee('변경사항 저장')
        ->assertSee('서울 강남구')
        ->assertSee('인천공항')
        ->assertSee('일정 목록')
        ->assertSee('일정 추가')
        ->assertSee('02:00')
        ->assertSee('예약 구분');
});

test('users can update an order', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'customer_name' => '홍길동',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->patch(route('dashboard.business.order.update', $order), [
            'status' => Order::STATUS_DRAFT,
            'group_type' => '셋트',
            'line_items' => [
                'new_1' => [
                    'scheduled_time' => '10:00',
                    'service_month' => 8,
                    'service_day' => 3,
                    'service_weekday' => '월요일',
                    'service_type' => '픽업',
                    'pickup_location' => '서울 중구',
                    'dropoff_location' => '김포공항',
                    'flight_number' => 'OZ101',
                    'passenger_count' => 3,
                    'luggage_count' => 1,
                    'amount_value' => 22000,
                ],
            ],
        ])
        ->assertRedirect(route('dashboard.business.order.show', $order))
        ->assertSessionHas('status', '오더가 수정되었습니다.');

    $order->refresh();

    expect($order->pickup_location)->toBe('서울 중구');
    expect($order->dropoff_location)->toBe('김포공항');
    expect($order->flight_number)->toBe('OZ101');
    expect($order->passenger_count)->toBe(3);
    expect($order->expected_revenue)->toBe(22000);
    expect($order->group_type)->toBe('셋트');
    expect($order->lineItems()->count())->toBe(1);

    $lineItem = $order->lineItems()->first();

    expect($lineItem?->scheduled_time)->toBe('10:00');
    expect($lineItem?->service_date)->toBe('8월3일');
    expect($lineItem?->amount_value)->toBe(22000);
});

test('users can update order line items', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'customer_name' => '홍길동',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_DRAFT,
    ]);

    $firstLineItem = $order->lineItems()->create([
        'scheduled_time' => '02:00',
        'service_month' => 3,
        'service_day' => 3,
        'service_weekday' => '화요일',
        'service_type' => '샌딩',
        'location' => '중구',
        'passenger_count' => 2,
        'luggage_count' => 1,
    ]);

    $removedLineItem = $order->lineItems()->create([
        'scheduled_time' => '05:00',
        'service_month' => 3,
        'service_day' => 3,
        'service_weekday' => '화요일',
        'service_type' => '픽업',
        'location' => '동대문구',
        'passenger_count' => 5,
        'luggage_count' => 0,
    ]);

    $this->actingAs($user)
        ->patch(route('dashboard.business.order.update', $order), [
            'pickup_location' => '서울 강남구',
            'dropoff_location' => '인천공항',
            'flight_number' => 'KE123',
            'scheduled_at' => '2026-08-03 10:00:00',
            'passenger_count' => 2,
            'estimated_duration_minutes' => 35,
            'distance_km' => 12.0,
            'expected_revenue' => 18000,
            'status' => Order::STATUS_DRAFT,
            'line_items' => [
                $firstLineItem->id => [
                    'scheduled_time' => '02:30',
                    'service_month' => 3,
                    'service_day' => 3,
                    'service_weekday' => '화요일',
                    'service_type' => '샌딩',
                    'location' => '중구',
                    'passenger_count' => 3,
                    'luggage_count' => 2,
                ],
                'new_1' => [
                    'scheduled_time' => '09:00',
                    'service_month' => 3,
                    'service_day' => 4,
                    'service_weekday' => '수요일',
                    'service_type' => '픽업',
                    'location' => '마포구',
                    'passenger_count' => 4,
                    'luggage_count' => 1,
                ],
            ],
        ])
        ->assertRedirect(route('dashboard.business.order.show', $order))
        ->assertSessionHas('status', '오더가 수정되었습니다.');

    $firstLineItem->refresh();
    expect($firstLineItem->scheduled_time)->toBe('02:30');
    expect($firstLineItem->passenger_count)->toBe(3);
    expect($firstLineItem->service_date)->toBe('3월3일');

    expect($order->lineItems()->whereKey($removedLineItem->id)->exists())->toBeFalse();

    $createdLineItem = $order->lineItems()->where('scheduled_time', '09:00')->first();
    expect($createdLineItem)->not->toBeNull();
    expect($createdLineItem->location)->toBe('마포구');
    expect($createdLineItem->service_date)->toBe('3월4일');
    expect($order->lineItems()->count())->toBe(2);
});

test('order registration validates required fields', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $this->actingAs($user)
        ->from(route('dashboard.business.order.create'))
        ->post(route('dashboard.business.order.store'), [
            'status' => '',
            'line_items' => [],
        ])
        ->assertRedirect(route('dashboard.business.order.create'))
        ->assertSessionHasErrors(['status', 'line_items']);
});
