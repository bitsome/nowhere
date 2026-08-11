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
        ->assertSee('데이터 테이블')
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
        ->assertSee('목록 프레임워크')
        ->assertSee('체크박스 + 배지 + 액션 버튼 샘플')
        ->assertSee('셀렉트 + 라디오 버튼 + 날짜 선택형 셀 샘플')
        ->assertSee('행 hover 액션 + 드롭다운 메뉴 샘플')
        ->assertSee('실행형 DataTable')
        ->assertSee('data-datatable-playground', false)
        ->assertDontSee('미리보기 테이블')
        ->assertDontSee('1차 구현 범위');
});

test('datatable module page shows sortable header and status cell sample', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.datatable'))
        ->assertSuccessful()
        ->assertSee('정렬 가능 헤더 + 상태 셀 샘플')
        ->assertSee('배차 대기')
        ->assertSee('배차 완료');
});
