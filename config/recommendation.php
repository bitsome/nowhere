<?php

// 운행 추천 알고리즘 설정

return [
    /*
    | 랜딩(공항 픽업) 대기 시간(분).
    | 랜딩 운행의 service_time은 '항공기 도착 시각'이다. 승객이 입국심사·짐찾기 후
    | 나오는 데 평균 1시간(60분)이 걸리므로, 연결 창 계산에서 이 대기를 더한다.
    | 타이트하게 잡으려면 LANDING_WAIT_MINUTES=30 처럼 줄인다.
    */
    'landing_wait_minutes' => (int) env('LANDING_WAIT_MINUTES', 60),

    /*
    | 샌딩→랜딩 간격(분).
    | 샌딩(공항으로 승객 운송) 직후 랜딩(공항 픽업)으로 이어질 때,
    | 랜딩 운행의 시작 시각(항공기 도착)이 직전 샌딩 시작 후 이 범위에 있어야 연결된다.
    | 승객 퇴장 대기(landing_wait_minutes)가 샌딩 소요를 흡수하므로 타이트하게 잡을 수 있다.
    */
    'send_landing_gap_minutes' => [
        'min' => (int) env('SEND_LANDING_GAP_MIN_MINUTES', 30),
        'max' => (int) env('SEND_LANDING_GAP_MAX_MINUTES', 120),
    ],

    /*
    | 랜딩→샌딩 간격(시간).
    | 랜딩(공항→도심) 운행 후 다음 샌딩(도심→공항)으로 이어질 때,
    | 다음 샌딩의 시작 시각이 직전 랜딩 시작 시각(service_time) 기준 이 범위에 있어야 연결된다.
    | 사용자 규칙: 랜딩 후 다음 운행은 '랜딩 + 3시간 이후'에만 연결한다.
    */
    'landing_send_gap_hours' => [
        'min' => (int) env('LANDING_SEND_GAP_MIN_HOURS', 3),
        'max' => (int) env('LANDING_SEND_GAP_MAX_HOURS', 6),
    ],

    /*
    | 홈 추천 최소 선호도(조건 일치율 %).
    | 이 미만의 운행은 추천에서 제외한다 — '판단을 덜 하게' 원칙.
    | 낮은 적합도 후보를 보여주지 않고 확실한 매칭만 남긴다.
    */
    'min_match_score' => (int) env('RECOMMEND_MIN_MATCH_SCORE', 60),
];
