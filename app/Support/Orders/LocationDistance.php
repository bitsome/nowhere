<?php

namespace App\Support\Orders;

/**
 * 출발지 → 도착지 거리 추정 — 좌표를 내장해 외부 지도 API 없이 계산한다.
 *
 * 운행카드에 "약 Nkm" 로 노출하는 값이라 정밀 측량이 목적이 아니다.
 * 직선거리에 도로 굴곡 보정 계수를 곱해 대략의 도로거리로 맞춘다.
 *
 * 좌표를 모르는 지명(해외·오염된 값·'미정')은 null 을 돌려주고, 카드는 거리를 표시하지 않는다.
 */
final class LocationDistance
{
    /** 도로 굴곡 보정 계수 — 직선거리 × 이 값 ≈ 도로거리(고속도로 구간이 많은 이 시장 기준). */
    private const ROAD_FACTOR = 1.2;

    /** 평균 주행 속도(km/h) — 도심 정체를 감안한 보수적 값. 소요시간은 "약 N시간" 표시용 추정치다. */
    private const AVERAGE_SPEED_KMH = 45;

    /** 지구 반지름(km). */
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * 광역(구·시) 단위 표기 — 좌표는 구 중심점이라 실제 지점은 그 안 어디든 될 수 있다.
     * (예: '마포구'는 마포구청 근처 좌표일 뿐, 공덕동은 여기서 약 4.8km 떨어져 있다)
     *
     * @var array<int, string>
     */
    private const COARSE_KEYS = [
        '인천', '김포', '서울',
        '종로', '중구', '용산', '성동', '광진', '동대문', '성북', '강북', '도봉', '은평', '서대문',
        '마포', '강서', '구로', '영등포', '동작', '관악', '서초', '강남', '송파', '강동', '양천',
        '금천', '중랑', '노원',
        '광명시', '성남시', '안양시', '고양시', '화성시', '수원시', '부산시', '인천광역시',
    ];

    /**
     * 지명 → [위도, 경도]. 저장되는 한국어 표기 기준이며, 아래 긴 표기가 먼저 맞는다.
     *
     * @var array<string, array{float, float}>
     */
    private const POINTS = [
        // 공항 — 이 시장에서 '공항'·'인천'·'김포'는 공항을 뜻한다
        '인천공항 제1터미널' => [37.4491, 126.4509],
        '인천공항 제2터미널' => [37.4602, 126.4407],
        '인천공항' => [37.4491, 126.4509],
        '김포공항' => [37.5583, 126.7906],
        '공항' => [37.4491, 126.4509],
        '인천' => [37.4491, 126.4509],
        '김포' => [37.5583, 126.7906],
        't1' => [37.4491, 126.4509],
        't2' => [37.4602, 126.4407],

        // 인스파이어 — 인천공항 옆 아레나·호텔
        '인천 인스파이어' => [37.4464, 126.4014],
        '인스파이어' => [37.4464, 126.4014],

        // 서울 자치구
        '종로' => [37.5735, 126.9788],
        '중구' => [37.5636, 126.9970],
        '용산' => [37.5324, 126.9900],
        '성동' => [37.5634, 127.0369],
        '광진' => [37.5384, 127.0822],
        '동대문' => [37.5744, 127.0396],
        '성북' => [37.5894, 127.0167],
        '강북' => [37.6396, 127.0257],
        '도봉' => [37.6688, 127.0470],
        '은평' => [37.6027, 126.9291],
        '서대문' => [37.5791, 126.9368],
        '마포' => [37.5663, 126.9014],
        '강서' => [37.5509, 126.8495],
        '구로' => [37.4954, 126.8875],
        '영등포' => [37.5264, 126.8962],
        '동작' => [37.5124, 126.9393],
        '관악' => [37.4784, 126.9518],
        '서초' => [37.4837, 127.0324],
        '강남' => [37.4979, 127.0276],
        '송파' => [37.5145, 127.1059],
        '강동' => [37.5301, 127.1238],
        '양천' => [37.5170, 126.8665],
        '금천' => [37.4568, 126.8954],
        '중랑' => [37.6063, 127.0926],
        '노원' => [37.6542, 127.0568],
        '서울' => [37.5665, 126.9780],

        // 서울 주요 지점
        '명동' => [37.5636, 126.9834],
        '남대문' => [37.5599, 126.9779],
        '서울역' => [37.5547, 126.9707],
        '홍대' => [37.5563, 126.9236],
        '신촌' => [37.5559, 126.9369],
        '이태원' => [37.5345, 126.9946],
        '인사동' => [37.5744, 126.9840],
        '광화문' => [37.5716, 126.9769],
        '잠실' => [37.5133, 127.1000],
        '코엑스' => [37.5115, 127.0595],
        '성수' => [37.5446, 127.0559],
        '왕십리' => [37.5613, 127.0372],
        '압구정로데오역' => [37.5270, 127.0404],
        '논현동' => [37.5110, 127.0217],
        '흑석' => [37.5099, 126.9637],
        '사당동' => [37.4765, 126.9816],
        '인터컨티넨탈' => [37.5088, 127.0600],
        '문학' => [37.4380, 126.6930],

        // 경기·광역시
        '광명시' => [37.4785, 126.8644],
        '성남시' => [37.4200, 127.1267],
        '안양시' => [37.3943, 126.9568],
        '고양시' => [37.6584, 126.8320],
        '화성시' => [37.1995, 126.8312],
        '수원시' => [37.2636, 127.0286],
        '부산시' => [35.1796, 129.0756],
        '인천광역시' => [37.4563, 126.7052],
    ];

    /**
     * 출발지 → 도착지의 대략 거리(km) — 둘 중 하나라도 좌표를 모르면 null.
     */
    public static function km(?string $pickup, ?string $dropoff): ?int
    {
        $from = self::pointOf($pickup);
        $to = self::pointOf($dropoff);

        if ($from === null || $to === null) {
            return null;
        }

        $km = self::straightKm($from, $to) * self::ROAD_FACTOR;
        $rounded = (int) round($km);

        // 같은 지점을 오가는 건은 거리 표시가 의미 없다
        return $rounded < 1 ? null : $rounded;
    }

    /**
     * 출발지 → 도착지의 대략 소요시간(분) — 거리를 모르면 null.
     *
     * 저장 컬럼(`orders.estimated_duration_minutes`)은 실등록 경로가 채우지 않으므로,
     * 표시 계층은 이 추정값을 쓴다.
     */
    public static function minutes(?string $pickup, ?string $dropoff): ?int
    {
        $km = self::km($pickup, $dropoff);

        if ($km === null) {
            return null;
        }

        return (int) round($km / self::AVERAGE_SPEED_KMH * 60);
    }

    /**
     * 지명과 현재 좌표(GPS)의 직선거리(km) — 지명 좌표를 모르면 null.
     *
     * 내장 좌표는 구·지역 단위 중심점이라 정밀 측량이 아니다("마포구"는 마포구 중심).
     * 운행 단계 진행 위치가 지명에서 너무 벗어났는지 판정하는 용도로만 쓴다.
     */
    public static function kmFromPoint(?string $place, float $latitude, float $longitude): ?float
    {
        $point = self::pointOf($place);

        if ($point === null) {
            return null;
        }

        return self::straightKm($point, [$latitude, $longitude]);
    }

    /**
     * 지명이 광역(구·시) 단위인지 — 좌표를 알더라도 실제 지점은 그 구·시 안 어디든 될 수 있다.
     * 운행 단계 진행 위치 검증에서 허용 반경을 정할 때 쓴다.
     */
    public static function isCoarsePlace(?string $place): bool
    {
        $key = self::matchKey($place);

        return $key !== null && in_array($key, self::COARSE_KEYS, true);
    }

    /**
     * 지명의 좌표 — 정확히 일치하는 표기가 없으면 가장 긴 표기로 포함 여부를 본다.
     *
     * `[인천공항 제1터미널]`(정확) → `[종로 호텔]`(종로 포함) → `[인천 인스파이어]`(인스파이어 포함).
     * 두 지점이 이어진 표기(`명동—인천`)는 어느 쪽인지 알 수 없어 좌표를 주지 않는다.
     *
     * @return array{float, float}|null
     */
    private static function pointOf(?string $value): ?array
    {
        $key = self::matchKey($value);

        return $key === null ? null : self::POINTS[$key];
    }

    /**
     * 지명에 맞는 사전 키 — 정확 일치가 우선, 없으면 가장 긴 표기의 포함 여부로 찾는다.
     */
    private static function matchKey(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '' || preg_match('/[—~→]/u', $value) === 1) {
            return null;
        }

        if (array_key_exists($value, self::POINTS)) {
            return $value;
        }

        $longest = null;

        foreach (array_keys(self::POINTS) as $name) {
            if (mb_strlen($name) <= mb_strlen((string) $longest) || mb_strpos($value, $name) === false) {
                continue;
            }

            $longest = $name;
        }

        return $longest;
    }

    /**
     * 두 좌표의 직선거리(km) — 하버사인.
     *
     * @param  array{float, float}  $from
     * @param  array{float, float}  $to
     */
    private static function straightKm(array $from, array $to): float
    {
        $lat1 = deg2rad($from[0]);
        $lat2 = deg2rad($to[0]);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad($to[1] - $from[1]);

        $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($h)));
    }
}
