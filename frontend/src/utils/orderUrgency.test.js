import { describe, expect, it } from 'vitest';
import { isPriorityOrder, isUrgentOrder, serviceDateTime } from './orderUrgency';

// 기준 시각 고정 — 자정 경계 판정이 실행 시각에 흔들리지 않게 한다
const NOW = new Date(2026, 8, 14, 12, 0, 0);

describe('serviceDateTime', () => {
    it('날짜·시각이 모두 있으면 로컬 일시를 만든다', () => {
        expect(serviceDateTime('2026-09-14', '07:30')).toEqual(new Date(2026, 8, 14, 7, 30, 0, 0));
    });

    it('한쪽이 비었거나 읽지 못하면 null', () => {
        expect(serviceDateTime('2026-09-14', null)).toBeNull();
        expect(serviceDateTime(null, '07:30')).toBeNull();
        expect(serviceDateTime('2026-09-14', '오전')).toBeNull();
    });
});

describe('isUrgentOrder', () => {
    it('오늘 남은 운행은 남은 시간과 무관하게 임박이다', () => {
        // 9시간 뒤 — 상한(120분)을 두지 않는 서버 규칙과 같아야 한다
        expect(isUrgentOrder({ service_date: '2026-09-14', service_time: '21:00' }, NOW)).toBe(true);
    });

    it('이미 지난 오늘 운행은 임박이 아니다', () => {
        expect(isUrgentOrder({ service_date: '2026-09-14', service_time: '09:00' }, NOW)).toBe(false);
    });

    it('내일 운행은 시각이 남았어도 임박이 아니다', () => {
        expect(isUrgentOrder({ service_date: '2026-09-15', service_time: '01:00' }, NOW)).toBe(false);
    });

    it('일시가 없으면 임박이 아니다', () => {
        expect(isUrgentOrder({ service_date: null, service_time: null }, NOW)).toBe(false);
    });
});

describe('isPriorityOrder', () => {
    it('등록자가 긴급으로 올린 운행은 시각과 무관하게 긴급이다', () => {
        expect(isPriorityOrder({
            is_priority: true,
            status: 'published',
            service_date: '2026-09-14',
            service_time: '21:00',
        }, NOW)).toBe(true);
    });

    it('아직 기사가 없는 공개 운행이 시각을 지나면 긴급이다', () => {
        expect(isPriorityOrder({
            is_priority: false,
            status: 'published',
            service_date: '2026-09-14',
            service_time: '09:00',
        }, NOW)).toBe(true);
    });

    it('기사가 정해진 운행은 시각이 지나도 긴급이 아니다', () => {
        expect(isPriorityOrder({
            is_priority: false,
            status: 'driving',
            service_date: '2026-09-14',
            service_time: '09:00',
        }, NOW)).toBe(false);
    });

    it('내일 시각이 지난 경우는 긴급이 아니다', () => {
        expect(isPriorityOrder({
            is_priority: false,
            status: 'published',
            service_date: '2026-09-13',
            service_time: '09:00',
        }, NOW)).toBe(false);
    });
});
