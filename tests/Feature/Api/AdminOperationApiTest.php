<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderIngestion;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => '운영자']);
    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'name' => '김기사']);
    $this->customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => '홍등록']);
});

function publishedOrder(User $owner, array $extra = []): Order
{
    return Order::factory()->create(array_merge([
        'user_id' => $owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
        'service_time' => '13:30',
        'amount_value' => 80000,
    ], $extra));
}

function startConversation(User $a, User $b, Order $order): Conversation
{
    $conversation = Conversation::query()->create(['order_id' => $order->id]);
    $conversation->users()->attach([$a->id, $b->id]);
    $conversation->messages()->create([
        'user_id' => $a->id,
        'body' => '안녕하세요, 운행 문의드립니다.',
    ]);

    return $conversation;
}

test('admins can moderate a driver and restricted drivers cannot claim or offer', function () {
    $order = publishedOrder($this->customer);

    // 제재 전 — 요청 가능
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    // 관리자가 '운행 제한' 제재
    Sanctum::actingAs($this->admin);
    $this->patchJson("/api/admin/users/{$this->driver->id}/moderation", [
        'status' => 'restricted',
        'note' => '노쇼 반복으로 운행 제한',
    ])->assertOk()
        ->assertJsonPath('data.moderation_status', 'restricted');

    expect($this->driver->fresh()?->moderation_note)->toBe('노쇼 반복으로 운행 제한');
    expect($this->driver->notifications()->where('data->title', '운영 제재')->count())->toBe(1);

    // 제재된 상태를 다시 읽어 요청 사용자에게 반영한다 (테스트 인스턴스 갱신)
    $this->driver = $this->driver->fresh();

    // 다른 운행에 요금 제안 시도 → 제재로 차단
    $order2 = publishedOrder($this->customer);
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order2->id}/offers", [
        'amount' => 70000,
        'message' => '제안합니다.',
    ])->assertForbidden();

    // 가져오기 요청도 차단
    $order3 = publishedOrder($this->customer);
    $this->postJson("/api/orders/{$order3->id}/claim")->assertForbidden();
});

test('non-admin cannot run moderation and admin cannot moderate themselves', function () {
    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/admin/users/{$this->customer->id}/moderation", [
        'status' => 'watch',
        'note' => '주의',
    ])->assertForbidden();

    Sanctum::actingAs($this->admin);
    $this->patchJson("/api/admin/users/{$this->admin->id}/moderation", [
        'status' => 'suspended',
        'note' => '본인 제재',
    ])->assertStatus(422);
});

test('admin can hide an order from the market and unhide it', function () {
    $order = publishedOrder($this->customer);

    // 숨김 전 — 마켓에 노출
    Sanctum::actingAs($this->driver);
    $this->getJson('/api/orders?scope=market')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id);

    // 관리자가 숨김
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/hide", [
        'hidden' => true,
        'reason' => '중복 등록으로 잠정 숨김',
    ])->assertOk()
        ->assertJsonPath('data.is_hidden', true);

    // 숨긴 사유 없이 요청은 거부
    $this->postJson("/api/admin/orders/{$order->id}/hide", [
        'hidden' => true,
        'reason' => '',
    ])->assertStatus(422);

    // 마켓·추천에서 제외
    Sanctum::actingAs($this->driver);
    $this->getJson('/api/orders?scope=market')
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->postJson("/api/orders/{$order->id}/claim")->assertForbidden();

    // 관리자 타임라인에 기록
    expect(OrderEvent::query()->where('order_id', $order->id)->where('event', 'admin_hidden')->count())->toBe(1);

    // 해제 후 다시 노출
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/hide", [
        'hidden' => false,
        'reason' => '확인 완료',
    ])->assertOk()
        ->assertJsonPath('data.is_hidden', false);
});

test('admin can hold an order which freezes progress until release', function () {
    $order = publishedOrder($this->customer);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/hold", [
        'hold' => true,
        'reason' => '분쟁 조정 중',
    ])->assertOk()
        ->assertJsonPath('data.admin_hold', true)
        ->assertJsonPath('data.admin_hold_reason', '분쟁 조정 중');

    expect(OrderEvent::query()->where('order_id', $order->id)->where('event', 'admin_hold')->count())->toBe(1);
    expect($this->customer->notifications()->where('data->title', '운행 보류')->count())->toBe(1);

    // 일반 사용자 상태 전이 차단
    Sanctum::actingAs($this->customer);
    $this->postJson("/api/orders/{$order->id}/status", ['status' => 'published'])->assertStatus(409);

    // 보류 해제 후 다시 진행 가능
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/hold", [
        'hold' => false,
        'reason' => '조정 완료',
    ])->assertOk()
        ->assertJsonPath('data.admin_hold', false);
});

test('admin can force cancel a running order with reason and notify parties', function () {
    $order = publishedOrder($this->customer);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    // 등록자가 승인 → accepted (운행 확정)
    Sanctum::actingAs($this->customer);
    $this->postJson("/api/orders/{$order->id}/status", ['status' => 'accepted'])->assertOk();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/force-cancel", [
        'reason' => '사기 의심 신고로 즉시 종료',
    ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($order->fresh()?->status)->toBe(Order::STATUS_CANCELLED);
    expect($order->fresh()?->cancel_reason)->toBe('사기 의심 신고로 즉시 종료');
    expect($this->customer->notifications()->where('data->title', '운행 강제 취소')->count())->toBe(1);
    expect($this->driver->notifications()->where('data->title', '운행 강제 취소')->count())->toBe(1);

    // 완료된 운행은 강제 취소할 수 없다
    $completed = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->customer->id,
        'status' => Order::STATUS_COMPLETED,
        'amount_value' => 100000,
        'expected_revenue' => 100000,
    ]);

    $this->postJson("/api/admin/orders/{$completed->id}/force-cancel", [
        'reason' => '완료 후 취소 시도',
    ])->assertStatus(422);
});

test('admin can search orders for intervention', function () {
    $order = publishedOrder($this->customer, ['pickup_location' => '김포공항']);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/operations/orders?q=김포')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.registrant.name', '홍등록');

    $this->getJson('/api/admin/operations/orders?q=없는노선')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('admin can read a conversation and send a moderation message', function () {
    $order = publishedOrder($this->customer);
    $conversation = startConversation($this->driver, $this->customer, $order);

    Sanctum::actingAs($this->admin);

    // 대화 목록 + 내용 읽기
    $this->getJson('/api/admin/operations/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/admin/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', '안녕하세요, 운행 문의드립니다.');

    // 중재 메시지
    $this->postJson("/api/admin/conversations/{$conversation->id}/moderate", [
        'body' => '운영팀에서 조정하겠습니다. 양측 모두 채팅을 자제해 주세요.',
    ])->assertCreated()
        ->assertJsonPath('data.payload.moderator', true);

    $message = Message::query()->latest('id')->first();

    expect($message->payload)->toBe(['moderator' => true]);
});

test('admin can hold a settlement which excludes it from payout requests', function () {
    // 정산 원장 생성 (settled 전이)
    $order = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->customer->id,
        'status' => Order::STATUS_COMPLETED,
        'amount_value' => 100000,
        'expected_revenue' => 100000,
    ]);

    Sanctum::actingAs($this->customer);
    $this->postJson('/api/orders/batch-settle', ['ids' => [$order->id]])->assertOk();

    $settlement = Settlement::query()->firstOrFail();

    // 관리자가 보류
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/settlements/{$settlement->id}/hold", [
        'hold' => true,
        'reason' => '운행 분쟁 확인 중',
    ])->assertOk()
        ->assertJsonPath('data.hold_reason', '운행 분쟁 확인 중');

    // 보류 중에는 출금 신청 불가
    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/bank-account', [
        'bank_name' => '국민은행',
        'account_number' => '111-22-333333',
        'account_holder' => '김기사',
    ])->assertOk();
    $this->postJson('/api/me/payouts')->assertStatus(409);

    // 관리자 목록에 보류 표시 + 해제 후 재신청 가능
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/operations/settlements')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hold_reason', '운행 분쟁 확인 중');

    $this->postJson("/api/admin/settlements/{$settlement->id}/hold", [
        'hold' => false,
        'reason' => '확인 완료',
    ])->assertOk()
        ->assertJsonPath('data.hold_reason', null);

    // 등록자 입금 확인(수금 확정) 후에야 기사가 출금 신청할 수 있다
    $settlement->forceFill([
        'collection_status' => Settlement::COLLECTION_PAID,
        'collected_at' => now(),
    ])->save();

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/me/payouts')->assertCreated();
});

test('daily summary groups red urgent, yellow review and green normal counts', function () {
    $order = publishedOrder($this->customer);

    // 신고 접수(red) + 보류(red)
    Sanctum::actingAs($this->driver);
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'cancel',
        'reason' => '등록자가 일방적으로 취소를 요구합니다.',
    ])->assertCreated();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/admin/orders/{$order->id}/hold", [
        'hold' => true,
        'reason' => '신고 조사 중',
    ])->assertOk();

    // 승인 대기(yellow) — 다른 운행에 기사가 요청한 상태로 만든다
    $approvalOrder = publishedOrder($this->customer);
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$approvalOrder->id}/claim")->assertOk();

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/operations/daily')
        ->assertOk()
        ->assertJsonPath('data.red.reports_pending', 1)
        ->assertJsonPath('data.red.orders_hold', 1)
        ->assertJsonPath('data.yellow.approval_waiting', 1);
});

test('metrics summarizes pipeline, matching, settlement, reports and users (Q-6)', function () {
    // 진행 중 운행 — 수락 대기 상태 1건
    $approvalOrder = publishedOrder($this->customer, [
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
    ]);

    // 신청(claim) — 최근 30일 이내이므로 matching_30d.pending에 집계
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$approvalOrder->id}/claim")->assertOk();

    // 정산 — 지급 대기(출금 가능) 원장 1건
    Settlement::query()->create([
        'order_id' => publishedOrder($this->customer, ['status' => Order::STATUS_SETTLED])->id,
        'driver_id' => $this->driver->id,
        'registrant_id' => $this->customer->id,
        'gross_amount' => 80000,
        'fee_amount' => 4000,
        'net_amount' => 76000,
        'status' => Settlement::STATUS_PENDING,
    ]);

    // 신고 접수 1건
    Sanctum::actingAs($this->driver);
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $approvalOrder->id,
        'category' => 'cancel',
        'reason' => '문제 신고',
    ])->assertCreated();

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/operations/metrics')
        ->assertOk()
        ->assertJsonPath('data.pipeline.acceptance_pending', 1)
        ->assertJsonPath('data.matching_30d.pending', 1)
        ->assertJsonPath('data.settlement.pending_amount', 76000)
        ->assertJsonPath('data.revenue.month_gross', 80000)
        ->assertJsonPath('data.revenue.month_fee', 4000)
        ->assertJsonPath('data.revenue.effective_rate', 0.05)
        ->assertJsonPath('data.revenue.total_fee', 4000)
        ->assertJsonPath('data.reports.pending', 1)
        ->assertJsonPath('data.users.customers_today', 1);
});

test('admin can set and clear a registrant specific fee rate', function () {
    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/admin/users/{$this->customer->id}/fee-rate", ['fee_rate' => 0.03])
        ->assertOk()
        ->assertJsonPath('data.fee_rate', 0.03);

    expect((float) $this->customer->fresh()?->fee_rate)->toBe(0.03);

    // 해제하면 전역 정책 요율을 따르도록 null이 된다
    $this->patchJson("/api/admin/users/{$this->customer->id}/fee-rate", ['fee_rate' => null])
        ->assertOk()
        ->assertJsonPath('data.fee_rate', null);

    expect($this->customer->fresh()?->fee_rate)->toBeNull();
});

test('non-admin cannot change a registrant fee rate', function () {
    Sanctum::actingAs($this->driver);

    $this->patchJson("/api/admin/users/{$this->customer->id}/fee-rate", ['fee_rate' => 0.01])
        ->assertForbidden();

    expect($this->customer->fresh()?->fee_rate)->toBeNull();
});

test('metrics requires admin role', function () {
    Sanctum::actingAs($this->driver);

    $this->getJson('/api/admin/operations/metrics')->assertForbidden();
});

test('관리자는 사용자 역할(기사↔등록자)을 변경할 수 있다', function () {
    // id=1은 루트로 간주되므로 일반 관리자는 루트가 아닌 id로 만든다
    $actorAdmin = User::factory()->create(['id' => 98, 'role' => User::ROLE_ADMIN, 'name' => '일반관리자']);
    Sanctum::actingAs($actorAdmin);

    // 기사 → 등록자 전환 (테스트용·역할 복구 시나리오)
    $this->patchJson("/api/admin/users/{$this->driver->id}/role", ['role' => User::ROLE_CUSTOMER])
        ->assertOk()
        ->assertJsonPath('data.role', User::ROLE_CUSTOMER)
        ->assertJsonPath('data.role_label', '등록자');

    expect($this->driver->fresh()?->role)->toBe(User::ROLE_CUSTOMER);
    expect($this->driver->notifications()->where('data->title', '계정 역할 변경')->count())->toBe(1);

    // 등록자 → 기사로 복구
    $this->patchJson("/api/admin/users/{$this->driver->id}/role", ['role' => User::ROLE_DRIVER])
        ->assertOk()
        ->assertJsonPath('data.role', User::ROLE_DRIVER);

    expect($this->driver->fresh()?->role)->toBe(User::ROLE_DRIVER);
});

test('일반 관리자는 Admin·Super Admin 역할을 지정할 수 없고 비관리자는 역할 변경을 못한다', function () {
    // id=1은 루트로 간주되므로 일반 관리자는 루트가 아닌 id로 만든다
    $actorAdmin = User::factory()->create(['id' => 98, 'role' => User::ROLE_ADMIN, 'name' => '일반관리자']);
    Sanctum::actingAs($actorAdmin);

    $this->patchJson("/api/admin/users/{$this->driver->id}/role", ['role' => User::ROLE_ADMIN])->assertForbidden();
    $this->patchJson("/api/admin/users/{$this->driver->id}/role", ['role' => User::ROLE_SUPER_ADMIN])->assertForbidden();

    // 같은 역할로는 변경 불가
    $this->patchJson("/api/admin/users/{$this->driver->id}/role", ['role' => User::ROLE_DRIVER])->assertStatus(422);

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/admin/users/{$this->customer->id}/role", ['role' => User::ROLE_CUSTOMER])->assertForbidden();
});

test('audit log records admin interventions and lists newest first with labels', function () {
    $order = publishedOrder($this->customer);

    // 비관리자는 감사 로그에 접근 불가
    Sanctum::actingAs($this->driver);
    $this->getJson('/api/admin/operations/audit')->assertForbidden();

    // 관리자 개입 3건 — 사용자 제재·운행 숨김·역할 변경
    Sanctum::actingAs($this->admin);
    $this->patchJson("/api/admin/users/{$this->driver->id}/moderation", [
        'status' => 'watch',
        'note' => '분쟁 다발로 주의',
    ])->assertOk();

    $this->postJson("/api/admin/orders/{$order->id}/hide", [
        'hidden' => true,
        'reason' => '중복 등록',
    ])->assertOk();

    $this->patchJson("/api/admin/users/{$this->customer->id}/role", ['role' => User::ROLE_DRIVER])->assertOk();

    // 역할 변경은 기존 상태(등록자) 기준으로 기록되어야 한다
    $this->patchJson("/api/admin/users/{$this->customer->id}/role", ['role' => User::ROLE_CUSTOMER])->assertOk();

    $this->getJson('/api/admin/operations/audit')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.action', 'user.role-change')
        ->assertJsonPath('data.0.action_label', '역할 변경')
        ->assertJsonPath('data.0.admin_name', '운영자')
        ->assertJsonPath('data.0.meta.before', User::ROLE_DRIVER)
        ->assertJsonPath('data.0.meta.after', User::ROLE_CUSTOMER)
        ->assertJsonPath('data.1.action', 'user.role-change')
        ->assertJsonPath('data.2.action', 'order.hide')
        ->assertJsonPath('data.2.action_label', '운행 숨김')
        ->assertJsonPath('data.2.message', "운행을 숨겼습니다 ({$order->order_number}) 사유: 중복 등록")
        ->assertJsonPath('data.3.action', 'user.moderation')
        ->assertJsonPath('data.3.action_label', '사용자 제재')
        ->assertJsonPath('data.3.meta.user_id', $this->driver->id);
});

test('관리자는 실패한 유입 목록에서 원문과 결손 칸을 확인한다', function () {
    // 파서가 도착지를 못 뽑아 등록이 막힌 일괄 유입
    OrderIngestion::query()->create([
        'user_id' => $this->admin->id,
        'endpoint' => 'orders/batch',
        'status' => OrderIngestion::STATUS_FAILED,
        'error' => '1번째 운행 — 원문에서 찾지 못한 항목(도착지)이 있어 등록하지 않았습니다.',
        'payload' => [
            'orders' => [
                [
                    'pickup_location' => '인천공항 T1',
                    'dropoff_location' => null,
                    'service_date' => '2026-10-01',
                    'service_time' => '09:30',
                    'original_summary' => '9:30 包车',
                ],
            ],
        ],
    ]);

    // 정상 처리된 유입은 목록에 뜨지 않는다
    OrderIngestion::query()->create([
        'user_id' => $this->admin->id,
        'endpoint' => 'orders',
        'status' => OrderIngestion::STATUS_PROCESSED,
        'order_ids' => [1],
        'payload' => ['pickup_location' => '서울역', 'dropoff_location' => '인천공항'],
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/operations/ingestions')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.endpoint', 'orders/batch')
        ->assertJsonPath('data.items.0.sent_by', '운영자')
        ->assertJsonPath('data.items.0.rows.0.pickup', '인천공항 T1')
        ->assertJsonPath('data.items.0.rows.0.dropoff', null)
        ->assertJsonPath('data.items.0.rows.0.service_time', '09:30')
        ->assertJsonPath('data.items.0.rows.0.summary', '9:30 包车');
});

test('일괄이 아닌 단일 유입도 한 건으로 결손 칸이 드러난다', function () {
    OrderIngestion::query()->create([
        'user_id' => $this->admin->id,
        'endpoint' => 'orders',
        'status' => OrderIngestion::STATUS_FAILED,
        'error' => '원문에서 찾지 못한 항목(시간)이 있어 등록하지 않았습니다.',
        'payload' => [
            'pickup_location' => '명동',
            'dropoff_location' => '인천공항',
            'original_summary' => '명동 → 공항',
        ],
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/operations/ingestions')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.rows.0.pickup', '명동')
        ->assertJsonPath('data.items.0.rows.0.service_time', null);
});

test('비관리자는 실패한 유입 목록을 볼 수 없다', function () {
    Sanctum::actingAs($this->driver);

    $this->getJson('/api/admin/operations/ingestions')->assertForbidden();
});
