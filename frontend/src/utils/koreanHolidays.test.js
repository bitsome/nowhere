import { describe, expect, it } from 'vitest';
import { koreanHolidayName } from './koreanHolidays';

describe('koreanHolidayName', () => {
    it('빈 값이면 빈 문자열을 반환한다', () => {
        expect(koreanHolidayName('')).toBe('');
        expect(koreanHolidayName(null)).toBe('');
        expect(koreanHolidayName(undefined)).toBe('');
    });

    it('고정 공휴일을 반환한다 (2026)', () => {
        expect(koreanHolidayName('2026-01-01')).toBe('신정');
        expect(koreanHolidayName('2026-03-01')).toBe('삼일절');
        expect(koreanHolidayName('2026-08-15')).toBe('광복절');
        expect(koreanHolidayName('2026-12-25')).toBe('성탄절');
    });

    it('음력 공휴일과 명절 연휴를 반환한다 (2026 설날·추석)', () => {
        expect(koreanHolidayName('2026-02-16')).toBe('설날 연휴');
        expect(koreanHolidayName('2026-02-17')).toBe('설날');
        expect(koreanHolidayName('2026-02-18')).toBe('설날 연휴');

        expect(koreanHolidayName('2026-09-24')).toBe('추석 연휴');
        expect(koreanHolidayName('2026-09-25')).toBe('추석');
        expect(koreanHolidayName('2026-09-26')).toBe('추석 연휴');
    });

    it('주말과 겹친 명절 다음 첫 평일에 대체공휴일을 지정한다', () => {
        // 2026 추석(09-25 금) 연휴가 주말(09-26 토)과 겹침 → 09-28(월) 대체공휴일
        expect(koreanHolidayName('2026-09-28')).toBe('추석 대체공휴일');
    });

    it('공휴일이 아니면 빈 문자열을 반환한다', () => {
        expect(koreanHolidayName('2026-09-07')).toBe('');
    });
});
