<?php

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => '운영자']);
    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'name' => '김기사']);
    $this->customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => '홍등록']);
});

function reportOrder(User $owner): Order
{
    return Order::factory()->create([
        'user_id' => $owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
        'amount_value' => 80000,
    ]);
}

test('report options expose targets, per-target categories and statuses', function () {
    Sanctum::actingAs($this->driver);

    $this->getJson('/api/reports/options')
        ->assertOk()
        ->assertJsonPath('data.targets.order', '운행')
        ->assertJsonPath('data.targets.user', '사용자')
        ->assertJsonPath('data.targets.chat', '채팅')
        ->assertJsonPath('data.categories.order.no_show', '노쇼')
        // 채팅 대상은 채팅·서비스·기타 유형만
        ->assertJsonMissingPath('data.categories.chat.no_show')
        ->assertJsonPath('data.categories.chat.other', '기타')
        ->assertJsonPath('data.statuses.pending', '접수')
        ->assertJsonPath('data.statuses.completed', '완료');
});

test('a driver can report another owners order and admins are notified', function () {
    $order = reportOrder($this->customer);

    Sanctum::actingAs($this->driver);

    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'no_show',
        'reason' => '노쇼로 연락이 두 번 모두 되지 않았습니다.',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.category_label', '노쇼')
        ->assertJsonPath('data.target.route', '인천공항 T1 → 강남')
        ->assertJsonPath('data.reporter.name', '김기사');

    $report = Report::query()->firstOrFail();

    expect($report->target_type)->toBe('order');
    expect($report->subject_user_id)->toBeNull();

    // 관리자에게 접수 알림이 간다
    expect($this->admin->notifications()->count())->toBe(1);
    expect($this->admin->notifications()->first()->data['title'])->toBe('신고 접수');
});

test('a user cannot report their own order', function () {
    $order = reportOrder($this->customer);

    Sanctum::actingAs($this->customer);

    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'fee',
        'reason' => '금액이 계약과 다릅니다.',
    ])->assertStatus(422);

    expect(Report::query()->count())->toBe(0);
});

test('a chat participant can report the conversation and the counterpart is the subject', function () {
    $order = reportOrder($this->customer);
    $conversation = Conversation::query()->create(['order_id' => $order->id]);
    $conversation->users()->attach([$this->customer->id, $this->driver->id]);

    Sanctum::actingAs($this->customer);

    $this->postJson('/api/reports', [
        'target_type' => 'chat',
        'target_id' => $conversation->id,
        'category' => 'chat',
        'reason' => '상대가 채팅에서 욕설과 폭언을 반복합니다.',
    ])->assertCreated()
        ->assertJsonPath('data.subject.name', '김기사')
        ->assertJsonPath('data.target_label', '채팅');
});

test('chat reports are only allowed for participants', function () {
    $order = reportOrder($this->customer);
    $conversation = Conversation::query()->create(['order_id' => $order->id]);
    $conversation->users()->attach([$this->customer->id]);
    $outsider = User::factory()->create(['role' => User::ROLE_DRIVER, 'name' => '외부기사']);

    Sanctum::actingAs($outsider);

    $this->postJson('/api/reports', [
        'target_type' => 'chat',
        'target_id' => $conversation->id,
        'category' => 'chat',
        'reason' => '대화 내용에 문제가 있어 신고합니다.',
    ])->assertStatus(422);
});

test('a driver can report a customer, but not themselves or staff', function () {
    Sanctum::actingAs($this->driver);

    $this->postJson('/api/reports', [
        'target_type' => 'user',
        'target_id' => $this->customer->id,
        'category' => 'service',
        'reason' => '서비스가 불성실해 신고합니다.',
    ])->assertCreated()
        ->assertJsonPath('data.subject.id', $this->customer->id)
        ->assertJsonPath('data.subject.role_label', '등록자');

    // 본인 신고 금지
    $this->postJson('/api/reports', [
        'target_type' => 'user',
        'target_id' => $this->driver->id,
        'category' => 'other',
        'reason' => '본인을 실수로 눌렀습니다.',
    ])->assertStatus(422);

    // 관리자(직원) 신고 금지
    $this->postJson('/api/reports', [
        'target_type' => 'user',
        'target_id' => $this->admin->id,
        'category' => 'other',
        'reason' => '관리자를 신고합니다.',
    ])->assertStatus(422);
});

test('admins can list reports and move them forward along the process flow', function () {
    $order = reportOrder($this->customer);

    Sanctum::actingAs($this->driver);
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'cancel',
        'reason' => '등록자가 일방적으로 취소를 요구했습니다.',
    ])->assertCreated();

    $report = Report::query()->firstOrFail();

    // 일반 기사는 관리자 목록에 접근할 수 없다
    $this->getJson('/api/admin/reports')->assertForbidden();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/reports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reporter.name', '김기사');

    // 접수 → 확인 → 처리(완료 단계로 점프) — 뒤로는 갈 수 없다
    $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'reviewing'])->assertOk();
    $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'pending'])->assertStatus(422);

    $this->patchJson("/api/admin/reports/{$report->id}", [
        'status' => 'handled',
        'note' => '운행 타임라인을 확인해 처리했습니다.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'handled')
        ->assertJsonPath('data.processed_by_name', '운영자')
        ->assertJsonPath('data.note', '운행 타임라인을 확인해 처리했습니다.');

    // 상태 필터 목록에서도 확인된다
    $this->getJson('/api/admin/reports?status=handled')->assertJsonCount(1, 'data');
    $this->getJson('/api/admin/reports?status=pending')->assertJsonCount(0, 'data');

    // 완료된 신고는 더 이상 진행할 수 없다
    $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'completed'])->assertOk();
    $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'completed'])->assertStatus(422);
});

test('reporters are notified once when their report reaches a terminal stage', function () {
    // 기사가 등록한(소유한) 운행을 등록자 입장에서 신고 — 내 운행 신고 금지 규칙과 무관하게 남의 운행
    $order = reportOrder($this->driver);

    Sanctum::actingAs($this->customer);
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'fee',
        'reason' => '금액을 정산하지 않아 신고합니다.',
    ])->assertCreated();

    $report = Report::query()->firstOrFail();

    Sanctum::actingAs($this->admin);

    // 조사 → 처리로 이동한 순간 신고자에게 알림 1건
    $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'investigating'])->assertOk();
    expect($this->customer->notifications()->count())->toBe(0);

    $this->patchJson("/api/admin/reports/{$report->id}", [
        'status' => 'handled',
        'note' => '등록자에게 지급 안내 후 종결했습니다.',
    ])->assertOk();

    expect($this->customer->notifications()->count())->toBe(1);
    expect($this->customer->notifications()->first()->data['title'])->toBe('신고 처리 결과');

    // 완료로 올려도 중복 알림은 없다
    $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'completed'])->assertOk();
    expect($this->customer->notifications()->count())->toBe(1);
});

test('report validation rejects missing or malformed input', function () {
    Sanctum::actingAs($this->driver);

    $order = reportOrder($this->customer);

    // 유형 누락
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'fee',
    ])->assertStatus(422);

    // 존재하지 않는 운행
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => 999999,
        'category' => 'fee',
        'reason' => '금액 문제로 신고합니다.',
    ])->assertStatus(422);

    // 운행에 없는 유형
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'nope',
        'reason' => '잘못된 유형입니다.',
    ])->assertStatus(422);

    // 이유가 너무 짧음
    $this->postJson('/api/reports', [
        'target_type' => 'order',
        'target_id' => $order->id,
        'category' => 'fee',
        'reason' => '짧음',
    ])->assertStatus(422);

    expect(Report::query()->count())->toBe(0);
});
