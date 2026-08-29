/**
 * 매칭 설정 시간대 표시 공통 로직 — 홈 '빠른 매칭' 칩과 매칭 설정 목록에서 함께 쓴다.
 *
 * 자정을 넘기는 시간대(종료 < 시작)는 시작이 오늘, 종료가 다음날인 야간 시간대로 처리한다.
 * 예) 시작 22:00 ~ 종료 03:00 → '오늘 22:00~[다음날]03:00'
 */

// 날짜 범위 → '오늘'/'내일'/'오늘/내일' (비우면 전체라 빈 문자열)
export function matchDateLabel(dateRange) {
    const labels = { today: '오늘', tomorrow: '내일', today_tomorrow: '오늘/내일' };

    return labels[dateRange] || '';
}

// 종료가 시작보다 이르면 자정을 넘기는 야간 시간대
export function isOvernightRange(startTime, endTime) {
    return Boolean(startTime && endTime && startTime > endTime);
}

/**
 * 매칭 설정 시간대 표시 조각 — 템플릿에서 날짜·시작·종료·다음날 배지 여부를 그대로 쓴다.
 *
 * @param {{start_time?: string|null, end_time?: string|null, date_range?: string|null}} pref
 * @returns {{date: string, start: string, end: string, overnight: boolean}}
 */
export function matchTimeInfo(pref = {}) {
    pref = pref || {};

    const start = pref.start_time || '';
    const end = pref.end_time || '24:00';

    return {
        date: matchDateLabel(pref.date_range),
        start,
        end,
        overnight: isOvernightRange(start, end),
    };
}

/**
 * 시작/종료 시각 앞에 표시할 날짜(MM-DD) — 날짜 범위와 자정 넘김 여부로 계산한다.
 * 예) range='today_tomorrow', 22:00~03:00 → 시작 '08-29', 종료 '08-30'(다음날)
 * baseDate를 주입하면 날짜 계산을 고정할 수 있어 테스트가 쉽다.
 *
 * @param {string|null} range 날짜 범위(today/tomorrow/today_tomorrow/빈 값)
 * @param {string|number|null} startTime 시작 시각
 * @param {string|number|null} endTime 종료 시각
 * @param {Date} [baseDate] 기준일 (기본 오늘)
 * @returns {{start: string, end: string, overnight: boolean}}
 */
export function matchDateRangeLabels(range, startTime, endTime, baseDate = new Date()) {
    const overnight = isOvernightRange(startTime, endTime);

    const fmt = (offset) => {
        const d = new Date(baseDate);
        d.setDate(d.getDate() + offset);

        return `${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };

    if (range === 'today') {
        return { start: fmt(0), end: fmt(overnight ? 1 : 0), overnight };
    }

    if (range === 'tomorrow') {
        return { start: fmt(1), end: fmt(overnight ? 2 : 1), overnight };
    }

    if (range === 'today_tomorrow') {
        return { start: fmt(0), end: fmt(overnight ? 1 : 0), overnight };
    }

    return { start: '', end: '', overnight };
}
