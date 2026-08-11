<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared toast dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.toast'))
        ->assertSuccessful()
        ->assertSee('토스트 유형 현황')
        ->assertSee('안내(info)')
        ->assertSee('완료(success)')
        ->assertSee('오류(error)')
        ->assertSee('data-toast-playground', false)
        ->assertSee('toast-card', false)
        ->assertDontSee('카드 유형 현황');
});
