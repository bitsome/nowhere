<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared dropdown dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.dropdown'))
        ->assertSuccessful()
        ->assertSee('Dropdown Playground')
        ->assertSee('클릭 트리거 유형')
        ->assertSee('메뉴 포지션')
        ->assertSee('헤더 · 구분선 · danger 항목')
        ->assertSee('확인 포인트')
        ->assertSee('data-dropdown-types-playground', false)
        ->assertDontSee('폼 유형 현황');
});
