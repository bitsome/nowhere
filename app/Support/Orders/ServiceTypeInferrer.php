<?php

namespace App\Support\Orders;

/**
 * 노선(출발지·도착지)으로 서비스 구분을 추정한다.
 *
 * 유입 원문에 接机/送机 같은 구분 표기가 없는 경우가 많다. 이때 공항이 노선의 어느 쪽에 있는지로
 * 구분을 정한다 — 공항에서 출발하면 픽업, 공항으로 가면 샌딩, 양쪽 모두 공항이 아니면 시내(point).
 * 공항→공항처럼 구분이 모호한 경우는 추정하지 않는다(억지 값을 만들지 않는다).
 */
class ServiceTypeInferrer
{
    public const PICKUP = 'pickup';

    public const SENDING = 'sending';

    public const POINT = 'point';

    /** 공항으로 볼 수 있는 표기 — 시장 관행상 '인천/김포'는 공항을 뜻한다. */
    private const AIRPORT_MARKERS = [
        '공항', '인천공항', '김포공항', '인천', '김포',
        '仁川', '金浦', '机场', '機場', 'T1', 'T2',
    ];

    /**
     * @return string|null 추정된 구분 (추정 불가 시 null)
     */
    public static function infer(?string $pickupLocation, ?string $dropoffLocation): ?string
    {
        if (blank($pickupLocation) || blank($dropoffLocation)) {
            return null;
        }

        $fromAirport = self::isAirport($pickupLocation);
        $toAirport = self::isAirport($dropoffLocation);

        if ($fromAirport === $toAirport) {
            // 공항→공항은 픽업/샌딩을 가릴 수 없다 — 시내 운행만 point 로 추정한다
            return $fromAirport ? null : self::POINT;
        }

        return $fromAirport ? self::PICKUP : self::SENDING;
    }

    /**
     * 공항 표기가 포함되어 있는지 — 구분(픽업/샌딩)을 정할 때 쓰는 기준이다.
     */
    public static function isAirport(?string $location): bool
    {
        foreach (self::AIRPORT_MARKERS as $marker) {
            if (mb_stripos((string) $location, $marker) !== false) {
                return true;
            }
        }

        return false;
    }
}
