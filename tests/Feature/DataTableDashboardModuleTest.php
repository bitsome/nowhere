<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows shared datatable module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Shared DataTable 테스트')
        ->assertSee(route('dashboard.modules.datatable'), false);
});

test('authenticated users can access shared datatable dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.datatable'))
        ->assertSuccessful()
        ->assertSee('DataTable Playground')
        ->assertSee('Shared List Framework')
        ->assertSee('체크박스 + 배지 + 액션 버튼 샘플')
        ->assertSee('셀렉트 + 라디오 버튼 + 날짜 선택형 셀 샘플')
        ->assertSee('행 hover 액션 + 드롭다운 메뉴 샘플')
        ->assertSee('실행형 DataTable')
        ->assertSee('data-datatable-playground', false)
        ->assertDontSee('미리보기 테이블')
        ->assertDontSee('1차 구현 범위');
});
