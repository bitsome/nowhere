<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Services\Order\OrderClaimService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 운행 목록 행(row)의 공용 데이터 계약을 만든다.
 *
 * Blade, Vue, API 응답이 이 구조를 그대로 사용하며,
 * 화면별로 row를 다시 조립하지 않는다.
 */
class OrderListRowBuilder
{
    /**
     * @var array<string, string>
     */
    private array $serviceLabels;

    /**
     * @var array<string, string>
     */
    private array $statusOptions;

    /**
     * @param  array<string, string>|null  $statusOptions
     */
    public function __construct(?array $statusOptions = null)
    {
        $this->serviceLabels = [
            'pickup' => '픽업',
            'sending' => '공항샌딩',
            'point' => '시내',
            'landing' => '공항랜딩',
        ];

        $this->statusOptions = $statusOptions ?? Order::statusOptions();
    }

    /**
     * 단일 운행 행 계약을 만든다.
     *
     * @return array<string, mixed>
     */
    public function build(Order $order): array
    {
        return [
            'key' => 'order-'.$order->id,
            'kind' => 'single',
            'id' => $order->id,
            // 수행자(운행 소유자) 사용자 id — 카드 단계 스테퍼는 기사 본인에게만 보여준다
            'userId' => $order->user_id,
            'orderNumber' => $order->order_number ?: '#'.$order->id,
            'customerName' => $order->customer_name ?: '',
            'serviceIcon' => $this->serviceIcon($order),
            'serviceLabel' => $this->serviceLabel($order),
            'serviceType' => $order->service_type,
            'vehicle' => ChineseTextNormalizer::displayVehicle($order->vehicle_type),
            'flightNumber' => $order->flight_number ?: '',
            'tags' => ChineseTextNormalizer::withoutInternalTags($order->tags),
            'passengerCount' => $order->passenger_count ?: 0,
            'luggageCount' => $order->luggage_count ?: 0,
            'date' => $this->formatDate($order),
            'time' => $this->formatTime($order),
            'pickupDateTime' => $this->formatPickupDateTime($order),
            'route' => $this->routeLabel($order),
            'amount' => $this->formatAmount($order),
            'status' => $order->status,
            'statusLabel' => $this->statusOptions[$order->status] ?? $order->status,
            'claimantName' => $order->claimant_user_id !== null ? ($order->claimant?->name ?? '') : '',
            'claimantUserId' => $order->claimant_user_id,
            // 일괄 요청 그룹 키 — 같은 추천1 체인에서 한 번에 보낸 요청끼리 묶는다
            'claimBatchId' => $order->claim_batch_id,
            // 가져오기 요청 시점·자동 만료 여부 — 요청보냄에서 30분 경과 시 휴지통으로 이동
            'claimedAt' => $order->claimed_at?->toISOString(),
            'claimExpired' => $order->claimed_at !== null
                && $order->claimed_at->addSeconds(OrderClaimService::CLAIM_EXPIRE_SECONDS)->isPast(),
            // 배차 승인(수락) 시점 — 진행중 목록의 '승인받은 시간'에 사용
            'approvedAt' => $order->approved_at?->toISOString(),
            // 운행중 세부 단계 — 카드 단계 스테퍼(운행시작→…→도착지 도착)의 현재 위치
            'rideStep' => $order->ride_step,
            'isToday' => $this->isToday($order),
            'isTomorrow' => $this->isTomorrow($order),
            'isNew' => $this->isNew($order),
            'isUrgent' => $this->isUrgent($order),
            'isPriority' => $this->isPriority($order),
            'sortDate' => $order->service_date ?: '',
            'sortTime' => $order->service_time ?: '',
            'sortCreatedAt' => $order->created_at?->toISOString() ?? '',
            'amountValue' => (int) ($order->expected_revenue ?? $order->amount_value ?? 0),
            // 소요시간(분) — 저장값은 데모 데이터에서만 채워지므로, 없으면 거리 기반 추정으로 채운다
            'estimatedDurationMinutes' => $order->estimated_duration_minutes
                ?? LocationDistance::minutes($order->pickup_location, $order->dropoff_location),
            // 출발지 → 도착지 대략 거리(km) — 좌표를 아는 지명끼리만 계산한다. 카드에 "약 Nkm" 로 노출
            'distanceKm' => LocationDistance::km($order->pickup_location, $order->dropoff_location),
            // 랜딩(공항 픽업) 대기 시간(분) — 체인 하차 시각 계산에 사용. 항공기 도착 후 승객 퇴장 대기.
            // 출발지가 공항이면 랜딩 (service_type은 부정확할 수 있어 방향만으로 판단)
            'landingWaitMinutes' => str_contains((string) $order->pickup_location, '공항')
                ? (int) config('recommendation.landing_wait_minutes', 60)
                : 0,
        ];
    }

    /**
     * 여러 운행을 행 계약 목록으로 만든다.
     *
     * @param  Collection<int, Order>|array<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function buildMany(Collection|array $orders): array
    {
        $rows = [];

        foreach ($orders as $order) {
            $rows[] = $this->build($order);
        }

        return $rows;
    }

    public function serviceLabel(Order $order): string
    {
        $type = $order->service_type;

        if ($type === null || $type === '') {
            return '-';
        }

        return $this->serviceLabels[$type] ?? '-';
    }

    public function serviceIcon(Order $order): string
    {
        return match ($order->service_type) {
            'pickup' => 'pickup',
            'sending' => 'sending',
            'landing' => 'landing',
            default => '',
        };
    }

    public function formatDate(Order $order): ?string
    {
        if (! $order->service_date) {
            return null;
        }

        $date = Carbon::parse($order->service_date);
        $weekdays = ['일', '월', '화', '수', '목', '금', '토'];

        return $date->format('n/j').'('.$weekdays[$date->dayOfWeek].')';
    }

    public function formatTime(Order $order): string
    {
        return $order->service_time ?: '';
    }

    public function formatPickupDateTime(Order $order): string
    {
        $date = $this->formatDate($order);
        $time = $this->formatTime($order);

        return trim(($date ?? '').' '.$time);
    }

    public function formatAmount(Order $order): string
    {
        $amount = $order->expected_revenue ?? $order->amount_value;

        if ($amount) {
            return number_format((int) $amount).'원';
        }

        // 금액 미지정 운행은 '요금 협의'로 노출한다 — 기사가 요금을 제안(offer)하는 흐름으로 이어진다.
        // 이미 끝난 운행은 금액이 비어 있으면 데이터 문제이므로 '-' 로 둔다.
        $closed = [Order::STATUS_COMPLETED, Order::STATUS_SETTLED, Order::STATUS_CANCELLED];

        return in_array($order->status, $closed, true) ? '-' : '요금 협의';
    }

    /**
     * 화면에 보여 줄 노선 — 유입 원문의 중국어 표기를 한국어로 바꾼다.
     */
    public function routeLabel(Order $order): string
    {
        return ChineseTextNormalizer::displayLocation($order->pickup_location)
            .' → '.ChineseTextNormalizer::displayLocation($order->dropoff_location);
    }

    /**
     * 오늘 운행 여부 (카드 리스트에서 "오늘" 라벨로 사용).
     * 서비스 날짜는 사용자 로컬(KST) 기준 문자열이므로 KST 오늘과 비교한다.
     */
    private function isToday(Order $order): bool
    {
        if (! $order->service_date) {
            return false;
        }

        return Carbon::parse($order->service_date, 'Asia/Seoul')->isSameDay(Carbon::now('Asia/Seoul'));
    }

    /**
     * 내일 운행 여부 (카드 리스트에서 "내일" 라벨로 사용).
     */
    private function isTomorrow(Order $order): bool
    {
        if (! $order->service_date) {
            return false;
        }

        return Carbon::parse($order->service_date, 'Asia/Seoul')->isSameDay(Carbon::now('Asia/Seoul')->addDay());
    }

    /**
     * 등록 후 1시간 이내 새 운행 여부 (목록에서 N 배지로 표시).
     */
    private function isNew(Order $order): bool
    {
        return $order->created_at !== null && $order->created_at->gte(now()->subHours(2));
    }

    /**
     * 임박 여부 — 오늘 서비스인데 아직 서비스 시각이 지나지 않은 운행 (빨간 임박 배지).
     *
     * 시각이 지나면 곧바로 긴급으로 올라가므로 남은 시간 상한은 두지 않는다.
     */
    public function isUrgent(Order $order): bool
    {
        $minutesUntil = $this->minutesUntilService($order);

        return $minutesUntil !== null && $minutesUntil > 0;
    }

    /**
     * 긴급 여부 — 등록자가 긴급으로 올렸거나(`is_priority`), 아직 매칭되지 않은 공개 운행인데
     * 서비스(착륙) 시각이 이미 지난 경우 (빨간 긴급 배지).
     *
     * 서비스 시각 전은 '임박', 그 시각이 지나면 '긴급'으로 올린다 — 기사가 지금 손대야 한다는 신호다.
     * 하한은 두지 않는다(오늘인지 여부는 minutesUntilService가 판단한다).
     */
    public function isPriority(Order $order): bool
    {
        if ((bool) $order->is_priority) {
            return true;
        }

        // 아직 기사가 정해지지 않은 운행만 본다 — 확정·운행중·완료 건은 지난 시각이 정상 흐름이다
        if (! in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
        ], true)) {
            return false;
        }

        $minutesUntil = $this->minutesUntilService($order);

        return $minutesUntil !== null && $minutesUntil <= 0;
    }

    /**
     * 서비스 시각까지 남은 분 (오늘이 아니거나 일시가 없으면 null).
     *
     * 서비스 시각은 사용자 로컬(KST) 기준 문자열로 저장되므로, 비교도 KST로 수행한다.
     */
    private function minutesUntilService(Order $order): ?int
    {
        if (! $order->service_date || ! $order->service_time) {
            return null;
        }

        $service = Carbon::parse($order->service_date.' '.$order->service_time, 'Asia/Seoul');
        $now = Carbon::now('Asia/Seoul');

        if (! $service->isSameDay($now)) {
            return null;
        }

        return (int) floor(($service->getTimestamp() - $now->getTimestamp()) / 60);
    }
}
