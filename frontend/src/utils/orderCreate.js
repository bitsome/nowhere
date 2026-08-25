// 운행 등록 공용 상수·순수 함수 — 폼/셋트/스케줄러 컴포저블에서 공유한다.

export const SERVICE_OPTIONS = [
    { value: 'pickup', label: '픽업' },
    { value: 'sending', label: '공항샌딩' },
    { value: 'landing', label: '공항랜딩' },
];

// AI 구조화는 한글 라벨(픽업/샌딩/랜딩)로 반환한다 → 코드로 매핑
const SERVICE_CODE_BY_LABEL = { 픽업: 'pickup', 샌딩: 'sending', 랜딩: 'landing', 혼합: 'pickup' };

export const SERVICE_LABELS = { pickup: '픽업', sending: '공항샌딩', landing: '공항랜딩' };

export const toServiceCode = (raw) => {
    if (SERVICE_OPTIONS.some((o) => o.value === raw)) {
        return raw;
    }

    return SERVICE_CODE_BY_LABEL[raw] ?? 'pickup';
};

// 날짜 변환: AI의 "8월3일"/"3号" → "YYYY-MM-DD" (연도는 올해 기준)
export const toIsoDate = (raw) => {
    const value = (raw ?? '').trim();

    if (!value) {
        return '';
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }

    let month = null;
    let day = null;

    if (/(\d{1,2})월(\d{1,2})일/.test(value)) {
        month = Number(RegExp.$1);
        day = Number(RegExp.$2);
    } else if (/^(\d{1,2})[号日]$/.test(value)) {
        month = new Date().getMonth() + 1;
        day = Number(RegExp.$1);
    }

    if (month === null || day === null) {
        return value;
    }

    const year = new Date().getFullYear();

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const WEEKDAYS = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];

// "YYYY-MM-DD" → "월요일" 등 요일
export const weekdayOf = (isoDate) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
        return '';
    }

    return WEEKDAYS[new Date(`${isoDate}T00:00:00`).getDay()];
};

// 차량 문자열 힌트 — 셋트 일정 파싱에서 본문에서 차량명을 찾을 때 사용
export const VEHICLE_HINTS = ['스타리아 11인승', '스타리아 9인승', '스타리아 7인승', '더뉴카니발', '카니발', '스타렉스', '그랜버', '그랜저', '소형 승용차'];

// 차량 선택 옵션 — 실제 마켓 등록 데이터 기준 (태그 허용으로 자유 입력도 유지)
export const VEHICLE_OPTIONS = [
    { label: '스타리아 7인승', value: '스타리아 7인승' },
    { label: '스타리아 9인승', value: '스타리아 9인승' },
    { label: '스타리아 11인승', value: '스타리아 11인승' },
    { label: '카니발', value: '카니발' },
    { label: '더뉴카니발', value: '더뉴카니발' },
    { label: '스타렉스', value: '스타렉스' },
    { label: '그랜버', value: '그랜버' },
    { label: '그랜저', value: '그랜저' },
    { label: '소형 승용차', value: '소형 승용차' },
];

// "8/10 09:00 인천공항→명동 카니발 3명 200000원" → 셋트 일정 1건
export const parseSetLine = (line) => {
    const item = {
        scheduled_time: '',
        service_date: null,
        service_time: null,
        service_type: 'pickup',
        pickup_location: '',
        dropoff_location: '',
        flight_number: '',
        passenger_count: null,
        luggage_count: null,
        expected_revenue: null,
        vehicle_type: '',
    };

    // 날짜: 2026-08-10 / 8-10 / 8/10 / 8.10
    let match = line.match(/(\d{4})-(\d{1,2})-(\d{1,2})/);

    if (match) {
        item.service_date = `${match[1]}-${match[2].padStart(2, '0')}-${match[3].padStart(2, '0')}`;
    } else {
        match = line.match(/(\d{1,2})[/.](\d{1,2})/);

        if (match) {
            const year = new Date().getFullYear();

            item.service_date = `${year}-${match[1].padStart(2, '0')}-${match[2].padStart(2, '0')}`;
        }
    }

    // 시간: 09:00 / 9:30 (24시간제)
    match = line.match(/(\d{1,2}):(\d{2})/);

    if (match) {
        item.service_time = `${match[1].padStart(2, '0')}:${match[2]}`;
    } else {
        // 한글 시간: 오전 9시 / 오후 3시 30분 / 새벽 6시
        match = line.match(/(오전|오후|새벽|저녁|아침|낮)\s*(\d{1,2})\s*시(?:\s*(\d{1,2})\s*분?)?/);

        if (match) {
            const ampm = match[1];
            let hour = parseInt(match[2], 10);
            const minute = match[3] ? parseInt(match[3], 10) : 0;

            if (ampm === '오후' || ampm === '저녁') {
                hour = hour < 12 ? hour + 12 : hour;
            }

            if ((ampm === '오전' || ampm === '아침') && hour === 12) {
                hour = 0;
            }

            item.service_time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        }
    }

    // 인원: 3명
    match = line.match(/(\d+)\s*명/);

    if (match) {
        item.passenger_count = parseInt(match[1], 10);
    }

    // 금액: 200000원 / 200,000원 / 20만원
    match = line.match(/(\d{1,3}(?:,\d{3})+|\d+)\s*만원/);

    if (match) {
        item.expected_revenue = parseFloat(match[1].replace(/,/g, '')) * 10000;
    } else {
        match = line.match(/(\d{1,3}(?:,\d{3})*|\d+)\s*원/);

        if (match) {
            item.expected_revenue = parseFloat(match[1].replace(/,/g, ''));
        }
    }

    // 차량: 알려진 차량명 매칭
    const vehicle = VEHICLE_HINTS.find((v) => line.includes(v));

    if (vehicle) {
        item.vehicle_type = vehicle;
    }

    // 노선: 출발 → 도착 (날짜/시간/금액/인원/차량 토큰을 뺀 나머지에서 분리)
    let rest = line
        .replace(/\d{4}-\d{1,2}-\d{1,2}/, '')
        .replace(/(\d{1,2})[/.](\d{1,2})/, '')
        .replace(/\d{1,2}:\d{2}/, '')
        .replace(/(오전|오후|새벽|저녁|아침|낮)\s*\d{1,2}\s*시(?:\s*\d{1,2}\s*분?)?/, '')
        .replace(/[\d,]+만?원/, '')
        .replace(/\d+\s*명(?![가-힣])/, '');

    VEHICLE_HINTS.forEach((v) => {
        rest = rest.replace(v, '');
    });

    rest = rest
        .replace(/->/g, ' → ')
        .replace(/[→~>]+/g, ' → ')
        .replace(/-+/g, ' ')
        .replace(/에서/g, ' → ')
        .replace(/\s+/g, ' ')
        .trim();

    const [pickup, ...dropParts] = rest.split(' → ').map((part) => part.trim()).filter(Boolean);

    if (pickup) {
        item.pickup_location = pickup;
    }

    if (dropParts.length) {
        item.dropoff_location = dropParts.join(' → ');
    }

    return item;
};
