<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index page shows the existing test page for guests', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('NoWhere 운영 시스템 시작 화면')
        ->assertSee('로그인')
        ->assertSee('회원가입');
});

test('index page redirects authenticated users to the independent frontend (SPA)', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(config('app.frontend_url'));
});
