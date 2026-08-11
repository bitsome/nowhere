<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows shared list module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('리스트')
        ->assertSee(route('dashboard.modules.lists'), false);
});

test('authenticated users can access shared list dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.lists'))
        ->assertSuccessful()
        ->assertSee('리스트 유형 현황')
        ->assertSee('기본 리스트')
        ->assertSee('구분선 리스트')
        ->assertSee('아이콘 리스트')
        ->assertSee('상태 리스트')
        ->assertSee('호버 액션 리스트')
        ->assertSee('알림 리스트')
        ->assertSee('체크 리스트')
        ->assertSee('확인 포인트')
        ->assertDontSee('DataTable Playground');
});
