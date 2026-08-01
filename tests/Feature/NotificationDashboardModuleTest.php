<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows notification module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Notification 테스트')
        ->assertSee(route('dashboard.modules.notification'), false);
});

test('notification dashboard module page renders toast mount target', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.notification'))
        ->assertSuccessful()
        ->assertSee('Notification 테스트 워크스페이스')
        ->assertSee('data-dashboard-notification-test', false)
        ->assertSee('data-notification-toast', false)
        ->assertSee('알림 보내기');
});
