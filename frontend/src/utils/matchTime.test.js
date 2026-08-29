import { describe, expect, it } from 'vitest';
import { isOvernightRange, matchDateLabel, matchDateRangeLabels, matchTimeInfo } from './matchTime';

describe('matchDateLabel', () => {
    it('날짜 범위에 맞는 라벨을 반환한다', () => {
        expect(matchDateLabel('today')).toBe('오늘');
        expect(matchDateLabel('tomorrow')).toBe('내일');
        expect(matchDateLabel('today_tomorrow')).toBe('오늘/내일');
    });

    it('전체(비움)·알 수 없는 값은 빈 문자열을 반환한다', () => {
        expect(matchDateLabel('')).toBe('');
        expect(matchDateLabel(null)).toBe('');
        expect(matchDateLabel(undefined)).toBe('');
        expect(matchDateLabel('next_week')).toBe('');
    });
});

describe('isOvernightRange', () => {
    it('종료가 시작보다 이르면 자정을 넘기는 시간대다', () => {
        expect(isOvernightRange('22:00', '03:00')).toBe(true);
        expect(isOvernightRange('17:50', '02:00')).toBe(true);
    });

    it('종료가 시작과 같거나 늦으면 일반 시간대다', () => {
        expect(isOvernightRange('09:00', '18:00')).toBe(false);
        expect(isOvernightRange('09:00', '09:00')).toBe(false);
    });

    it('시작 또는 종료가 없으면 자정 넘김으로 판단하지 않는다', () => {
        expect(isOvernightRange('22:00', '')).toBe(false);
        expect(isOvernightRange('', '03:00')).toBe(false);
        expect(isOvernightRange(null, null)).toBe(false);
    });
});

describe('matchTimeInfo', () => {
    it('일반 시간대는 날짜+시작~종료 조각을 반환한다', () => {
        expect(matchTimeInfo({ start_time: '17:50', end_time: '23:00', date_range: 'today' })).toEqual({
            date: '오늘',
            start: '17:50',
            end: '23:00',
            overnight: false,
        });
    });

    it('자정을 넘기는 시간대는 다음날 배지(overnight)를 켠다', () => {
        expect(matchTimeInfo({ start_time: '22:00', end_time: '03:00', date_range: 'today_tomorrow' })).toEqual({
            date: '오늘/내일',
            start: '22:00',
            end: '03:00',
            overnight: true,
        });
    });

    it('종료 시각이 없으면 24:00으로 처리한다', () => {
        expect(matchTimeInfo({ start_time: '17:50' })).toEqual({
            date: '',
            start: '17:50',
            end: '24:00',
            overnight: false,
        });
    });

    it('시작 시각이 없으면 빈 조각을 반환한다', () => {
        expect(matchTimeInfo({})).toEqual({
            date: '',
            start: '',
            end: '24:00',
            overnight: false,
        });
        expect(matchTimeInfo(null)).toEqual({
            date: '',
            start: '',
            end: '24:00',
            overnight: false,
        });
    });
});

describe('matchDateRangeLabels', () => {
    const base = new Date(2026, 7, 29); // 2026-08-29 (오늘)

    it('자정을 넘기면 종료 날짜가 다음날이 된다', () => {
        expect(matchDateRangeLabels('today_tomorrow', '22:00', '03:00', base)).toEqual({
            start: '08-29',
            end: '08-30',
            overnight: true,
        });
    });

    it('일반 시간대는 시작·종료가 같은 날이다', () => {
        expect(matchDateRangeLabels('today_tomorrow', '09:00', '18:00', base)).toEqual({
            start: '08-29',
            end: '08-29',
            overnight: false,
        });
    });

    it('오늘 범위에서 자정을 넘기면 종료가 내일(08-30)이다', () => {
        expect(matchDateRangeLabels('today', '22:00', '03:00', base)).toEqual({
            start: '08-29',
            end: '08-30',
            overnight: true,
        });
    });

    it('내일 범위에서 자정을 넘기면 종료가 모레(08-31)이다', () => {
        expect(matchDateRangeLabels('tomorrow', '22:00', '03:00', base)).toEqual({
            start: '08-30',
            end: '08-31',
            overnight: true,
        });
    });

    it('날짜 범위가 전체(빈 값)면 날짜 라벨은 비운다', () => {
        expect(matchDateRangeLabels('', '22:00', '03:00', base)).toEqual({
            start: '',
            end: '',
            overnight: true,
        });
    });
});
