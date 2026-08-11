<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows shared dialog module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('다이얼로그')
        ->assertSee(route('dashboard.modules.dialog'), false);
});

test('authenticated users can access shared dialog module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.dialog'))
        ->assertSuccessful()
        ->assertSee('Dialog Playground')
        ->assertSee('data-dialog-playground', false)
        ->assertSee('data-dialog-open', false);
});

test('dialog module page renders blade x-dialog component with confirm and close triggers', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.dialog'))
        ->assertSuccessful()
        ->assertSee('Blade 다이얼로그')
        ->assertSee('role="alertdialog"', false)
        ->assertSee('data-dialog-close', false)
        ->assertSee('data-dialog-confirm', false);
});

test('dialog module page shows size and confirm-only dialog types', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.dialog'))
        ->assertSuccessful()
        ->assertSee('크기 · 확인만 유형')
        ->assertSee('dialog-sm-sample', false)
        ->assertSee('dialog-md-sample', false)
        ->assertSee('dialog-confirm-only', false);
});
