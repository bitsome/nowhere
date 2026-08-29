import { describe, expect, it } from 'vitest';
import { useMatchSettings } from './useMatchSettings';

const setup = () =>
    useMatchSettings({
        message: { warning() {}, success() {}, error() {} },
        loadMatchedOrders: async () => {},
    });

describe('useMatchSettings - matchRestSummary', () => {
    it('요일·지역·인원·금액을 묶어 요약한다', () => {
        const { matchRestSummary } = setup();

        expect(
            matchRestSummary({ days: [1, 3], area: '인천공항', max_passengers: 7, min_revenue: 50000 }),
        ).toBe('월·수 · 출발 인천공항 · 최대 7인 · 50,000원 이상');
    });

    it('조건이 없으면 매일만 반환한다', () => {
        const { matchRestSummary } = setup();

        expect(matchRestSummary({ days: [], area: '', max_passengers: null, min_revenue: 0 })).toBe('매일');
    });

    it('금액·인원 제한이 없으면 생략한다', () => {
        const { matchRestSummary } = setup();

        expect(matchRestSummary({ days: [], area: '서울 강남', max_passengers: null, min_revenue: 0 })).toBe(
            '매일 · 출발 서울 강남',
        );
    });
});
