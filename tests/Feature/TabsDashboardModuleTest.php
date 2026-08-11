<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can access shared tab menu dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.tabs'))
        ->assertSuccessful()
        ->assertSee('TabMenu')
        ->assertSee('ViewToggle')
        ->assertSee('마켓')
        ->assertSee('내가 받은 오더')
        ->assertSee('data-order-tabs', false)
        ->assertSee('data-view-toggle-demo', false);
});
