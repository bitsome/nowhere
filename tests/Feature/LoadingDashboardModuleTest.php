<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared loading dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.loading'))
        ->assertSuccessful()
        ->assertSee('로딩 · 빈 상태 유형 현황')
        ->assertSee('인라인 로딩')
        ->assertSee('전체 화면 로딩')
        ->assertSee('빈 상태')
        ->assertSee('data-loading-playground', false)
        ->assertSee('datatable-empty', false)
        ->assertDontSee('카드 유형 현황');
});
