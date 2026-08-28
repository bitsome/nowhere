<?php

use App\Models\ChatImageUpload;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('api chat send accepts an image attachment', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);

    $response = $this->post("/api/chats/{$conversation->id}/messages", [
        'body' => '사진입니다',
        'image' => UploadedFile::fake()->image('photo.jpg', 10, 10),
    ])->assertCreated();

    expect($response->json('data.image_url'))->not->toBeNull();

    Storage::disk('public')->assertExists(Message::latest('id')->first()->image_path);
});

test('api chat send requires body or image', function () {
    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);

    $this->postJson("/api/chats/{$conversation->id}/messages", ['body' => ''])
        ->assertStatus(422);
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

test('api chat image archive returns only my images', function () {
    Storage::fake('public');

    $myConversation = Conversation::create();
    $myConversation->users()->attach([$this->driver->id, $this->partner->id]);
    $myConversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '내 사진',
        'image_path' => 'chat/my-photo.jpg',
    ]);
    $myConversation->messages()->create([
        'user_id' => $this->partner->id,
        'body' => '상대 사진',
        'image_path' => 'chat/partner-photo.jpg',
    ]);

    $this->getJson('/api/chats/images/archive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.url', '/api/chat/images/my-photo.jpg');
});

test('api chat image archive deduplicates by image path', function () {
    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '첫 번째',
        'image_path' => 'chat/duplicate.jpg',
    ]);
    $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '두 번째',
        'image_path' => 'chat/duplicate.jpg',
    ]);

    $this->getJson('/api/chats/images/archive')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('api chat image archive paginates 24 per page with meta', function () {
    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);

    for ($i = 0; $i < 30; $i++) {
        $conversation->messages()->create([
            'user_id' => $this->driver->id,
            'body' => "사진 {$i}",
            'image_path' => "chat/photo-{$i}.jpg",
        ]);
    }

    $this->getJson('/api/chats/images/archive?page=1')
        ->assertOk()
        ->assertJsonCount(24, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 30);

    $this->getJson('/api/chats/images/archive?page=2')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

test('api chat send deduplicates identical images by fingerprint', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    $file = UploadedFile::fake()->image('photo.jpg', 10, 10);

    // 같은 내용 이미지를 두 번 업로드 — 동일 지문(해시)으로 파일명이 같아 중복 저장이 일어나지 않는다
    $this->post("/api/chats/{$conversation->id}/messages", ['body' => '첫 번째', 'image' => $file])
        ->assertCreated();
    $first = Message::latest('id')->first();

    $this->post("/api/chats/{$conversation->id}/messages", ['body' => '두 번째', 'image' => $file])
        ->assertCreated();
    $second = Message::latest('id')->first();

    expect($second->image_path)->toBe($first->image_path);
    expect(Storage::disk('public')->allFiles('chat'))->toHaveCount(1);
});

test('api chat image upload stores image and returns fingerprint', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('photo.jpg', 10, 10);

    $this->post('/api/chats/images', ['image' => $file])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['image_path', 'url']]);

    $imagePath = $this->post('/api/chats/images', ['image' => $file])->json('data.image_path');

    expect(Storage::disk('public')->exists($imagePath))->toBeTrue();
});

test('api chat send groups multiple image_paths into one message', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/a.jpg', 'a');
    Storage::disk('public')->put('chat/b.jpg', 'b');
    $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '첫 사진',
        'image_path' => 'chat/a.jpg',
    ]);
    $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '둘째 사진',
        'image_path' => 'chat/b.jpg',
    ]);

    // 여러 장을 한 개 말풍선으로 묶어 보낸다
    $this->postJson("/api/chats/{$conversation->id}/messages", [
        'body' => '묶음 전송',
        'image_paths' => ['chat/a.jpg', 'chat/b.jpg'],
    ])->assertCreated();

    $message = Message::latest('id')->first();

    expect($message->image_paths)->toBe(['chat/a.jpg', 'chat/b.jpg']);
    expect($message->image_path)->toBe('chat/a.jpg');

    $serialized = $this->getJson("/api/chats/{$conversation->id}")->json('data');
    $bundle = collect($serialized)->firstWhere('id', $message->id);

    expect($bundle['images'])->toBe([
        '/api/chat/images/a.jpg',
        '/api/chat/images/b.jpg',
    ]);
    expect($bundle['image_url'])->toBe('/api/chat/images/a.jpg');
});

test('api chat send allows freshly uploaded images before any message references them', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);

    // 선택 즉시 업로드(전송 전 단계) — 아직 이 지문을 참조하는 메시지는 없다
    $pathA = $this->post('/api/chats/images', ['image' => UploadedFile::fake()->image('a.jpg', 10, 10)])->json('data.image_path');
    $pathB = $this->post('/api/chats/images', ['image' => UploadedFile::fake()->image('b.jpg', 12, 12)])->json('data.image_path');

    // 업로드한 사진을 곧바로 묶음 전송한다
    $this->postJson("/api/chats/{$conversation->id}/messages", [
        'image_paths' => [$pathA, $pathB],
    ])->assertCreated();

    $message = Message::latest('id')->first();

    expect($message->image_paths)->toBe([$pathA, $pathB]);
    expect(Storage::disk('public')->exists($pathA))->toBeTrue();

    // 전송 후에는 업로드 소유권 기록이 정리된다 (메시지가 참조를 대신한다)
    expect(ChatImageUpload::query()->count())->toBe(0);
});

test('api chat deletes my own message and cleans unreferenced image file', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/only.jpg', 'only');
    $message = $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '지울 사진',
        'image_path' => 'chat/only.jpg',
        'image_paths' => ['chat/only.jpg'],
    ]);

    $this->deleteJson("/api/chats/{$conversation->id}/messages/{$message->id}")
        ->assertOk()
        ->assertJsonPath('data', true);

    expect(Message::find($message->id))->toBeNull();
    expect(Storage::disk('public')->exists('chat/only.jpg'))->toBeFalse();
});

test('api chat keeps image file when another message still references it', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/shared.jpg', 'shared');
    $first = $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '첫 메시지',
        'image_path' => 'chat/shared.jpg',
    ]);
    $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '둘째 메시지',
        'image_path' => 'chat/shared.jpg',
    ]);

    $this->deleteJson("/api/chats/{$conversation->id}/messages/{$first->id}")->assertOk();

    expect(Storage::disk('public')->exists('chat/shared.jpg'))->toBeTrue();
});

test('api chat cannot delete another users message', function () {
    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    $message = $conversation->messages()->create([
        'user_id' => $this->partner->id,
        'body' => '상대 메시지',
    ]);

    $this->deleteJson("/api/chats/{$conversation->id}/messages/{$message->id}")->assertForbidden();

    expect(Message::find($message->id))->not->toBeNull();
});

test('api chat send reuses my previous image via image_path', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);

    $sent = $this->post("/api/chats/{$conversation->id}/messages", [
        'body' => '사진',
        'image' => UploadedFile::fake()->image('photo.jpg', 10, 10),
    ])->assertCreated();
    $first = Message::latest('id')->first();

    // 보관함에서 고른 이미지는 파일 재업로드 없이 image_path만으로 바로 전송된다
    $reuse = $this->postJson("/api/chats/{$conversation->id}/messages", [
        'body' => '다시 보냄',
        'image_path' => $first->image_path,
    ])->assertCreated();

    expect($reuse->json('data.image_url'))->toBe($sent->json('data.image_url'));
    expect(Message::latest('id')->first()->image_path)->toBe($first->image_path);
    expect(Storage::disk('public')->allFiles('chat'))->toHaveCount(1);
});

test('api chat send rejects reusing another users image_path', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/partner.jpg', 'x');
    $conversation->messages()->create([
        'user_id' => $this->partner->id,
        'body' => '상대 사진',
        'image_path' => 'chat/partner.jpg',
    ]);

    // 남의 이미지 경로로는 재사용할 수 없다
    $this->postJson("/api/chats/{$conversation->id}/messages", [
        'body' => '도용 시도',
        'image_path' => 'chat/partner.jpg',
    ])->assertStatus(422);
});

test('api chat archive image can be deleted', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/photo.jpg', 'photo');
    $message = $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '사진',
        'image_path' => 'chat/photo.jpg',
    ]);

    $this->deleteJson("/api/chats/images/archive/{$message->id}")
        ->assertOk()
        ->assertJsonPath('data', true);

    // 내 메시지의 이미지 참조가 제거되고 파일도 함께 정리된다
    expect($message->fresh()?->image_path)->toBeNull();
    Storage::disk('public')->assertMissing('chat/photo.jpg');
});

test('api chat archive image delete keeps file when another message still references it', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/shared.jpg', 'photo');
    $message = $conversation->messages()->create([
        'user_id' => $this->driver->id,
        'body' => '첫 번째',
        'image_path' => 'chat/shared.jpg',
    ]);
    $conversation->messages()->create([
        'user_id' => $this->partner->id,
        'body' => '같은 사진',
        'image_path' => 'chat/shared.jpg',
    ]);

    $this->deleteJson("/api/chats/images/archive/{$message->id}")->assertOk();

    // 내 참조만 제거되고, 다른 메시지가 쓰는 파일은 남는다
    expect($message->fresh()?->image_path)->toBeNull();
    Storage::disk('public')->assertExists('chat/shared.jpg');
});

test('api chat archive image delete forbids deleting others messages', function () {
    Storage::fake('public');

    $conversation = Conversation::create();
    $conversation->users()->attach([$this->driver->id, $this->partner->id]);
    Storage::disk('public')->put('chat/partner.jpg', 'photo');
    $message = $conversation->messages()->create([
        'user_id' => $this->partner->id,
        'body' => '상대 사진',
        'image_path' => 'chat/partner.jpg',
    ]);

    $this->deleteJson("/api/chats/images/archive/{$message->id}")->assertForbidden();

    Storage::disk('public')->assertExists('chat/partner.jpg');
});
