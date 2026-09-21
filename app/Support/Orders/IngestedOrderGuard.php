<?php

namespace App\Support\Orders;

use App\Models\Order;
use Carbon\Carbon;

/**
 * 위챗 모니터가 보내온 운행이 규칙에 맞는지 등록 전에 검사한다.
 *
 * 여기서 값을 고치지 않는다 — 규칙에 맞는 행만 받고, 어긋난 행은 사유와 함께 거절해
 * 유입 기록(order_ingestions)에 남긴다. 그 기록이 파서를 고치는 근거가 된다.
 *
 * 검사하는 규칙:
 *  ⓐ 지명 칸에 값이 아니라 필드 라벨(日期·地址·航班号)이 들어왔는가
 *  ⓑ 유입 원문에서 온 행의 날짜가 공개 상한(30일)을 넘는가 → 소수 금액(`6.5`)을 날짜로 읽은 오독
 *  ⓒ 편명(KE925·MU2043) 안의 숫자를 시각으로 읽지 않았는가 → 09:25 · 20:43 은 시각이 아니다
 *  ⓓ 방향이 서비스 구분과 맞는가 → 接机(픽업)는 공항→목적지, 送机(샌딩)는 출발지→공항
 */
final class IngestedOrderGuard
{
    /**
     * 항공사 코드 — 편명 안의 숫자는 시각이 아니다.
     *
     * `/u` 를 붙이면 `\b` 가 유니코드 기준이 되어 `中区KE925`(한자에 붙은 코드)를 놓친다. ASCII 패턴이므로 쓰지 않는다.
     */
    private const FLIGHT_PATTERN = '/([A-Z]{2}\s?\d{2,4}|\d[A-Z]\s?\d{2,4})/';

    /**
     * 지명 칸에 들어가면 안 되는 필드 라벨 — 파서가 라벨과 값의 위치를 어긋나게 읽은 경우다.
     *
     * 예약 시스템이 보낸 문구(`日期：9.16 … 航班号：OZ364 … 地址：중정서울종로호텔`)에서
     * `地址`·`航班号` 같은 라벨 자체가 지명으로 저장된 행이 있었다.
     */
    private const LOCATION_LABELS = [
        '日期', '时间', '時間', '地址', '航班号', '航班號', '航班',
        '订单编号', '訂單編號', '全名', '行程信息', '出发时间', '出發時間',
        '出发地', '目的地', '乘客人数', '行李',
        // 예약 문구가 `人数、行李：2人2行李`·`乘客：1位`·`车型：` 로 오는 형태에서 라벨만 지명으로 잡힌 건
        '人数', '乘客', '车型', '車型',
    ];

    /**
     * 문구에 명시적으로 적힌 시각만 — 편명 안의 숫자는 시각 구분자가 없어 여기 걸리지 않는다.
     *
     * `/u` 가 없으면 전각 콜론(`：`)이 바이트 단위로 쪼개져 `8：00` 을 놓친다. 앞 경계는 `\b` 대신
     * 명시적 lookbehind 를 쓴다 — `/u` 에서 `\b` 는 유니코드 기준이라 한자에 붙은 숫자를 놓친다.
     */
    private const EXPLICIT_TIME_PATTERN = '/(?<![\dA-Za-z])(\d{1,2})\s*[:：]\s*(\d{2})(?!\d)/u';

    /**
     * 서비스 표시 — 어느 쪽이 공항인지(방향)를 정하는 근거다.
     *
     * @var array<string, string>
     */
    private const SERVICE_MARKERS = [
        'sending' => '/(送机|送機|送機場|샌딩|sanding)/u',
        'pickup' => '/(接机|接機|落地|举牌|舉牌|픽업|랜딩|pickup|landing)/u',
    ];

    /**
     * 규칙에 어긋난 행을 찾아 사유를 돌려준다.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string> 행 인덱스 => 거절 사유
     */
    public function violations(array $rows): array
    {
        $violations = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $reason = $this->violationOf($row);

            if ($reason !== null) {
                $violations[$index] = $reason;
            }
        }

        return $violations;
    }

    /**
     * 한 행의 위반 사유 — 규칙에 맞으면 null.
     *
     * @param  array<string, mixed>  $row
     */
    private function violationOf(array $row): ?string
    {
        $label = $this->labelInLocation($row);

        if ($label !== null) {
            return '지명 칸에 값이 아니라 라벨('.$label.')이 들어 있습니다.';
        }

        $date = $this->ingestedDateOverLimit($row);

        if ($date !== null) {
            return '운행 날짜 '.$date.' 가 오늘부터 '.Order::PUBLISH_MAX_DAYS_AHEAD
                .'일을 넘습니다 — 원문의 금액을 날짜로 읽지 않았는지 확인해야 합니다.';
        }

        $summary = trim((string) ($row['original_summary'] ?? ''));
        $time = trim((string) ($row['service_time'] ?? ''));
        $flight = $this->flightBehindTime($summary, $time);

        if ($flight !== null) {
            return '편명 '.$flight.' 의 숫자를 시각 '.$time.' 으로 읽었습니다 — 문구에 적힌 실제 시각을 써야 합니다.';
        }

        $service = $this->serviceOf($row);

        // 구분을 판별할 수 없으면 방향 규칙을 적용하지 않는다 (등록은 기존 추정 로직이 이어받는다)
        if ($service === null) {
            return null;
        }

        return $this->directionViolation($row, $service);
    }

    /**
     * 지명 칸에 필드 라벨이 들어온 행 — 라벨을 돌려주고, 없으면 null.
     *
     * @param  array<string, mixed>  $row
     */
    private function labelInLocation(array $row): ?string
    {
        foreach (['pickup_location', 'dropoff_location'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));

            if ($value === '') {
                continue;
            }

            foreach (self::LOCATION_LABELS as $label) {
                if (mb_strpos($value, $label) !== false) {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * 유입 원문에서 온 행의 날짜가 공개 상한을 넘었는지 — 넘은 날짜를 돌려주고, 아니면 null.
     *
     * 사람이 등록 화면에서 먼 날짜를 초안으로 잡아 두는 것은 막지 않는다. 파서가 보낸 행만 본다
     * (`original_summary` 는 유입 경로에만 실린다). 예) 원문 `6.5🌾`(금액 6.5만)를 6월 5일로 읽은 행.
     *
     * @param  array<string, mixed>  $row
     */
    private function ingestedDateOverLimit(array $row): ?string
    {
        if (trim((string) ($row['original_summary'] ?? '')) === '') {
            return null;
        }

        $date = trim((string) ($row['service_date'] ?? ''));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $limit = now('Asia/Seoul')->startOfDay()->addDays(Order::PUBLISH_MAX_DAYS_AHEAD);

        return Carbon::parse($date, 'Asia/Seoul')->gt($limit) ? $date : null;
    }

    /**
     * 서비스 구분과 어긋난 방향을 찾는다.
     *
     * @param  array<string, mixed>  $row
     */
    private function directionViolation(array $row, string $service): ?string
    {
        $pickup = (string) ($row['pickup_location'] ?? '');
        $dropoff = (string) ($row['dropoff_location'] ?? '');

        $pickupIsAirport = ServiceTypeInferrer::isAirport($pickup);
        $dropoffIsAirport = ServiceTypeInferrer::isAirport($dropoff);

        if ($service === ServiceTypeInferrer::PICKUP) {
            if ($dropoffIsAirport && ! $pickupIsAirport) {
                return '接机(픽업)는 공항에서 출발합니다 — 공항('.$dropoff.')이 도착지 칸에 있습니다.';
            }

            if ($pickup !== '' && ! $pickupIsAirport && $dropoff === '') {
                return '接机(픽업)는 공항에서 출발합니다 — 목적지('.$pickup.')가 출발지 칸에 있습니다.';
            }
        }

        if ($service === ServiceTypeInferrer::SENDING) {
            if ($pickupIsAirport && ! $dropoffIsAirport && $dropoff !== '') {
                return '送机(샌딩)는 공항으로 갑니다 — 공항('.$pickup.')이 출발지 칸에 있습니다.';
            }

            if ($dropoff !== '' && ! $dropoffIsAirport && $pickup === '') {
                return '送机(샌딩)는 공항으로 갑니다 — 출발지('.$dropoff.')가 도착지 칸에 있습니다.';
            }
        }

        return null;
    }

    /**
     * 이 행의 서비스 구분 — 행에 적힌 값이 우선, 없으면 원문에서 이 행이 가리키는 줄의 표시를 본다.
     *
     * @param  array<string, mixed>  $row
     */
    private function serviceOf(array $row): ?string
    {
        $service = (string) ($row['service_type'] ?? '');

        if (in_array($service, [ServiceTypeInferrer::PICKUP, ServiceTypeInferrer::SENDING], true)) {
            return $service;
        }

        $line = $this->lineFor($row);

        return $line === null ? null : $this->serviceFrom($line);
    }

    /**
     * 보낸 시각이 원문의 편명에서 주워 온 값이면 그 편명을, 아니면 null 을 돌려준다.
     *
     * `09:25` + `KE925`, `20:43` + `MU2043` 처럼 시각의 숫자가 편명 꼬리 숫자와 같으면 편명을 시각으로 읽은 것이다.
     */
    private function flightBehindTime(string $summary, string $time): ?string
    {
        if ($summary === '' || preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        $digits = ltrim($matches[1].$matches[2], '0');

        if ($digits === '') {
            return null;
        }

        foreach ($this->flightTokens($summary) as $token) {
            $compact = preg_replace('/\s+/', '', $token) ?? $token;

            if (preg_match('/(\d{2,4})$/', $compact, $tail) === 1 && ltrim($tail[1], '0') === $digits) {
                return $compact;
            }
        }

        return null;
    }

    /**
     * 이 행이 원문의 어느 줄인지 — 편명이 있으면 그 편명 줄, 없으면 시각이 적힌 줄이다.
     *
     * @param  array<string, mixed>  $row
     */
    private function lineFor(array $row): ?string
    {
        $summary = trim((string) ($row['original_summary'] ?? ''));

        if ($summary === '') {
            return null;
        }

        $flight = preg_replace('/\s+/', '', trim((string) ($row['flight_number'] ?? ''))) ?? '';
        $time = trim((string) ($row['service_time'] ?? ''));

        foreach (preg_split('/[\r\n]+/u', $summary) ?: [] as $line) {
            if ($flight !== '') {
                foreach ($this->flightTokens($line) as $token) {
                    if ((preg_replace('/\s+/', '', $token) ?? $token) === $flight) {
                        return $line;
                    }
                }
            }

            if ($time !== '' && in_array($time, $this->explicitTimes($line), true)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * 문구에 명시적으로 적힌 시각들 (HH:MM).
     *
     * @return array<int, string>
     */
    private function explicitTimes(string $line): array
    {
        preg_match_all(self::EXPLICIT_TIME_PATTERN, $line, $matches, PREG_SET_ORDER);

        $times = [];

        foreach ($matches as $match) {
            $hour = (int) $match[1];
            $minute = (int) $match[2];

            if ($hour <= 23 && $minute <= 59) {
                $times[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $times;
    }

    /**
     * 한 줄에 적힌 서비스 구분 — 없으면 null.
     */
    private function serviceFrom(string $line): ?string
    {
        foreach (self::SERVICE_MARKERS as $service => $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return $service;
            }
        }

        return null;
    }

    /**
     * 문구에 나온 항공사 코드들.
     *
     * @return array<int, string>
     */
    private function flightTokens(string $text): array
    {
        preg_match_all(self::FLIGHT_PATTERN, $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
