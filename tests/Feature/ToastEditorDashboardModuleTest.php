<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows toast ui editor module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Toast UI Editor 테스트')
        ->assertSee(route('dashboard.modules.editor'), false);
});

test('authenticated users can access toast ui editor dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.editor'))
        ->assertSuccessful()
        ->assertSee('Toast UI Editor / Viewer')
        ->assertSee('Shared Editor Framework')
        ->assertSee('Live Test')
        ->assertSee('Toast UI Editor 테스트')
        ->assertSee('에디터를 불러오는 중입니다.')
        ->assertSee('data-toast-editor-playground', false)
        ->assertSee('data-base-markdown-editor-playground', false);
});
