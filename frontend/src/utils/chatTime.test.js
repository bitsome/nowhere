import { describe, expect, it } from 'vitest';
import { formatClock, formatDayLabel, getChatTimestamp, isSameDay } from './chatTime';

describe('getChatTimestamp', () => {
    it('빈 값이면 null을 반환한다', () => {
        expect(getChatTimestamp('')).toBeNull();
        expect(getChatTimestamp(null)).toBeNull();
        expect(getChatTimestamp(undefined)).toBeNull();
    });

    it('ISO 문자열을 Date로 변환한다', () => {
        const ts = new Date(2026, 7, 12, 9, 30).toISOString();
        const d = getChatTimestamp(ts);
        expect(d).toBeInstanceOf(Date);
        expect(d.getMonth()).toBe(7);
        expect(d.getDate()).toBe(12);
    });

    it('상대시간을 대략적 Date로 추정한다', () => {
        const before = Date.now();
        const d = getChatTimestamp('5 minutes ago');
        const after = Date.now();
        expect(d.getTime()).toBeGreaterThanOrEqual(before - 3600 * 1000);
        expect(d.getTime()).toBeLessThanOrEqual(after);
    });

    it('인식할 수 없는 값이면 null을 반환한다', () => {
        expect(getChatTimestamp('zzz')).toBeNull();
    });
});

describe('isSameDay', () => {
    it('같은 날짜면 true다', () => {
        expect(isSameDay(new Date(2026, 7, 12, 1, 0), new Date(2026, 7, 12, 23, 59))).toBe(true);
    });

    it('다른 날짜면 false다', () => {
        expect(isSameDay(new Date(2026, 7, 12), new Date(2026, 7, 13))).toBe(false);
    });
});

describe('formatClock', () => {
    it('오전/오후를 붙여 표시한다', () => {
        expect(formatClock(new Date(2026, 7, 12, 9, 5))).toBe('오전 9:05');
        expect(formatClock(new Date(2026, 7, 12, 14, 30))).toBe('오후 2:30');
        expect(formatClock(new Date(2026, 7, 12, 0, 0))).toBe('오전 12:00');
    });

    it('빈 값이면 빈 문자열을 반환한다', () => {
        expect(formatClock(null)).toBe('');
    });
});

describe('formatDayLabel', () => {
    it('어제는 "어제"로 표시한다', () => {
        const now = new Date();
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        expect(formatDayLabel(yesterday)).toBe('어제');
    });

    it('올해 날짜는 "M월 D일"로 표시한다', () => {
        const now = new Date();
        expect(formatDayLabel(new Date(now.getFullYear(), 7, 12))).toBe('8월 12일');
    });

    it('다른 해는 "YYYY년 M월 D일"로 표시한다', () => {
        const now = new Date();
        expect(formatDayLabel(new Date(now.getFullYear() - 1, 11, 31))).toBe(`${now.getFullYear() - 1}년 12월 31일`);
    });
});
