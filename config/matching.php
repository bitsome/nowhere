<?php

// 운행별 적합 기사 자동 매칭 랭킹 설정 — 구현: app/Services/DriverMatchRanker.php
// 배점 기준: docs/OPERATIONS.md '자동 매칭 점수 (운행별 적합 기사 상위 N명 추천)'

return [
    /*
    | 적합도 점수 배점(합계 100).
    | - vehicle    : 운행 차량 요건과 기사 보유 차량 일치
    | - distance   : 픽업지가 기사 '현재 활동 권역'(최근 하차지/자주 가는 지역)과 가까움
    | - time       : 운행 시각이 기사 활동 시간대(매칭 설정 창/운행 이력 시간대) 안
    | - route      : 같은 날 진행 중(수락/운행중) 일정과 픽업·하차 권역이 이어짐(동선)
    | - preference : 활성 매칭 설정 조건 충족
    | - rating     : 기사가 받은 리뷰 평점 (데이터 없으면 중립값 사용)
    */
    'weights' => [
        'vehicle' => 25,
        'distance' => 25,
        'time' => 20,
        'route' => 15,
        'preference' => 10,
        'rating' => 5,
    ],

    /*
    | 한 번에 내려줄 상위 적합 기사 수.
    */
    'top_n' => (int) env('AUTO_MATCH_TOP_N', 5),

    /*
    | 기사 리뷰가 없을 때 평점 중립값 (1~5) — 신규 기사가 평점 때문에
    | 랭킹에서 부당하게 밀리지 않도록 한다.
    */
    'neutral_rating' => 3.0,
];
