<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared button dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.buttons'))
        ->assertSuccessful()
        ->assertSee('아이콘 현황')
        ->assertSee('상태 유형')
        ->assertSee('disabled')
        ->assertSee('loading')
        ->assertSee('icon only')
        ->assertSee('data-button-playground', false)
        ->assertDontSee('카드 유형 현황');
});
