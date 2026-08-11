<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows shared card module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('카드')
        ->assertSee(route('dashboard.modules.cards'), false);
});

test('authenticated users can access shared card dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.cards'))
        ->assertSuccessful()
        ->assertSee('카드 유형 현황')
        ->assertSee('기본 카드')
        ->assertSee('통계 카드')
        ->assertSee('요약 카드')
        ->assertSee('리스트 카드')
        ->assertSee('액션 카드')
        ->assertSee('클릭 카드')
        ->assertSee('상태 카드')
        ->assertSee('확인 포인트')
        ->assertDontSee('DataTable Playground');
});
