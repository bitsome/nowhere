import { computed, ref } from 'vue';

/**
 * 경로 지도 — 출발지/도착지 전환과 외부 지도 링크를 담당한다.
 *
 * @param {{ order: import('vue').Ref<object|null> }} options 운행 데이터 ref
 */
export function useOrderMap({ order }) {
    const mapTarget = ref('pickup');
    const mapOpen = ref(false);

    const mapQueryLabel = computed(() => (mapTarget.value === 'pickup' ? '출발지' : '도착지'));

    const mapQuery = computed(() =>
        (mapTarget.value === 'pickup' ? order.value?.pickup_location : order.value?.dropoff_location) || '',
    );

    const mapEmbedUrl = computed(() =>
        mapQuery.value ? `https://maps.google.com/maps?q=${encodeURIComponent(mapQuery.value)}&z=15&output=embed&hl=ko` : '',
    );

    const mapGoogleUrl = computed(() => (mapQuery.value ? `https://maps.google.com/maps?q=${encodeURIComponent(mapQuery.value)}` : '#'));
    const mapNaverUrl = computed(() => (mapQuery.value ? `https://map.naver.com/v5/search/${encodeURIComponent(mapQuery.value)}` : '#'));
    const mapKakaoUrl = computed(() => (mapQuery.value ? `https://map.kakao.com/link/search/${encodeURIComponent(mapQuery.value)}` : '#'));

    return {
        mapTarget,
        mapOpen,
        mapQueryLabel,
        mapQuery,
        mapEmbedUrl,
        mapGoogleUrl,
        mapNaverUrl,
        mapKakaoUrl,
    };
}
