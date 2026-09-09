<?php

use App\Models\SupportPost;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
        'name' => '홍기사',
    ]);

    $this->admin = User::factory()->create([
        'id' => 88,
        'role' => User::ROLE_ADMIN,
        'name' => '관리자',
    ]);

    $this->notice = SupportPost::create([
        'kind' => SupportPost::KIND_NOTICE,
        'title' => '시스템 점검 안내',
        'body' => '9월 6일 새벽 2시부터 3시까지 점검이 있습니다.',
        'author_id' => $this->admin->id,
    ]);

    $this->faq = SupportPost::create([
        'kind' => SupportPost::KIND_FAQ,
        'title' => '운행 수수료는 얼마인가요?',
        'body' => '운행 금액의 5%입니다.',
        'author_id' => $this->admin->id,
    ]);
});

test('user can browse notices and faqs by kind', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/support/posts?kind=notice')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', '시스템 점검 안내');

    $this->getJson('/api/support/posts')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user submits 1:1 inquiry -> open ticket + admin notified', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/support/tickets', [
        'title' => '결제 금액이 이상해요',
        'body' => '어제 완료된 운행 정산 금액이 실제와 다릅니다.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.status_label', '답변 대기');

    expect($this->admin->notifications()->where('data->title', '1:1 문의 접수')->count())->toBe(1);
});

test('my tickets only shows my own inquiries', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/support/tickets', ['title' => '내 문의', 'body' => '본문'])->assertCreated();

    // 다른 사용자의 문의
    $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    SupportTicket::create(['user_id' => $other->id, 'title' => '남의 문의', 'body' => '본문', 'status' => SupportTicket::STATUS_OPEN]);

    $this->getJson('/api/support/tickets/mine')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', '내 문의');
});

test('non admin cannot manage support', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/admin/support/posts')->assertStatus(403);
    $this->getJson('/api/admin/support/tickets')->assertStatus(403);

    $this->postJson('/api/admin/support/posts', ['kind' => 'notice', 'title' => '공지', 'body' => '내용'])
        ->assertStatus(403);
});

test('admin creates/updates/deletes support post', function () {
    Sanctum::actingAs($this->admin);

    $created = $this->postJson('/api/admin/support/posts', [
        'kind' => 'notice',
        'title' => '새 공지',
        'body' => '공지 내용입니다.',
    ])->assertCreated()->json('data');

    $this->patchJson("/api/admin/support/posts/{$created['id']}", [
        'title' => '수정된 공지',
    ])->assertOk()->assertJsonPath('data.title', '수정된 공지');

    $this->deleteJson("/api/admin/support/posts/{$created['id']}")->assertOk();

    expect(SupportPost::query()->find($created['id']))->toBeNull();
});

test('admin answers ticket -> answered + user notified', function () {
    Sanctum::actingAs($this->user);
    $this->postJson('/api/support/tickets', ['title' => '출금 문의', 'body' => '언제 출금되나요?'])->assertCreated();

    $ticketId = SupportTicket::query()->where('user_id', $this->user->id)->firstOrFail()->id;

    Sanctum::actingAs($this->admin);
    $this->patchJson("/api/admin/support/tickets/{$ticketId}/answer", [
        'answer' => '영업일 기준 1~2일 내 지급됩니다.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'answered');

    expect($this->user->notifications()->where('data->title', '1:1 문의 답변')->count())->toBe(1);
});

test('answer requires body and cannot re-answer', function () {
    Sanctum::actingAs($this->user);
    $this->postJson('/api/support/tickets', ['title' => '문의', 'body' => '본문'])->assertCreated();

    $ticketId = SupportTicket::query()->where('user_id', $this->user->id)->firstOrFail()->id;

    Sanctum::actingAs($this->admin);
    $this->patchJson("/api/admin/support/tickets/{$ticketId}/answer", ['answer' => ''])->assertStatus(422);

    $this->patchJson("/api/admin/support/tickets/{$ticketId}/answer", ['answer' => '답변입니다.'])->assertOk();
    $this->patchJson("/api/admin/support/tickets/{$ticketId}/answer", ['answer' => '재답변'])->assertStatus(422);
});

test('admin ticket list prioritizes open tickets', function () {
    Sanctum::actingAs($this->user);
    $this->postJson('/api/support/tickets', ['title' => '대기 문의', 'body' => '본문'])->assertCreated();

    $ticket = SupportTicket::query()->where('user_id', $this->user->id)->firstOrFail();
    $ticket->forceFill(['status' => SupportTicket::STATUS_ANSWERED, 'answer' => '답변', 'answered_at' => now()])->save();

    $this->postJson('/api/support/tickets', ['title' => '새 대기', 'body' => '본문'])->assertCreated();

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/support/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', '새 대기')
        ->assertJsonPath('data.0.user.name', '홍기사');
});
