<?php

return [
    // 완료된 운행이 자동 정산되기까지의 유예 시간(시간) — 이 안에 등록자가 문제를 제기하거나
    // 보류를 요청할 수 있다. 유예가 지나면 정상 운행은 시스템이 자동 정산한다.
    'auto_after_hours' => (int) env('SETTLEMENT_AUTO_AFTER_HOURS', 24),

    // 플랫폼 수수료율 — 운행금액 대비 비율 (0.05 = 5%). 운영 중 요율 조정은 이 값만 바꾼다.
    // 정산 원장에는 정산 시점의 요율이 스냅샷으로 남아 과거 정산 금액은 바뀌지 않는다.
    'fee_rate' => (float) env('SETTLEMENT_FEE_RATE', 0.05),

    // 최소 수수료(원) — 소액 운행에서도 최소 이 금액은 부과한다. 0이면 미적용.
    'min_fee' => (int) env('SETTLEMENT_MIN_FEE', 0),

    // 플랫폼 매입 계좌 — 등록자가 운행 대금(gross)을 입금할 계좌.
    // 수동 입금 확인 방식에서 등록자에게 안내하는 계좌 정보이며, 관리자가 입금을 확인해 수금을 확정한다.
    'platform_account' => [
        'bank_name' => env('SETTLEMENT_PLATFORM_BANK_NAME', ''),
        'account_number' => env('SETTLEMENT_PLATFORM_ACCOUNT_NUMBER', ''),
        'account_holder' => env('SETTLEMENT_PLATFORM_ACCOUNT_HOLDER', ''),
    ],
];
