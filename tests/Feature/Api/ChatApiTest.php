<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create();
    $this->partner = User::factory()->create();
    Sanctum::actingAs($this->driver);
});

test('api chats lists conversations with counterpart and unread count', function () {
    $conversation = Conversation::create(['last_message_at' => now()]);
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    $conversation->messages()->create(['user_id' => $this->partner->id, 'body' => '안녕하세요']);
    $conversation->messages()->create(['user_id' => $this->partner->id, 'body' => '확인 부탁드립니다']);

    $this->getJson('/api/chats')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.counterpart.name', $this->partner->name)
        ->assertJsonPath('data.0.unread_count', 2)
        ->assertJsonPath('data.0.last_message.body', '확인 부탁드립니다');
});

test('api chats shows messages and marks them read', function () {
    $conversation = Conversation::create(['last_message_at' => now()]);
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    $conversation->messages()->create(['user_id' => $this->partner->id, 'body' => '안녕하세요']);

    $this->getJson("/api/chats/{$conversation->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', '안녕하세요');

    expect(Message::query()->whereNull('read_at')->count())->toBe(0);
});

test('api chats sends a message', function () {
    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);

    $this->postJson("/api/chats/{$conversation->id}/messages", ['body' => '네 가능합니다'])
        ->assertCreated()
        ->assertJsonPath('data.body', '네 가능합니다');

    expect($conversation->fresh()?->last_message_at)->not->toBeNull();
    expect(Message::query()->where('user_id', $this->driver->id)->count())->toBe(1);
});

test('api chats forbids access to conversations I am not in', function () {
    $other = User::factory()->create();
    $conversation = Conversation::create();
    $conversation->users()->attach([$this->partner->id, $other->id]);

    $this->getJson("/api/chats/{$conversation->id}")->assertForbidden();
    $this->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'hi'])->assertForbidden();
});

test('api chats creates a new conversation', function () {
    $this->postJson('/api/chats', ['user_id' => $this->partner->id])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);

    expect($this->driver->conversations()->count())->toBe(1);
    expect($this->partner->conversations()->count())->toBe(1);
});
