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
        ->assertSee('비즈니스 모듈')
        ->assertSee('5개 모듈')
        ->assertSee('컴포넌트 데모')
        ->assertSee('2개 모듈')
        ->assertSee('알림')
        ->assertSee('NoWhere 비즈니스 허브')
        ->assertSee('오더 관리')
        ->assertSee('데이터 테이블')
        ->assertSee('에디터')
        ->assertSee('다이얼로그')
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
