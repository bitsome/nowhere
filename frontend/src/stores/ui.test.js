import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useUiStore } from './ui';

describe('useUiStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('초기 상태를 가진다', () => {
        const store = useUiStore();
        expect(store.communityMyPostsOnly).toBe(false);
        expect(store.communitySort).toBe('latest');
        expect(store.orderFormActive).toBe(false);
        expect(store.filterActive).toBe(false);
        expect(store.actionName).toBeNull();
        expect(store.actionSeq).toBe(0);
    });

    it('emitAction이 액션 이름과 시퀀스를 갱신한다', () => {
        const store = useUiStore();
        store.emitAction('filter');
        expect(store.actionName).toBe('filter');
        expect(store.actionSeq).toBe(1);

        store.emitAction('refresh');
        expect(store.actionName).toBe('refresh');
        expect(store.actionSeq).toBe(2);
    });
});
