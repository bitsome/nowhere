<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderSummaryAiStructurer
{
    public function structure(string $summary): array
    {
        $apiKey = (string) (config('services.ai_order_structurer.api_key') ?: config('services.order_ai.api_key'));
        $baseUrl = rtrim((string) (config('services.ai_order_structurer.base_url') ?: config('services.order_ai.base_url')), '/');
        $model = (string) (config('services.ai_order_structurer.model') ?: config('services.order_ai.model'));
        $timeout = (int) (config('services.ai_order_structurer.timeout') ?: config('services.order_ai.timeout') ?: 30);
        $verifySsl = (bool) (config('services.ai_order_structurer.verify_ssl', config('services.order_ai.verify_ssl', true)));

        if ($apiKey === '' || $baseUrl === '' || $model === '') {
            throw new HttpException(503, 'AI 구조화 API 설정이 비어 있습니다.');
        }

        $payload = [
            'model' => $model,
            'temperature' => 0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $summary,
                ],
            ],
        ];

        try {
            $http = Http::timeout($timeout)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->acceptJson()
                ->withToken($apiKey);

            if (! $verifySsl) {
                $http = $http->withoutVerifying();
            }

            $response = $this->callWithResponseFormatFallback($http, $baseUrl, $payload);
        } catch (ConnectionException $exception) {
            throw new HttpException(503, 'AI 구조화 서버에 연결할 수 없습니다.', $exception);
        } catch (RequestException $exception) {
            $statusCode = $exception->response?->status() ?? 500;
            $message = data_get($exception->response?->json(), 'error.message')
                ?? data_get($exception->response?->json(), 'message')
                ?? 'AI 구조화 요청에 실패했습니다.';

            throw new HttpException($statusCode, $message, $exception);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI 구조화 응답이 비어 있습니다.');
        }

        $decoded = json_decode($this->extractJson($content), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI 구조화 응답을 해석할 수 없습니다.');
        }

        $requestLabel = $this->normalizeRequestLabel((string) data_get($decoded, 'request_label', ''));
        $serviceDate = $this->normalizeServiceDateValue((string) data_get($decoded, 'service_date', ''));
        $normalizedLineItems = $this->normalizeLineItems(data_get($decoded, 'line_items', []));

        if ($serviceDate === '') {
            $serviceDate = $this->extractServiceDate($requestLabel);
        }

        $groupType = $this->extractGroupType($requestLabel);

        // 요약 문구에 묶음 키워드가 없어도 일정이 2건 이상이면 셋트로 본다.
        if ($groupType === '' && count($normalizedLineItems) > 1) {
            $groupType = '셋트';
        }

        $passengerCountRaw = data_get($decoded, 'passenger_count');
        $luggageCountRaw = data_get($decoded, 'luggage_count');

        // "3+3" 형식은 인원+짐 수를 의미한다.
        if (($luggageCountRaw === null || $luggageCountRaw === '')
            && is_string($passengerCountRaw)
            && preg_match('/^(\d+)\s*\+\s*(\d+)$/', $passengerCountRaw, $matches) === 1) {
            $luggageCountRaw = $matches[2];
        }

        $normalized = [
            'request_label' => $requestLabel,
            'service_date' => $serviceDate,
            'service_month' => $this->extractServiceMonth($serviceDate),
            'service_day' => $this->extractServiceDay($serviceDate),
            'service_weekday' => $this->extractServiceWeekday($serviceDate),
            'service_time' => $this->normalizeTime((string) data_get($decoded, 'service_time', data_get($decoded, 'scheduled_time', ''))),
            'extra_options' => $this->extractExtraOptions($summary),
            'group_type' => $groupType,
            'vehicle_type' => $this->normalizeVehicleType((string) data_get($decoded, 'vehicle_type', '')),
            'service_type' => $this->normalizeServiceType((string) data_get($decoded, 'service_type', '')),
            'passenger_count' => $this->normalizePassengerCount($passengerCountRaw),
            'luggage_count' => $this->normalizeLuggageCount($luggageCountRaw),
            'pickup_location' => $this->normalizeLocation((string) data_get($decoded, 'pickup_location', '')),
            'dropoff_location' => $this->normalizeLocation((string) data_get($decoded, 'dropoff_location', '')),
            'scheduled_time' => $this->normalizeTime((string) data_get($decoded, 'scheduled_time', data_get($decoded, 'service_time', ''))),
            'order_type' => $this->normalizeOrderType((string) data_get($decoded, 'order_type', '')),
            'flight_number' => strtoupper(trim((string) data_get($decoded, 'flight_number', ''))),
            'amount_text' => $this->normalizeAmountText((string) data_get($decoded, 'amount_text', '')),
            'amount_value' => $this->normalizeAmountValue(data_get($decoded, 'amount_value')),
            'line_items' => $normalizedLineItems,
        ];

        if ($normalized['service_date'] === '' && $normalizedLineItems !== []) {
            $firstLineItem = $normalizedLineItems[0];
            $normalized['service_date'] = (string) data_get($firstLineItem, 'service_date', '');
            $normalized['service_month'] = (string) data_get($firstLineItem, 'service_month', '');
            $normalized['service_day'] = (string) data_get($firstLineItem, 'service_day', '');
            $normalized['service_weekday'] = (string) data_get($firstLineItem, 'service_weekday', '');
        }

        if ($normalized['service_time'] === '' && $normalizedLineItems !== []) {
            $normalized['service_time'] = (string) data_get($normalizedLineItems[0], 'service_time', '');
        }

        if ($normalized['scheduled_time'] === '' && $normalized['service_time'] !== '') {
            $normalized['scheduled_time'] = $normalized['service_time'];
        }

        if ($normalized['service_type'] === '' && $normalizedLineItems !== []) {
            $lineServiceTypes = collect($normalizedLineItems)
                ->pluck('service_type')
                ->filter()
                ->unique()
                ->values();

            $normalized['service_type'] = match (true) {
                $lineServiceTypes->count() > 1 => '혼합',
                $lineServiceTypes->count() === 1 => (string) $lineServiceTypes->first(),
                default => '',
            };
        }

        if ($normalized['passenger_count'] === null && $normalizedLineItems !== []) {
            $firstPassengerCount = Arr::first(array_map(
                fn (array $lineItem): ?int => $this->normalizePassengerCount(data_get($lineItem, 'passenger_count')),
                $normalizedLineItems,
            ), fn (?int $value): bool => $value !== null);

            $normalized['passenger_count'] = $firstPassengerCount;
        }

        if ($normalized['luggage_count'] === null && $normalizedLineItems !== []) {
            $firstLuggageCount = Arr::first(array_map(
                fn (array $lineItem): ?int => $this->normalizeLuggageCount(data_get($lineItem, 'luggage_count')),
                $normalizedLineItems,
            ), fn (?int $value): bool => $value !== null);

            $normalized['luggage_count'] = $firstLuggageCount;
        }

        if ($normalized['pickup_location'] === '' && $normalizedLineItems !== []) {
            $normalized['pickup_location'] = (string) data_get($normalizedLineItems[0], 'pickup_location', '');
        }

        if ($normalized['dropoff_location'] === '' && $normalizedLineItems !== []) {
            $normalized['dropoff_location'] = (string) data_get($normalizedLineItems[0], 'dropoff_location', '');
        }

        // 샌딩(送机)은 도착지가 명시되지 않아도 기본적으로 인천공항으로 간다.
        // 단, 픽업 장소가 이미 공항/터미널이면 도착지를 덮어쓰지 않는다.
        if ($normalized['service_type'] === '샌딩' && $normalized['dropoff_location'] === '' && $normalized['pickup_location'] !== '' && ! $this->isAirportLocation($normalized['pickup_location'])) {
            $normalized['dropoff_location'] = '인천';
        }

        return $normalized;
    }

    /**
     * json_object 응답 형식을 요청하고, 지원하지 않아 실패하면 형식 없이 1회 재시도한다.
     */
    private function callWithResponseFormatFallback($http, string $baseUrl, array $payload): Response
    {
        try {
            return $http
                ->post($baseUrl.'/chat/completions', $payload + [
                    'response_format' => ['type' => 'json_object'],
                ])
                ->throw();
        } catch (RequestException $exception) {
            return $http
                ->post($baseUrl.'/chat/completions', $payload)
                ->throw();
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
중국어 공항 픽업/샌딩 오더 요약을 JSON으로만 구조화한다.
설명 없이 JSON만 출력한다.
모르는 값은 문자열은 "", 숫자는 null로 둔다.

최상위 키만 사용:
request_label, service_date, service_month, service_day, service_weekday, service_time, extra_options, group_type, vehicle_type, service_type, passenger_count, luggage_count, pickup_location, dropoff_location, scheduled_time, order_type, flight_number, amount_text, amount_value, line_items

line_items 각 항목 키만 사용:
scheduled_time, service_date, service_month, service_day, service_weekday, service_time, service_type, pickup_location, dropoff_location, passenger_count, luggage_count

규칙:
- 여러 일정은 모두 line_items에 넣고 상위 값은 첫 일정 기준으로 채운다
- 시간은 HH:MM 형식(예: 3.30 → 03:30)
- service_type은 픽업, 샌딩, 혼합, ""만 사용
- order_type은 공항 오더, 일반 오더, 비즈니스 오더, ""만 사용
- passenger_count, luggage_count, amount_value는 숫자 또는 null
- flight_number는 항공편 코드만
- 공항 샌딩: 送机 뒤에 나오는 장소는 픽업 장소에서 공항으로 바래다줌. 도착지가 명시되지 않아도 기본 도착지는 인천공항 → dropoff_location: "인천"
- 공항 픽업: 接机 뒤에 나오는 장소는 도착지 (공항에서 도착지로 바래다줌)
- 경로가 하나뿐이면 있는 쪽만 채운다. 단, 샌딩(送机)이면 도착지는 기본 인천공항
- 중국어 원문은 한국어 의미로 정리한다
- n号/n日는 이번 달 n일을 뜻하는 날짜 → service_date: "n号" 형태로 그대로 둔다(월/일 변환 금지)
- 一起出, 套出, 셋트는 묶음 오더 → group_type: "셋트"
- 일정(line_items)이 2건 이상이면 묶음 오더 → group_type: "셋트"
- 卡起는 카니발부터 가능(스타리아 포함) → vehicle_type에 그대로 넣는다
- 카니발은 차량 종류 → vehicle_type: "카니발"

예시:
明洞—仁川 => pickup_location: "명동", dropoff_location: "인천"
3.30送机 蚕室 => scheduled_time: "03:30", service_type: "샌딩", pickup_location: "잠실", dropoff_location: "인천"
03:00 送机 麻浦区 1人 => scheduled_time: "03:00", service_type: "샌딩", pickup_location: "마포구", dropoff_location: "인천", passenger_count: 1
3号 卡起 03:00 送机 麻浦区 1人 07:00 送机 明洞 4人 => service_date: "3号", group_type: "셋트", vehicle_type: "卡起", line_items: [{scheduled_time: "03:00", service_type: "샌딩", pickup_location: "마포구", dropoff_location: "인천", passenger_count: 1}, {scheduled_time: "07:00", service_type: "샌딩", pickup_location: "명동", dropoff_location: "인천", passenger_count: 4}]
2号 一起出 카니발 => service_date: "2号", group_type: "셋트", vehicle_type: "카니발"
2号 一起出 卡起 => service_date: "2号", group_type: "셋트", vehicle_type: "卡起"
PROMPT;
    }

    private function extractJson(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        return $trimmed;
    }

    private function normalizeTime(string $time): string
    {
        $normalized = trim($time);

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(?<hour>\d{1,2})[:.时](?<minute>\d{2})$/u', $normalized, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        if (preg_match('/^(?<hour>\d{1,2})(?<minute>\d{2})$/', $normalized, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        if (preg_match('/^(?<hour>\d{1,2})\s*点$/u', $normalized, $matches) === 1) {
            return sprintf('%02d:00', (int) $matches['hour']);
        }

        return $normalized;
    }

    private function normalizeOrderType(string $orderType): string
    {
        return match (trim($orderType)) {
            '공항 오더', 'airport', 'airport_order' => '공항 오더',
            '비즈니스 오더', 'business', 'business_order' => '비즈니스 오더',
            '일반 오더', 'normal', 'normal_order' => '일반 오더',
            default => trim($orderType),
        };
    }

    private function normalizeServiceType(string $serviceType): string
    {
        $normalized = trim($serviceType);

        return match (true) {
            str_contains($normalized, '收送机'),
            str_contains($normalized, '혼합'),
            $normalized === 'mixed' => '혼합',
            str_contains($normalized, '픽업'),
            str_contains($normalized, '接机'),
            $normalized === 'pickup' => '픽업',
            str_contains($normalized, '샌딩'),
            str_contains($normalized, '送机'),
            $normalized === 'sending' => '샌딩',
            str_contains($normalized, '랜딩'),
            $normalized === 'landing' => '랜딩',
            default => '',
        };
    }

    private function normalizePassengerCount(mixed $passengerCount): ?int
    {
        if ($passengerCount === null || $passengerCount === '') {
            return null;
        }

        if (is_numeric($passengerCount)) {
            return (int) $passengerCount;
        }

        if (preg_match('/(\d+)/', (string) $passengerCount, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function normalizeLuggageCount(mixed $luggageCount): ?int
    {
        if ($luggageCount === null || $luggageCount === '') {
            return null;
        }

        if (is_numeric($luggageCount)) {
            return (int) $luggageCount;
        }

        if (preg_match('/(\d+)/', (string) $luggageCount, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function normalizeLineItems(mixed $lineItems): array
    {
        if (! is_array($lineItems)) {
            return [];
        }

        $normalizedItems = [];
        $currentServiceDate = '';

        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $serviceDate = $this->normalizeServiceDateValue((string) data_get($lineItem, 'service_date', ''));
            if ($serviceDate !== '') {
                $currentServiceDate = $serviceDate;
            }

            $scheduledTime = $this->normalizeTime((string) data_get($lineItem, 'scheduled_time', data_get($lineItem, 'service_time', '')));
            $linePassengerRaw = data_get($lineItem, 'passenger_count');
            $lineLuggageRaw = data_get($lineItem, 'luggage_count');

            // "3+3" 형식은 인원+짐 수를 의미한다.
            if (($lineLuggageRaw === null || $lineLuggageRaw === '')
                && is_string($linePassengerRaw)
                && preg_match('/^(\d+)\s*\+\s*(\d+)$/', $linePassengerRaw, $matches) === 1) {
                $lineLuggageRaw = $matches[2];
            }

            $lineServiceType = $this->normalizeServiceType((string) data_get($lineItem, 'service_type', ''));

            $linePickup = $this->normalizeLocation((string) data_get($lineItem, 'pickup_location', ''));
            $lineDropoff = $this->normalizeLocation((string) data_get($lineItem, 'dropoff_location', ''));

            if ($linePickup === '' && $lineDropoff === '') {
                $lineLocation = $this->normalizeLocation((string) data_get($lineItem, 'location', ''));

                if (str_contains($lineLocation, '—')) {
                    [$linePickup, $lineDropoff] = array_pad(explode('—', $lineLocation, 2), 2, '');
                } else {
                    $linePickup = $lineLocation;
                }
            }

            // 샌딩(送机) 일정은 도착지가 명시되지 않으면 기본적으로 인천공항으로 간다.
            // 단, 픽업 장소가 이미 공항/터미널이면 도착지를 덮어쓰지 않는다.
            if ($lineServiceType === '샌딩' && $lineDropoff === '' && $linePickup !== '' && ! $this->isAirportLocation($linePickup)) {
                $lineDropoff = '인천';
            }

            $combinedLocation = match (true) {
                $linePickup !== '' && $lineDropoff !== '' => $linePickup.'—'.$lineDropoff,
                $linePickup !== '' => $linePickup,
                default => $lineDropoff,
            };

            $normalizedItems[] = [
                'scheduled_time' => $scheduledTime,
                'service_date' => $currentServiceDate,
                'service_month' => $this->extractServiceMonth($currentServiceDate),
                'service_day' => $this->extractServiceDay($currentServiceDate),
                'service_weekday' => $this->extractServiceWeekday($currentServiceDate),
                'service_time' => $scheduledTime,
                'service_type' => $this->normalizeServiceType((string) data_get($lineItem, 'service_type', '')),
                'pickup_location' => $linePickup,
                'dropoff_location' => $lineDropoff,
                'location' => $combinedLocation,
                'passenger_count' => $this->normalizePassengerCount($linePassengerRaw),
                'luggage_count' => $this->normalizeLuggageCount($lineLuggageRaw),
            ];
        }

        return $normalizedItems;
    }

    private function normalizeRequestLabel(string $requestLabel): string
    {
        $normalized = trim($requestLabel);

        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['套出', '套接', '套装', '一起出', '묶음', '세트'], '셋트', $normalized);

        // "8.2号", "8/02", "8月2号" 형태의 날짜 표기 정리
        $datePatterns = [
            '/(\d{1,2})\s*[.\/]\s*(\d{1,2})\s*号?/u',
            '/(\d{1,2})\s*月\s*(\d{1,2})\s*号?/u',
        ];

        $isDateMatched = false;

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                $dateLabel = (int) $matches[1].'월'.(int) $matches[2].'일';
                $normalized = preg_replace($pattern, $dateLabel, $normalized, 1) ?? $normalized;
                $isDateMatched = true;
                break;
            }
        }

        // 월 표기가 없는 "2号", "2日"는 이번 달 날짜로 처리한다.
        if (! $isDateMatched && preg_match('/(\d{1,2})\s*[号日]/u', $normalized, $matches) === 1) {
            $dateLabel = now()->month.'월'.(int) $matches[1].'일';
            $normalized = preg_replace('/(\d{1,2})\s*[号日]/u', $dateLabel, $normalized, 1) ?? $normalized;
        }

        // "날짜" 같은 보조 표기는 제거하고 중복 단어(셋트 등)는 하나로 합친다.
        $normalized = preg_replace('/\s*날짜\s*/u', ' ', $normalized) ?? $normalized;
        $normalized = implode(' ', array_unique(preg_split('/\s+/u', trim($normalized))));

        return match ($normalized) {
            '今天' => '오늘',
            '明天' => '내일',
            '收送机' => '현시간부터 픽업/샌딩 배차대기',
            default => $normalized,
        };
    }

    private function extractServiceDate(string $requestLabel): string
    {
        if (preg_match('/(\d{1,2})\s*[.\/월-]\s*(\d{1,2})/u', $requestLabel, $matches) !== 1) {
            return '';
        }

        return $this->normalizeServiceDateValue($matches[1].'월'.$matches[2].'일');
    }

    private function normalizeServiceDateValue(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        // "2号", "2日" 형태는 이번 달 날짜로 처리한다.
        if (preg_match('/^(\d{1,2})\s*[号日]$/u', $normalized, $matches) === 1) {
            return now()->month.'월'.(int) $matches[1].'일';
        }

        if (preg_match('/(\d{1,2})\D+(\d{1,2})/u', $normalized, $matches) !== 1) {
            return $normalized;
        }

        return (int) $matches[1].'월'.(int) $matches[2].'일';
    }

    private function extractServiceMonth(string $serviceDate): string
    {
        if (preg_match('/(\d{1,2})월\d{1,2}일/u', $serviceDate, $matches) !== 1) {
            return '';
        }

        return (string) (int) $matches[1];
    }

    private function extractServiceDay(string $serviceDate): string
    {
        if (preg_match('/\d{1,2}월(\d{1,2})일/u', $serviceDate, $matches) !== 1) {
            return '';
        }

        return (string) (int) $matches[1];
    }

    private function extractServiceWeekday(string $serviceDate): string
    {
        if (preg_match('/(\d{1,2})월(\d{1,2})일/u', $serviceDate, $matches) !== 1) {
            return '';
        }

        $date = Carbon::create(now()->year, (int) $matches[1], (int) $matches[2]);

        return match ($date->dayOfWeek) {
            Carbon::MONDAY => '월요일',
            Carbon::TUESDAY => '화요일',
            Carbon::WEDNESDAY => '수요일',
            Carbon::THURSDAY => '목요일',
            Carbon::FRIDAY => '금요일',
            Carbon::SATURDAY => '토요일',
            Carbon::SUNDAY => '일요일',
        };
    }

    private function extractGroupType(string $requestLabel): string
    {
        return str_contains($requestLabel, '셋트') ? '셋트' : '';
    }

    private function extractExtraOptions(string $summary): array
    {
        $options = [];

        if (str_contains($summary, '不加不聊')) {
            $options[] = '추가 연락 없음';
        }

        if (str_contains($summary, '秒结')) {
            $options[] = '즉시 정산';
        }

        if (str_contains($summary, '无超时')) {
            $options[] = '초과 시간 없음';
        }

        return $options;
    }

    private function normalizeVehicleType(string $vehicleType): string
    {
        $normalized = trim($vehicleType);
        $contains = fn (string $needle): bool => str_contains($normalized, $needle);

        return match (true) {
            $normalized === '333', $contains('需要333') => '스타리아 9인승(3-3-3)',
            $normalized === '222' => '카니발 7인승(2-2-2)',
            $normalized === '新卡起', $normalized === '全部新卡' => '더뉴카니발 4세대',
            $contains('新卡') && $contains('卡起') => '더뉴카니발 4세대',
            $contains('卡起') => '카니발부터 가능',
            $normalized === '카니발' => '카니발',
            $contains('利亚7') && $contains('333') => '스타리아 7인승 또는 9인승(3-3-3)',
            $contains('新卡') && $contains('利亚') => '더뉴카니발 4세대 또는 스타리아',
            $normalized === '利亚7' => '스타리아 7인승',
            $normalized === '利亚' => '스타리아',
            $normalized === '小车🉑' => '소형 승용차(세단/SUV)',
            default => $normalized,
        };
    }

    private function isAirportLocation(string $location): bool
    {
        return str_contains($location, '인천')
            || str_contains($location, '김포')
            || str_contains($location, '공항')
            || str_contains($location, '터미널');
    }

    private function normalizeLocation(string $location): string
    {
        $normalized = trim($location);

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, '—')) {
            $parts = preg_split('/\s*—\s*/u', $normalized) ?: [];

            $normalizedParts = array_filter(
                array_map(fn (string $part): string => $this->normalizeSingleLocation($part), $parts),
                static fn (string $part): bool => $part !== '',
            );

            return implode('—', $normalizedParts);
        }

        return $this->normalizeSingleLocation($normalized);
    }

    private function normalizeSingleLocation(string $location): string
    {
        $normalized = trim($location);

        return match ($normalized) {
            '仁川', '仁川机场', '仁川機場' => '인천',
            'T1' => '인천공항 제1터미널',
            '明洞' => '명동',
            '龙山', '龙山区' => '용산구',
            '弘大' => '홍대',
            '麻浦' => '마포',
            '麻浦区' => '마포구',
            '江南', '江南区' => '강남구',
            '蚕室' => '잠실',
            '东大门', '东大门区' => '동대문구',
            '永登浦' => '영등포',
            '秃山' => '독산',
            '中区' => '중구',
            '钟路', '钟路区' => '종로구',
            '恩平' => '은평구',
            '江东' => '강동구',
            '金浦' => '김포',
            '首尔站' => '서울역',
            '江西区' => '강서구',
            '客路端' => '클록',
            default => $normalized,
        };
    }

    private function normalizeAmountText(string $amountText): string
    {
        $normalized = trim($amountText);
        $normalized = preg_replace('/^(?:套出|套接|세트|셋트|묶음|한세트)\s*/u', '', $normalized) ?? $normalized;

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:万|塊|块|w|W|🥬|🌾|만)?$/u', $normalized, $matches) === 1) {
            $amount = $matches[1];

            // 소수점 아래의 의미 없는 0만 제거한다. (예: 30.0 → 30, 6.50 → 6.5)
            if (str_contains($amount, '.')) {
                $amount = rtrim(rtrim($amount, '0'), '.');
            }

            return $amount.'만';
        }

        return $normalized;
    }

    private function normalizeAmountValue(mixed $amountValue): ?int
    {
        if ($amountValue === null || $amountValue === '') {
            return null;
        }

        if (is_numeric($amountValue)) {
            return (int) round((float) $amountValue);
        }

        $normalized = trim((string) $amountValue);
        $normalized = preg_replace('/^(?:套出|套接|세트|셋트|묶음|한세트)\s*/u', '', $normalized) ?? $normalized;

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:万|塊|块|w|W|🥬|🌾|만)?$/u', $normalized, $matches) === 1) {
            return (int) round(((float) $matches[1]) * 10000);
        }

        if (preg_match('/(\d+(?:\.\d+)?)/u', $normalized, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1];

        if (preg_match('/(万|塊|块|w|W|🥬|🌾|만)/u', $normalized) === 1) {
            return (int) round($value * 10000);
        }

        return (int) round($value);
    }
}
