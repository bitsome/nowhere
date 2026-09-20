<?php

namespace App\Support\Orders;

use App\Models\Order;

/**
 * 운행 단계 진행 위치 검증 — 실제로 그 장소에 있을 때만 단계를 진행할 수 있게 한다.
 *
 * 픽업지에 가지 않고 '픽업장소 도착'을 찍으면 기록이 사실과 달라져 분쟁·정산 근거가 무너진다.
 * 그래서 픽업 관련 단계는 픽업지, 도착 단계는 하차지 근처여야 한다.
 *
 * 한계 두 가지 때문에 검증하지 못하는 경우는 통과시킨다 —
 * ① 내장 지명 좌표는 구·지역 중심점이라 정밀하지 않고,
 * ② 기사가 위치 권한을 거부하면 좌표가 없다.
 * 위치 확인이 운행 자체를 막으면 현장에서 운행을 못 하게 되므로 '막는 것'보다 '기록'을 우선한다.
 */
final class RideStepGeofence
{
    /** 단계별 기준 지점 — 없으면 검증하지 않는다('이동중'은 어디든 통과). */
    private const REFERENCE = [
        Order::RIDE_STEP_PICKUP_ARRIVED => 'pickup',
        Order::RIDE_STEP_PASSENGER_ARRIVED => 'pickup',
        Order::RIDE_STEP_DEPARTED => 'pickup',
        Order::RIDE_STEP_ARRIVED => 'dropoff',
    ];

    /** 기준 지점 표기 — 차단 안내 문구에 쓴다. */
    private const LABEL = ['pickup' => '픽업지', 'dropoff' => '도착지'];

    /**
     * 정확한 지점을 아는 지명의 허용 반경(km) — 사전에 특정 지점으로 등록된 곳(공항·역·랜드마크).
     */
    public const ALLOWED_KM = 1.0;

    /**
     * 구·시 단위 지명의 허용 반경(km) — 좌표가 구 중심점뿐이라 구 안에서 최대 5km까지 벌어진다.
     * 촘촘하게 잡되, 구 외곽(예: 마포구 중심 ↔ 공덕 약 4.8km) 픽업 건은 정상 도착도 막힐 수 있다.
     * 더 촘촘하게 하려면 지명 사전을 동 단위로 늘리거나 픽업지 좌표를 확보해야 한다.
     */
    public const ALLOWED_COARSE_KM = 1.5;

    /**
     * 단계 진행이 기준 지점에서 너무 멀리서 이뤄졌는지 — 위반이면 안내 정보, 아니면 null.
     *
     * @return array{label: string, place: string, km: float}|null
     */
    public static function violation(Order $order, string $step, ?float $latitude, ?float $longitude): ?array
    {
        $kind = self::REFERENCE[$step] ?? null;

        // 검증 대상 단계가 아니거나 위치를 못 받았으면 그대로 진행한다
        if ($kind === null || $latitude === null || $longitude === null) {
            return null;
        }

        $place = (string) ($kind === 'pickup' ? $order->pickup_location : $order->dropoff_location);
        $km = LocationDistance::kmFromPoint($place, $latitude, $longitude);

        if ($km === null) {
            return null;
        }

        // 구·시 단위 지명은 그 구가 넓어 좌표가 중심점일 뿐이라, 반경을 넓게 잡는다
        $allowedKm = LocationDistance::isCoarsePlace($place) ? self::ALLOWED_COARSE_KM : self::ALLOWED_KM;

        if ($km <= $allowedKm) {
            return null;
        }

        return ['label' => self::LABEL[$kind], 'place' => $place, 'km' => $km];
    }

    /**
     * 차단 안내 문구 — 기사가 무엇을 해야 하는지 바로 알 수 있게 지명과 거리를 함께 보여준다.
     *
     * @param  array{label: string, place: string, km: float}  $violation
     */
    public static function message(array $violation): string
    {
        return sprintf(
            '%s(%s)에서 약 %.1fkm 떨어져 있습니다. %s에 도착한 뒤 진행해 주세요.',
            $violation['label'],
            $violation['place'],
            $violation['km'],
            $violation['label'],
        );
    }
}
