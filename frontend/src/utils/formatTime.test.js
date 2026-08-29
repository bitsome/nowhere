import { describe, expect, it } from 'vitest';
import { formatTime } from './formatTime';

describe('formatTime', () => {
    it('빈 값이면 빈 문자열을 반환한다', () => {
        expect(formatTime('')).toBe('');
        expect(formatTime(null)).toBe('');
        expect(formatTime(undefined)).toBe('');
    });

    it('상대시간을 한국어로 변환한다', () => {
        expect(formatTime('just now')).toBe('방금');
        expect(formatTime('5 seconds ago')).toBe('5초 전');
        expect(formatTime('10 minutes ago')).toBe('10분 전');
        expect(formatTime('3 hours ago')).toBe('3시간 전');
        expect(formatTime('2 days ago')).toBe('2일 전');
        expect(formatTime('1 week ago')).toBe('1주 전');
        expect(formatTime('4 months ago')).toBe('4개월 전');
    });

    it('ISO 날짜는 "M월 D일"로 변환한다', () => {
        // 로컬 정오 기준이라 시간대와 무관하게 8월 12일로 유지된다
        const local = new Date(2026, 7, 12, 12, 0).toISOString();
        expect(formatTime(local)).toBe('8월 12일');
    });

    it('인식할 수 없는 값은 그대로 반환한다', () => {
        expect(formatTime('???')).toBe('???');
    });
});
