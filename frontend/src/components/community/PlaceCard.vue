<script setup>
import BaseIcon from '../common/BaseIcon.vue';

/**
 * 여행지 맛집 카드 — 상호·지역·주소와 지도 링크를 구조화해 보여준다.
 * 피드 카드와 상세 화면에서 공용으로 쓴다.
 */
defineOptions({ name: 'PlaceCard' });

defineProps({
    /** { name, region, address, map_url } */
    place: { type: Object, required: true },
});
</script>

<template>
    <div class="place" @click.stop>
        <div class="place__head">
            <BaseIcon name="food" :size="15" />
            <span v-if="place.region" class="place__region">{{ place.region }}</span>
        </div>

        <strong class="place__name">{{ place.name }}</strong>

        <p v-if="place.address" class="place__address">
            <BaseIcon name="location" :size="13" />
            {{ place.address }}
        </p>

        <a
            v-if="place.map_url"
            :href="place.map_url"
            target="_blank"
            rel="noopener noreferrer"
            class="place__map"
        >
            지도에서 보기
            <BaseIcon name="arrow-forward" :size="13" />
        </a>
    </div>
</template>

<style scoped>
.place {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 10px 0;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: color-mix(in srgb, var(--brand) 4%, var(--surface));
}

.place__head {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--brand);
}

.place__region {
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.place__name {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    word-break: break-word;
}

.place__address {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    color: var(--text-muted);
    font-size: 11px;
    word-break: break-word;
}

.place__address svg {
    flex-shrink: 0;
    width: 13px;
    height: 13px;
}

.place__map {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    align-self: flex-start;
    margin-top: 2px;
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--brand) 35%, transparent);
    background: color-mix(in srgb, var(--brand) 6%, transparent);
    color: var(--accent);
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
}

.place__map svg { width: 13px; height: 13px; }
</style>
