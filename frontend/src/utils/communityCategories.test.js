import { describe, expect, it } from 'vitest';
import { COMMUNITY_CATEGORIES, categoryOf } from './communityCategories';

describe('COMMUNITY_CATEGORIES', () => {
    it('7개 카테고리를 보유한다', () => {
        expect(COMMUNITY_CATEGORIES).toHaveLength(7);
    });

    it('모든 카테고리가 key/label/icon을 가진다', () => {
        for (const c of COMMUNITY_CATEGORIES) {
            expect(c.key).toBeTruthy();
            expect(c.label).toBeTruthy();
            expect(c.icon).toBeTruthy();
        }
    });

    it('key가 중복되지 않는다', () => {
        const keys = COMMUNITY_CATEGORIES.map((c) => c.key);
        expect(new Set(keys).size).toBe(keys.length);
    });
});

describe('categoryOf', () => {
    it('존재하는 key는 해당 카테고리를 반환한다', () => {
        expect(categoryOf('airport').label).toBe('공항정보');
        expect(categoryOf('money').label).toBe('수익·노하우');
    });

    it('없는 key는 기본(첫 번째) 카테고리를 반환한다', () => {
        expect(categoryOf('unknown')).toBe(COMMUNITY_CATEGORIES[0]);
    });
});
