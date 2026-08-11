<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows nowhere business module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('NoWhere 비즈니스 허브')
        ->assertSee(route('dashboard.business.nowhere'), false);
});

test('nowhere dashboard module page renders business hub cards', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.nowhere'))
        ->assertSuccessful()
        ->assertSee('핵심 비즈니스 모듈')
        ->assertSee('오더 관리')
        ->assertSee('배차 관리')
        ->assertSee('정산 관리')
        ->assertSee('Business Foundation 우선')
        ->assertSee('data-dashboard-nowhere-hub', false);
});
