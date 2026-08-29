import { describe, expect, it } from 'vitest';
import { LEVEL_LIST, LEVEL_TIERS, XP_RULES, tierForLevel } from './levels';

describe('levels', () => {
    it('레벨 1~10 전체가 존재한다', () => {
        expect(LEVEL_LIST).toHaveLength(10);
        expect(LEVEL_LIST.map((l) => l.level)).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    });

    it('minXp가 단조 증가한다', () => {
        for (let i = 1; i < LEVEL_LIST.length; i += 1) {
            expect(LEVEL_LIST[i].minXp).toBeGreaterThan(LEVEL_LIST[i - 1].minXp);
        }
    });

    it('tierForLevel이 레벨 경계를 정확히 구분한다', () => {
        expect(tierForLevel(1).label).toBe('신입');
        expect(tierForLevel(2).label).toBe('신입');
        expect(tierForLevel(3).label).toBe('베테랑');
        expect(tierForLevel(5).label).toBe('스타');
        expect(tierForLevel(10).label).toBe('레전드');
    });

    it('범위 밖 레벨은 첫 티어로 폴백한다', () => {
        expect(tierForLevel(99).label).toBe('신입');
    });

    it('XP 규칙이 비어있지 않다', () => {
        expect(XP_RULES.length).toBeGreaterThan(0);
    });

    it('티어가 5개다', () => {
        expect(LEVEL_TIERS).toHaveLength(5);
    });
});
