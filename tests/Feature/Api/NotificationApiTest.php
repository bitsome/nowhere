<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create();
    Sanctum::actingAs($this->driver);
});

function makeNotification(User $user, array $data = [], ?string $readAt = null): DatabaseNotification
{
    return DatabaseNotification::unguarded(fn () => DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\OrderNotification',
        'notifiable_id' => $user->id,
        'notifiable_type' => User::class,
        'data' => array_merge(['title' => '새 오더 도착', 'message' => '내용'], $data),
        'read_at' => $readAt,
    ]));
}

test('api notifications lists notifications with unread count', function () {
    makeNotification($this->driver, ['title' => '새 오더 도착', 'order_id' => 3]);

    $read = makeNotification($this->driver, ['title' => '읽은 알림'], now()->subHour());
    $read->update(['created_at' => now()->subHour()]);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('total', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', '새 오더 도착')
        ->assertJsonPath('data.0.read', false)
        ->assertJsonPath('data.0.order_id', 3)
        ->assertJsonPath('data.1.read', true);
});

test('api notifications marks read by ids', function () {
    $notification = makeNotification($this->driver);

    $this->postJson('/api/notifications/read', ['ids' => [$notification->id]])
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    expect($notification->fresh()?->read_at)->not->toBeNull();
});

test('api notifications marks all read', function () {
    makeNotification($this->driver);
    makeNotification($this->driver, ['title' => '두 번째']);

    $this->postJson('/api/notifications/read', ['all' => true])
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    expect($this->driver->unreadNotifications()->count())->toBe(0);
});
