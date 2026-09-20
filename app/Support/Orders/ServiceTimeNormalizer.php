<?php

namespace App\Support\Orders;

/**
 * 운행 시각(`service_time`) 문자열을 항상 두 자리 시(hour) `HH:MM` 으로 맞춘다.
 *
 * 이 값은 문자열 컬럼이고, 마켓 노출 컷·자동 취소·리마인더가 전부 문자열로 비교한다
 * (`service_time >= '14:02'`). `7:30` 처럼 한 자리 시각이 저장되면 `'7' > '1'` 이라
 * 이미 지난 운행이 마켓 목록에 남고 자동 취소에서도 빠져나간다. 정렬도 같은 원인으로 어긋난다.
 *
 * 저장 경로(모델 mutator)와 문구 구조화가 같은 규칙을 쓰도록 한 곳에 모아 둔다.
 */
class ServiceTimeNormalizer
{
    /**
     * 한 자리 시각을 0으로 채우고 구분자를 `:` 로 통일한다. 읽지 못한 값은 그대로 돌려준다.
     */
    public static function normalize(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $normalized = trim($time);

        if ($normalized === '') {
            return '';
        }

        // `7:30`·`7.30`·`7时30`
        if (preg_match('/^(?<hour>\d{1,2})[:.时](?<minute>\d{2})$/u', $normalized, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        // `730` — 구분자 없이 붙여 쓴 표기
        if (preg_match('/^(?<hour>\d{1,2})(?<minute>\d{2})$/', $normalized, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        // `7点`
        if (preg_match('/^(?<hour>\d{1,2})\s*点$/u', $normalized, $matches) === 1) {
            return sprintf('%02d:00', (int) $matches['hour']);
        }

        return $normalized;
    }
}
