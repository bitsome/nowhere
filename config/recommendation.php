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
];
