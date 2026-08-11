<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared modal dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.modal'))
        ->assertSuccessful()
        ->assertSee('공용 모달 현황')
        ->assertSee('모달 구조 유형')
        ->assertSee('BaseModal', false)
        ->assertSee('data-modal-playground', false)
        ->assertDontSee('리스트 유형 현황');
});
