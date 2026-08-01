<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows minimal module focused layout', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('모듈 허브')
        ->assertSee('현재 테스트 가능한 페이지')
        ->assertSee('7개 모듈')
        ->assertSee('Notification 테스트')
        ->assertSee('Shared DataTable 테스트')
        ->assertSee('Toast UI Editor 테스트')
        ->assertDontSee('현재 운영 상태')
        ->assertDontSee('완료된 기반')
        ->assertDontSee('운영 규칙');
});

test('dashboard hub layout renders shared flash toast marker for status messages', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->withSession([
            'status' => '게시글이 등록되었습니다.',
        ])
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-toast-flash', false)
        ->assertSee('게시글이 등록되었습니다.');
});
