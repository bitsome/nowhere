import { reactive } from 'vue';

/**
 * URL별 이미지 로딩 상태 추적 — 'loading' | 'loaded' | 'error'.
 * 말풍선·갤러리 등 <img>의 @load/@error와 함께 사용해
 * 로딩 스켈레톤/실패 플레이스홀더를 간단히 표시한다.
 */
export function useImageStatus() {
    const states = reactive({});

    const statusOf = (url) => states[url] ?? 'loading';

    const markLoaded = (url) => {
        states[url] = 'loaded';
    };

    const markError = (url) => {
        states[url] = 'error';
    };

    return { statusOf, markLoaded, markError };
}
