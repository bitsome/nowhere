<?php

use App\Support\Orders\LocationDistance;

test('출발지와 도착지의 대략 거리를 계산한다', function () {
    // 직선거리에 도로 보정(×1.2)을 곱한 값 — 정밀 측량이 아니라 카드 표시용 추정치다
    expect(LocationDistance::km('인천공항', '명동'))->toBeGreaterThan(52)
        ->toBeLessThan(65)
        ->and(LocationDistance::km('김포공항', '명동'))->toBeGreaterThan(16)
        ->toBeLessThan(26)
        ->and(LocationDistance::km('인천공항 제2터미널', '인스파이어'))->toBeLessThan(10);
});

test('공항으로 쓰이는 표기는 공항 좌표로 본다', function () {
    // 이 시장에서 '공항'·'인천'·'김포'는 공항을 뜻한다
    $airportKm = LocationDistance::km('인천공항', '명동');

    expect(LocationDistance::km('공항', '명동'))->toBe($airportKm)
        ->and(LocationDistance::km('인천', '명동'))->toBe($airportKm)
        ->and(LocationDistance::km('김포공항', '명동'))->not->toBe($airportKm);
});

test('긴 표기가 먼저 맞고, 짧은 표기는 포함 관계로 읽는다', function () {
    // '종로 호텔'은 사전에 없지만 종로 좌표로 읽는다
    expect(LocationDistance::km('종로 호텔', '인천공항'))->toBe(LocationDistance::km('종로구', '인천공항'))
        ->and(LocationDistance::km('인천 인스파이어', '명동'))->toBe(LocationDistance::km('인스파이어', '명동'));
});

test('좌표를 모르는 지명과 이어진 두 지점은 거리를 주지 않는다', function () {
    expect(LocationDistance::km('미정', '명동'))->toBeNull()
        ->and(LocationDistance::km('', '명동'))->toBeNull()
        ->and(LocationDistance::km(null, '명동'))->toBeNull()
        // 한 칸에 두 지점이 이어진 값은 어느 쪽인지 알 수 없다
        ->and(LocationDistance::km('명동—인천', '강남구'))->toBeNull();
});

test('같은 지점끼리는 거리를 주지 않는다', function () {
    expect(LocationDistance::km('명동', '명동'))->toBeNull()
        ->and(LocationDistance::km('강남구', '강남'))->toBeNull();
});

test('거리에서 대략 소요시간(분)을 추정한다', function () {
    // 평균 45km/h 가정 — 저장 컬럼이 비어 있는 실등록 건의 "약 N시간" 표시에 쓴다
    $minutes = LocationDistance::minutes('인천공항', '명동');

    expect($minutes)->toBeGreaterThan(60)
        ->toBeLessThan(100)
        ->and($minutes)->toBe((int) round(LocationDistance::km('인천공항', '명동') / 45 * 60));
});

test('거리를 모르면 소요시간도 주지 않는다', function () {
    expect(LocationDistance::minutes('미정', '명동'))->toBeNull()
        ->and(LocationDistance::minutes('명동—인천', '강남'))->toBeNull()
        ->and(LocationDistance::minutes('명동', '명동'))->toBeNull()
        ->and(LocationDistance::minutes(null, null))->toBeNull();
});
