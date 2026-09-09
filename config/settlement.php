<?php

return [
    // 완료된 운행이 자동 정산되기까지의 유예 시간(시간) — 이 안에 등록자가 문제를 제기하거나
    // 보류를 요청할 수 있다. 유예가 지나면 정상 운행은 시스템이 자동 정산한다.
    'auto_after_hours' => (int) env('SETTLEMENT_AUTO_AFTER_HOURS', 24),
];
