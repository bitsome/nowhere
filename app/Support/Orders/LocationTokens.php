<?php

namespace App\Support\Orders;

/**
 * 위치 문자열(출발지/도착지)을 구·동 단위 토큰으로 분해하는 공용 유틸.
 *
 * - 공항 터미널/선 표기 차이를 정규화하고('국제', 'T1', '국내선' 등 제거)
 * - 시·군·구 경계 단위로 잘라 ('강남구' → '강남구', '강남')
 * - 시/도명(서울, 인천, 경기 등) 블록리스트는 제거한다
 *
 * 운행 추천·자동 매칭 랭킹에서 "같은 권역(가까운 위치)" 판정에 사용한다.
 */
class LocationTokens
{
    /**
     * 위치 문자열을 권역 토큰 배열로 분해한다.
     *
     * @return array<int, string>
     */
    public static function tokens(string $location): array
    {
        $normalized = (string) preg_replace('/(\bT\d\b|국내선|국제선)/u', '', str_replace('국제', '', $location));
        $parts = preg_split('/[\s>→\-·,()（）\/]+/u', $normalized);

        $tokens = [];

        foreach ((array) $parts as $part) {
            $part = trim($part);

            if (mb_strlen($part) < 2) {
                continue;
            }

            $tokens[] = mb_strtolower($part);

            // '강남구' → '강남' — 상세 구역만으로도 겹치게
            $stripped = preg_replace('/(구|동|읍|면|리)$/u', '', mb_strtolower($part));

            if (mb_strlen((string) $stripped) >= 2) {
                $tokens[] = (string) $stripped;
            }
        }

        $blocklist = [
            '서울', '서울특별시', '부산', '부산광역시', '인천', '인천광역시',
            '대구', '대전', '광주', '울산', '세종',
            '경기', '경기도', '강원', '강원도',
            '충북', '충청북도', '충남', '충청남도',
            '전북', '전라북도', '전남', '전라남도',
            '경북', '경상북도', '경남', '경상남도',
            '제주', '제주도',
        ];

        return array_values(array_unique(array_diff($tokens, $blocklist)));
    }

    /**
     * 두 위치가 같은 권역 토큰을 하나라도 공유하는지.
     */
    public static function sharesZone(string $a, string $b): bool
    {
        return array_intersect(self::tokens($a), self::tokens($b)) !== [];
    }

    /**
     * 한 위치의 토큰이 권역 목록 중 하나와 겹치는지.
     *
     * @param  array<int, string>  $zoneTokens
     */
    public static function touchesAnyZone(string $location, array $zoneTokens): bool
    {
        return array_intersect(self::tokens($location), $zoneTokens) !== [];
    }
}
