/**
 * 한국 공휴일 (대한민국 기준) — 날짜 문자열(YYYY-MM-DD)로 공휴일 이름을 반환한다.
 *
 * - 고정 공휴일: 신정·삼일절·어린이날·현충일·광복절·개천절·한글날·성탄절
 * - 음력 공휴일(설날·부처님 오신날·추석): 양력 환산표(2025~2028) 사용
 * - 설날·추석은 전날·다음날 연휴 포함
 * - 대체공휴일: 어린이날·설날·추석·부처님 오신날·성탄절이 주말과 겹치면
 *   다음 첫 비공휴일 평일로 지정
 */

const SOLAR_HOLIDAYS = [
    ['01-01', '신정'],
    ['03-01', '삼일절'],
    ['05-05', '어린이날'],
    ['06-06', '현충일'],
    ['08-15', '광복절'],
    ['10-03', '개천절'],
    ['10-09', '한글날'],
    ['12-25', '성탄절'],
];

// 음력 기반 공휴일의 양력 날짜 (MM-DD) — 설날·부처님 오신날·추석
const LUNAR_HOLIDAYS = {
    2025: { '01-29': '설날', '05-05': '부처님 오신날', '10-06': '추석' },
    2026: { '02-17': '설날', '05-24': '부처님 오신날', '09-25': '추석' },
    2027: { '02-07': '설날', '05-13': '부처님 오신날', '09-15': '추석' },
    2028: { '01-27': '설날', '05-02': '부처님 오신날', '10-03': '추석' },
};

// 대체공휴일 대상 공휴일
const SUBSTITUTE_ELIGIBLE = new Set(['어린이날', '설날', '추석', '부처님 오신날', '성탄절']);

const toStr = (y, m, d) => {
    const dt = new Date(y, m - 1, d);

    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};

const isWeekend = (y, m, d) => {
    const dow = new Date(y, m - 1, d).getDay();

    return dow === 0 || dow === 6;
};

const YEARS = [2025, 2026, 2027, 2028];

// 'YYYY-MM-DD' -> 공휴일 이름
const holidayMap = new Map();

for (const y of YEARS) {
    // 고정 공휴일
    for (const [md, name] of SOLAR_HOLIDAYS) {
        holidayMap.set(`${y}-${md}`, name);
    }

    // 음력 공휴일 + 설날·추석 연휴(전날·다음날)
    const lunar = LUNAR_HOLIDAYS[y] ?? {};

    for (const [md, name] of Object.entries(lunar)) {
        const [m, d] = md.split('-').map(Number);
        const dateKey = toStr(y, m, d);
        const existing = holidayMap.get(dateKey);

        // 어린이날과 부처님 오신날이 겹치는 날 등 — 두 이름을 함께 표시
        holidayMap.set(dateKey, existing ? `${existing}·${name}` : name);

        if (name === '설날' || name === '추석') {
            holidayMap.set(toStr(y, m, d - 1), `${name} 연휴`);
            holidayMap.set(toStr(y, m, d + 1), `${name} 연휴`);
        }
    }
}

// 대체공휴일 계산
const addSubstitute = (y, m, d, name, hasExtendedRange) => {
    const days = hasExtendedRange ? [d - 1, d, d + 1] : [d];

    if (!days.some((dd) => isWeekend(y, m, dd))) {
        return;
    }

    // 범위 마지막 날 이후의 첫 비공휴일 평일
    let dd = Math.max(...days) + 1;
    let guard = 0;

    while (guard++ < 7) {
        const dow = new Date(y, m - 1, dd).getDay();
        const dateKey = toStr(y, m, dd);

        if (dow !== 0 && dow !== 6 && !holidayMap.has(dateKey)) {
            holidayMap.set(dateKey, `${name} 대체공휴일`);

            return;
        }

        dd++;
    }
};

for (const y of YEARS) {
    for (const [md, name] of SOLAR_HOLIDAYS) {
        if (SUBSTITUTE_ELIGIBLE.has(name)) {
            const [m, d] = md.split('-').map(Number);

            addSubstitute(y, m, d, name, false);
        }
    }

    for (const [md, name] of Object.entries(LUNAR_HOLIDAYS[y] ?? {})) {
        if (SUBSTITUTE_ELIGIBLE.has(name)) {
            const [m, d] = md.split('-').map(Number);

            // 연휴(전날·다음날)가 있는 명절(설날·추석)만 범위 확장
            addSubstitute(y, m, d, name, name === '설날' || name === '추석');
        }
    }
}

/**
 * 날짜(YYYY-MM-DD)의 공휴일 이름을 반환한다. 공휴일이 아니면 빈 문자열.
 */
export const koreanHolidayName = (dateStr) => {
    if (!dateStr) {
        return '';
    }

    return holidayMap.get(dateStr) ?? '';
};
