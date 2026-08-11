<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard hub shows shared form module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('폼')
        ->assertSee(route('dashboard.modules.forms'), false);
});

test('authenticated users can access shared form dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.forms'))
        ->assertSuccessful()
        ->assertSee('폼 유형 현황')
        ->assertSee('기본 입력')
        ->assertSee('날짜·시간·요일')
        ->assertSee('서비스 날짜')
        ->assertSee('출발 시간')
        ->assertSee('운행 요일')
        ->assertSee('셀렉트')
        ->assertSee('텍스트에어리어')
        ->assertSee('체크박스')
        ->assertSee('폼 그룹')
        ->assertSee('폼 상태')
        ->assertSee('액션 폼')
        ->assertSee('확인 포인트')
        ->assertDontSee('DataTable Playground');
});
