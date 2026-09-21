<?php

namespace App\Support\Orders;

use App\Models\OrderTerm;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * 위챗 등 중국어로 들어온 운행 값을 한국어 표기로 바꾸는 공용 정규화 사전.
 *
 * 저장(OrderCreator)과 문구 구조화(OrderSummaryAiStructurer) 가 같은 사전을 쓰도록
 * 한 곳에 모아 둔다 — 사전이 두 벌로 갈라지면 한쪽만 갱신되어 표기가 어긋난다.
 *
 * 내장 사전에 없는 표기는 관리자 화면에서 매핑한 값(order_terms)을 함께 본다.
 */
final class ChineseTextNormalizer
{
    /** 관리자 매핑값 캐시 키 — 관리자 저장 시 forgetCache() 로 비운다. */
    public const CACHE_KEY = 'order_terms.mapped';

    /** 지명 — 정확히 일치할 때만 바꾼다. 한국어·주소는 그대로 통과시킨다. */
    private const LOCATIONS = [
        '仁川' => '인천',
        '仁川机场' => '인천',
        '仁川機場' => '인천',
        '仁川T1' => '인천공항 제1터미널',
        'T1' => '인천공항 제1터미널',
        't1' => '인천공항 제1터미널',
        '机场' => '공항',
        '明洞' => '명동',
        '龙山' => '용산구',
        '弘大' => '홍대',
        '宏大' => '홍대',
        '麻浦' => '마포구',
        '江南' => '강남구',
        '蚕室' => '잠실',
        '东大门' => '동대문구',
        '铜雀区舍堂洞' => '동작구 사당동',
        '永登浦' => '영등포구',
        '秃山' => '독산',
        '中区' => '중구',
        '钟' => '종로구',
        '钟路' => '종로구',
        '恩平' => '은평구',
        '江东' => '강동구',
        '江北' => '강북구',
        '广津' => '광진구',
        '圣水' => '성수',
        // 김포·인천은 이 시장에서 '공항'을 뜻하는 표기라 시(市)를 붙이지 않는다
        '金浦' => '김포',
        '高阳' => '고양시',
        '华城' => '화성시',
        '九老酒店' => '구로 호텔',
        'inspire场馆' => '인스파이어 아레나',
        '首尔站' => '서울역',
        '江西区' => '강서구',
        '客路端' => '클록',
        // 위챗 모니터 유입에서 확인된 표기 — 관리자 매핑과 별개로 사전에 내장해 두면
        // 로컬 DB 재동기화와 무관하게 유지되고, 배포만으로 운영에도 함께 적용된다.
        '城北' => '성북구',
        '文鹤' => '문학',
        '狎鸥亭罗德奥地铁站' => '압구정로데오역',
        '圣水地铁站' => '성수역',
        '弘大地铁站' => '홍대입구역',
        '往十里' => '왕십리',
        '梨泰院' => '이태원',
        '松坡' => '송파구',
        '安阳' => '안양시',
        '马铺' => '마포구',
        '釜山' => '부산시',
        '厦门' => '샤먼',
        '杭州' => '항저우',
        '福州' => '푸저우',
        // 인스파이어를 다르게 적은 표기 — 모니터 유입에서 확인된 변이
        '迎士柏' => '인스파이어',
        '迎世博' => '인스파이어',
        '迎仕柏' => '인스파이어',
        '仁川inspire' => '인천 인스파이어',
        '洲际' => '인터컨티넨탈',
        'voco酒店' => 'voco 호텔',
        '어여화餐厅' => '어여화 식당',
        '光华门' => '광화문',
        '中路' => '종로구',
        '仁川T2' => '인천공항 제2터미널',
        // 모니터가 오타로 보낸 표기 — 같은 뜻으로 본다
        '永澄甫' => '영등포구',
        // 마켓 카드 점검(2026-09-13)에서 '미정'으로 보이던 지명
        '城东' => '성동구',
        '江西' => '강서구',
        '光明市' => '광명시',
        '南大门' => '남대문',
        '世宗大' => '세종대',
        '明洞乐天城市' => '명동 롯데시티',
        '仁川2' => '인천공항 제2터미널',
        '仁川t2' => '인천공항 제2터미널',
        // 인스파이어 호텔 — 영문 표기로 그대로 들어오는 경우가 있다
        'inspire' => '인스파이어',
        'Inspire' => '인스파이어',
        // 잔여 유입 정리(2026-09-13)에서 확인된 표기
        '冠岳' => '관악구',
        '舍堂洞' => '사당동',
        // 인스파이어 축약 — '东大门-ins' 처럼 두 지점을 이어 적을 때 쓴다
        'ins' => '인스파이어',
        'Ins' => '인스파이어',
        // 사용자 분류(2026-09-13): 金铺는 金浦(김포) 오기, 世运은 명동의 업체·거점명
        '金铺' => '김포',
        '世运' => '세운',
        '明洞世运' => '명동 세운',
        // 유입 품질 점검(2026-09-14)에서 '미정'으로 남아 있던 지명.
        // 행정구역 값은 구(區)를 붙여 통일한다 — 사전에 두 벌로 두지 않고 조회할 때 접미사를 뗀다.
        '西大门' => '서대문구',
        '瑞草' => '서초구',
        '九老' => '구로구',
        '嘛扑' => '마포구',
        '黑石' => '흑석',
        '城南' => '성남시',
        '仁寺洞' => '인사동',
        '道峰' => '도봉구',
        '新罗' => '신라',
        '中庭首尔钟路酒店' => '종로 호텔',
    ];

    /**
     * 운영 지시 표기 — 지명이 아니라 운행 태그로 옮겨야 하는 값.
     *
     * 관리자 화면에서 '태그' 분야로 매핑한 값과 합쳐서 쓴다 (관리자 매핑이 우선).
     */
    private const TAGS = [
        '帮划客路' => '클록스텝진행',
        '飞机马上降落' => '지금 착륙',
        '客人出来了' => '고객 나옴',
        // 고객·결제 성격 표기 — 지명이 아니라 기사가 알아야 할 운행 조건이다
        '老外' => '외국인 고객',
        '秒结' => '바로결제',
        '秒結' => '바로결제',
        // 목적지가 특정 호텔이라는 표시 — 건물 이름이 아니라 조건이다
        '酒店' => '호텔',
        // 렌트 차량만 받는다는 조건 (기사 본인 차량으로는 못 받는 운행)
        '租赁车' => '렌트카만 가능',
        '租賃車' => '렌트카만 가능',
        // 짐이 없다는 조건 — 차량 선택에 영향을 준다
        '没行李' => '짐 없음',
        '沒行李' => '짐 없음',
        // 금연 차량 조건 (无烟利亚 = 금연 스타렉스) — 차량 선택에 영향을 준다
        '无烟' => '금연 차량',
        '無煙' => '금연 차량',
    ];

    /**
     * 긴급 태그 — 지금 당장 손이 필요한 운행이라 마켓에서 긴급 배지로 올린다.
     *
     * `飞机马上降落`(지금 착륙)·`客人出来了`(고객 나옴)은 기사가 바로 움직여야 한다는 신호다.
     */
    private const URGENT_TAGS = [
        '지금 착륙',
        '고객 나옴',
    ];

    /**
     * 위치 문자열을 한국어로 바꾼다. '—' 로 이어진 복수 구간은 각각 바꿔 다시 잇는다.
     */
    public static function location(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $normalized = trim($location);

        if ($normalized === '') {
            return '';
        }

        if (! str_contains($normalized, '—')) {
            return self::singleLocation($normalized);
        }

        $parts = preg_split('/\s*—\s*/u', $normalized) ?: [];

        $normalizedParts = array_filter(
            array_map(self::singleLocation(...), $parts),
            static fn (string $part): bool => $part !== '',
        );

        return implode('—', $normalizedParts);
    }

    /**
     * 한 구간짜리 지명을 한국어로 바꾼다.
     *
     * 한 칸에 두 지점이 이어 붙어 온 표기(`宏大送inspire` = 홍대→인스파이어)는 '—' 로 이어 두고,
     * 끝에 붙은 단독 接送(`宏大送` = 홍대)은 방향 표시일 뿐이라 지명에서 뗀다.
     *
     * 파서가 지명 앞뒤에 시각·차량·금액을 붙여 보낸 값(`0830送机中区`, `金浦小车5万`)은
     * 껍데기를 떼고 다시 찾는다. 사전에서 읽히는 값이 나올 때만 바꾸므로 모르는 표기는 그대로 남는다.
     */
    public static function singleLocation(string $location): string
    {
        $normalized = trim($location);

        $mapped = self::mappedLocation($normalized);

        if ($mapped !== null) {
            return $mapped;
        }

        $through = self::routeThrough($normalized);

        if ($through !== null) {
            return $through;
        }

        $stripped = trim((string) preg_replace('/[接送收]$/u', '', $normalized));

        if ($stripped !== $normalized && $stripped !== '') {
            return self::mappedLocation($stripped) ?? $normalized;
        }

        $candidates = [$normalized, ...self::noiseTrimCandidates($normalized)];

        foreach ($candidates as $candidate) {
            $resolved = self::mappedLocation($candidate);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // 사전 지명 뒤에 브랜드가 붙은 값(`江南Voco`) — 앞 지명만 살려 이어 둔다.
        // 껍데기를 뗀 후보까지 본다 (`江南Voco.5米` → `江南Voco`)
        foreach ($candidates as $candidate) {
            $resolved = self::mappedWithSuffix($candidate);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $normalized;
    }

    /**
     * 앞뒤 껍데기를 한 겹씩 벗긴 후보들 — 뒤에서부터 차례로 돌려준다.
     *
     * 한 번에 다 벗기면 `金浦小车5万` 이 `金浦` 까지만 가야 할 것이 지나치게 잘릴 수 있어,
     * 벗긴 단계마다 후보로 남기고 사전 조회는 부르는 쪽이 한다.
     *
     * @return array<int, string>
     */
    private static function noiseTrimCandidates(string $value): array
    {
        $candidates = [];
        $current = trim($value);

        for ($round = 0; $round < 3; $round++) {
            // 앞: 시각(0830·08:30) 과 서비스 표시(送机·接机)
            $step = (string) preg_replace('/^(?:\d{1,2}\s*[:.时]\s*\d{2}|\d{3,4})\s*/u', '', $current);
            $step = (string) preg_replace('/^(?:收送机|送机|接机)\s*/u', '', $step);

            // 뒤: 수량·금액(5万·6人·3件) → 숫자
            $step = (string) preg_replace('/\s*\d+(?:\.\d+)?\s*(?:万|萬|块|塊|元|米|人|位|名|件|个|個|份|台|辆|輛)$/u', '', $step);
            $step = (string) preg_replace('/\s*\d+$/u', '', $step);
            $step = trim($step, " \t,，、/·|.~～〜-—–");

            // 차종은 어디서 끊을지 알 수 없다 — 뗀 길이마다 후보로 남기고 사전이 고르게 한다.
            // (짧게 뗀 것부터 넣어야 `金浦小车` 가 `金` 까지 가지 않는다)
            foreach (self::trailingVehicleVariants($step) as $variant) {
                $candidates[] = $variant;
            }

            if ($step === '' || $step === $current) {
                break;
            }

            $candidates[] = $step;
            $current = $step;
        }

        return $candidates;
    }

    /**
     * 끝에서 차종을 떼어낸 변형들 — 짧게 뗀 것부터 (사전에서 먼저 읽히는 쪽이 이긴다).
     *
     * 차종 판정(`isVehicleToken`)은 '포함' 기준이라 `浦小车` 도 차종으로 본다. 그래서 하나만
     * 돌려주면 `金浦小车` 가 `金` 까지 잘린다 — 길이별로 다 남기고 사전 조회가 고르게 한다.
     *
     * @return array<int, string>
     */
    private static function trailingVehicleVariants(string $value): array
    {
        $variants = [];
        $limit = min(4, mb_strlen($value) - 1);

        for ($length = 2; $length <= $limit; $length++) {
            if (self::isVehicleToken(mb_substr($value, -$length))) {
                $variants[] = trim(mb_substr($value, 0, -$length));
            }
        }

        return $variants;
    }

    /**
     * 사전 지명 뒤에 브랜드·숫자가 붙은 값 — 앞 지명만 살려 이어 둔다 (`江南Voco` → `강남Voco`).
     *
     * 뒤에 남은 값이 한글이면 지명의 일부일 수 있어 건드리지 않는다(모르는 표기는 그대로 둔다).
     */
    private static function mappedWithSuffix(string $value): ?string
    {
        $longest = null;

        foreach (self::LOCATIONS as $key => $mapped) {
            if (mb_strlen($key) < 2 || mb_strlen($key) <= mb_strlen((string) $longest) || ! str_starts_with($value, $key)) {
                continue;
            }

            $longest = $key;
        }

        if ($longest === null) {
            return null;
        }

        $suffix = trim(mb_substr($value, mb_strlen($longest)));

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\-]{0,14}$/', $suffix) !== 1) {
            return null;
        }

        return self::LOCATIONS[$longest].$suffix;
    }

    /**
     * 사전에서 읽히는 지명이면 한국어 값, 모르면 null.
     *
     * 표기는 조금씩 달라진다 — 구(區) 접미사가 붙기도 하고 안 붙기도 하고, 번체(區)로 오기도 한다.
     * 사전에 두 벌로 두지 않고 후보 표기를 만들어 본다 (`瑞草区` → `瑞草`, `中區` → `中区`).
     */
    private static function mappedLocation(string $value): ?string
    {
        $override = self::mappedOverrides('location');

        foreach (self::locationCandidates($value) as $candidate) {
            $mapped = $override[$candidate] ?? self::LOCATIONS[$candidate] ?? null;

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    /**
     * 사전에서 찾아볼 후보 표기 — 원문 · 번체(區)를 간체(区)로 바꾼 것 · 구 접미사를 뗀 것.
     *
     * @return array<int, string>
     */
    private static function locationCandidates(string $value): array
    {
        $base = [$value, str_replace('區', '区', $value)];
        $trimmed = array_map(
            static fn (string $candidate): string => (string) preg_replace('/区$/u', '', $candidate),
            $base,
        );

        return array_values(array_filter(
            array_unique([...$base, ...$trimmed]),
            static fn (string $candidate): bool => $candidate !== '',
        ));
    }

    /**
     * `A送B` 처럼 한 칸에 두 지점이 이어 붙은 표기를 'A—B' 로 편다.
     *
     * 두 쪽 모두 사전에서 읽힐 때만 편다 — 한 쪽이라도 모르면 단독 接/送 이 다른 낱말에
     * 붙은 것일 수 있으므로 원문을 그대로 둔다.
     */
    private static function routeThrough(string $value): ?string
    {
        if (preg_match('/^(.+?)[接送](.+)$/u', $value, $matches) !== 1) {
            return null;
        }

        $from = self::mappedLocation(trim($matches[1]));
        $to = self::mappedLocation(trim($matches[2]));

        if ($from === null || $to === null) {
            return null;
        }

        return $from.'—'.$to;
    }

    /**
     * 차량 표기를 한국어 차종으로 바꾼다.
     */
    public static function vehicleType(?string $vehicleType): ?string
    {
        if ($vehicleType === null) {
            return null;
        }

        $normalized = trim($vehicleType);

        if ($normalized === '') {
            return '';
        }

        $mapped = self::mappedOverrides('vehicle')[$normalized] ?? null;

        if ($mapped !== null) {
            return $mapped;
        }

        $contains = static fn (string $needle): bool => str_contains($normalized, $needle);

        return match (true) {
            // 9인승 의자배치 표기 — 333=3-3-3, 2223=2-2-2-3
            $normalized === '333', $contains('需要333') => '스타리아 9인승(3-3-3)',
            $normalized === '2223', $contains('2223') => '스타리아 9인승(2-2-2-3)',
            $normalized === '222' => '카니발 7인승(2-2-2)',
            $normalized === '新卡起', $normalized === '全部新卡' => '더뉴카니발 4세대',
            $contains('新卡') && $contains('卡起') => '더뉴카니발 4세대',
            $contains('卡起') => '카니발부터 가능',
            $contains('卡或利亚') => '카니발 또는 스타리아',
            $normalized === '카니발' => '카니발',
            $contains('利亚7') && $contains('333') => '스타리아 7인승 또는 9인승(3-3-3)',
            $contains('新卡') && $contains('利亚') => '더뉴카니발 4세대 또는 스타리아',
            // 위 조합 규칙을 모두 지난 뒤의 단독 표기 — "新卡"만 있으면 더뉴카니발로 본다
            $contains('新卡') => '더뉴카니발 4세대',
            // 공백이 섞여 들어온 표기(利亚 7)도 같은 값으로 본다
            str_replace(' ', '', $normalized) === '利亚7' => '스타리아 7인승',
            $normalized === '利亚' => '스타리아',
            $normalized === '小车🉑', $contains('小车') => '소형 승용차(세단/SUV)',
            $normalized === '大车' => '대형차',
            // 위챗 모니터 유입에서 확인된 표기 — 卡=카니발, 利亚=스타리아
            $normalized === '九卡' => '카니발 9인승',
            $normalized === '利亚9' => '스타리아 9인승',
            $normalized === '卡尼巴' => '카니발',
            // 잔여 유입 정리(2026-09-13)에서 확인된 표기
            $contains('埃尔法'), $contains('阿尔法') => '알파드',
            default => $normalized,
        };
    }

    /**
     * 차종 표기인지 — 지명 칸에 섞여 들어온 차종(`埃尔法`)을 가려낸다.
     *
     * 사전에 있는 차종 표기만 true 다. 모르는 값은 false 로 두어, 지명을 잘못 지우는 일이 없게 한다.
     */
    public static function isVehicleToken(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim($value);

        return $trimmed !== '' && self::vehicleType($trimmed) !== $trimmed;
    }

    /**
     * 화면에 보여 줄 위치 표기.
     *
     * 사전으로 풀리지 않는 한자가 남아 있으면(대화 잡음이 위치로 들어온 경우 등)
     * 엉뚱한 값을 그대로 노출하지 않도록 '미정'으로 둔다. 저장 값은 그대로 두고 표시할 때만 바꾼다.
     */
    public static function displayLocation(?string $location): string
    {
        $normalized = self::location($location);

        if ($normalized === null || trim($normalized) === '') {
            return '-';
        }

        return self::containsChinese($normalized) ? '미정' : $normalized;
    }

    /**
     * 화면에 보여 줄 차량 표기 — 미지정은 '차량 무관', 사전에 없는 한자는 '차량 미정'.
     */
    public static function displayVehicle(?string $vehicleType): string
    {
        $normalized = self::vehicleType($vehicleType);

        if ($normalized === null || trim($normalized) === '') {
            return '차량 무관';
        }

        return self::containsChinese($normalized) ? '차량 미정' : $normalized;
    }

    /**
     * 화면에 보여 줄 노선 — "출발 → 도착".
     *
     * 유입 원문의 중국어가 목록·알림·정산 카드에 그대로 새지 않도록 목록 행과 같은 규칙을 쓴다.
     * 양쪽 모두 비어 있으면 빈 문자열을 돌려 화면에서 노선 줄을 감출 수 있게 한다.
     */
    public static function routeLabel(?string $pickup, ?string $dropoff): string
    {
        $from = self::displayLocation($pickup);
        $to = self::displayLocation($dropoff);

        if ($from === '-' && $to === '-') {
            return '';
        }

        return $from.' → '.$to;
    }

    /**
     * 값에 중국어(한자)가 남아 있는지 — 사전에 없는 표기인지 판단하는 기준.
     */
    public static function containsChinese(?string $value): bool
    {
        return $value !== null && preg_match('/\p{Han}/u', $value) === 1;
    }

    /**
     * 값에 태그로 매핑된 표기가 들어 있으면 해당 태그를 돌려준다.
     *
     * '帮划客路' 처럼 지명이 아니라 운영 지시(태그)로 쓰이는 표기를 운행 태그로 옮기기 위한 것이다.
     *
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    public static function tagsFor(array $values): array
    {
        $tags = [];

        foreach ($values as $value) {
            if ($value === null || trim($value) === '') {
                continue;
            }

            foreach (self::tagDictionary() as $term => $tag) {
                if (str_contains($value, $term)) {
                    $tags[] = $tag;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * 태그 표기 사전 — 내장 + 관리자 매핑 (관리자 매핑이 우선).
     *
     * @return array<string, string>
     */
    public static function tagDictionary(): array
    {
        return self::mappedOverrides('tag') + self::TAGS;
    }

    /**
     * 태그로 등록된 표기인지 — 지명이 아니므로 용어 사전에 미매핑으로 다시 쌓지 않는다.
     */
    public static function isTagTerm(string $term): bool
    {
        return array_key_exists($term, self::tagDictionary());
    }

    /**
     * 유입 파서(모니터)가 붙이는 내부 태그 — 기사가 볼 이유가 없어 저장·표시에서 뺀다.
     */
    private const INTERNAL_TAGS = [
        'wechat-monitor',
        'conf60',
    ];

    /**
     * 긴급 태그인지 — 붙으면 운행을 긴급(`is_priority`)으로 올려 마켓에서 먼저 보이게 한다.
     */
    public static function isUrgentTag(string $tag): bool
    {
        return in_array($tag, self::URGENT_TAGS, true);
    }

    /**
     * 내부 태그를 걷어낸 태그 목록 — 저장 직전과 카드 표시 직전에 같은 기준으로 쓴다.
     *
     * @param  array<int, string>|null  $tags
     * @return array<int, string>
     */
    public static function withoutInternalTags(?array $tags): array
    {
        return array_values(array_diff($tags ?? [], self::INTERNAL_TAGS));
    }

    /**
     * 관리자가 매핑한 값 중 해당 분야의 사전.
     *
     * DB 를 쓸 수 없는 상황(단위 테스트 등)에서는 내장 사전만으로 동작하도록 조용히 빈 배열을 돌려준다.
     *
     * @return array<string, string>
     */
    private static function mappedOverrides(string $group): array
    {
        try {
            $overrides = Cache::remember(self::CACHE_KEY, 300, static function (): array {
                $groups = [
                    OrderTerm::FIELD_PICKUP => 'location',
                    OrderTerm::FIELD_DROPOFF => 'location',
                    OrderTerm::FIELD_VEHICLE => 'vehicle',
                    // 차종도 같은 값(운행 차종)에 반영한다 — 등급/인승과 모델명을 나눠 관리할 뿐이다
                    OrderTerm::FIELD_MODEL => 'vehicle',
                    OrderTerm::FIELD_TAG => 'tag',
                ];

                $mapped = ['location' => [], 'vehicle' => [], 'tag' => []];

                OrderTerm::query()
                    ->where('status', OrderTerm::STATUS_MAPPED)
                    ->whereNotNull('mapped_to')
                    ->get(['field', 'term', 'mapped_to'])
                    ->each(function (OrderTerm $term) use (&$mapped, $groups): void {
                        $group = $groups[$term->field] ?? null;

                        if ($group !== null) {
                            $mapped[$group][$term->term] = $term->mapped_to;
                        }
                    });

                return $mapped;
            });
        } catch (Throwable) {
            return [];
        }

        return $overrides[$group] ?? [];
    }

    /**
     * 관리자 매핑이 바뀌면 캐시를 비운다.
     */
    public static function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // 캐시를 못 쓰는 환경에서는 내장 사전만 사용한다
        }
    }
}
