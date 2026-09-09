<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index page shows the service intro for guests', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('배차 관리 서비스입니다')
        ->assertSee('로그인');
});

test('index page redirects authenticated users to the independent frontend (SPA)', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(config('app.frontend_url'));
});
