<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared alert dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.alert'))
        ->assertSuccessful()
        ->assertSee('알림 배너 유형 현황')
        ->assertSee('안내')
        ->assertSee('완료')
        ->assertSee('경고')
        ->assertSee('오류 발생')
        ->assertSee('role="alert"', false)
        ->assertDontSee('카드 유형 현황');
});
