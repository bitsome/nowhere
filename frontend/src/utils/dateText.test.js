import { describe, expect, it } from 'vitest';
import { relativeDateLabel } from './dateText';

// 로컬 기준 오늘에서 offsetDays만큼 떨어진 날짜 "YYYY-MM-DD" (헬퍼와 동일하게 로컬 날짜 해석)
const iso = (offsetDays) => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);

    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
};

describe('relativeDateLabel', () => {
    it('sortDate가 없으면 원본 날짜 텍스트를 그대로 반환한다', () => {
        expect(relativeDateLabel('9/2(수)', undefined)).toBe('9/2(수)');
        expect(relativeDateLabel('9/2(수)', '')).toBe('9/2(수)');
        expect(relativeDateLabel(undefined, undefined)).toBe('');
    });

    it('오늘은 "오늘"로 표시한다', () => {
        expect(relativeDateLabel('9/2(수)', iso(0))).toBe('오늘');
    });

    it('내일·모레는 상대 라벨로 표시한다', () => {
        expect(relativeDateLabel('9/3(목)', iso(1))).toBe('내일');
        expect(relativeDateLabel('9/4(금)', iso(2))).toBe('모레');
    });

    it('그 이후 날짜는 원본 날짜 텍스트를 유지한다', () => {
        expect(relativeDateLabel('9/8(화)', iso(6))).toBe('9/8(화)');
    });

    it('잘못된 sortDate는 원본 텍스트로 폴백한다', () => {
        expect(relativeDateLabel('9/2(수)', 'not-a-date')).toBe('9/2(수)');
    });
});
