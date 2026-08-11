import { defineStore } from 'pinia';

/**
 * 상단 헤더의 페이지별 액션을 뷰에 전달하는 공용 스토어.
 * 헤더에서 emitAction(name)을 호출하면, 해당 페이지 뷰가 watch로 받아 처리한다.
 */
export const useUiStore = defineStore('ui', {
    state: () => ({
        // 커뮤니티 — 헤더 메뉴 상태
        communityMyPostsOnly: false,
        communitySort: 'latest', // latest | popular
        // 액션 버스 (헤더 → 뷰)
        actionName: null,
        actionSeq: 0,
    }),
    actions: {
        emitAction(name) {
            this.actionName = name;
            this.actionSeq += 1;
        },
    },
});
