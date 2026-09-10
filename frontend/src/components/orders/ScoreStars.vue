<script setup>
import { computed } from 'vue';
import BaseIcon from '../common/BaseIcon.vue';

/**
 * ScoreStars — 0~5점 값을 별 5개로 보여준다.
 * 소수는 절반/부분 채움으로 처리한다. 예: 3.5 → 별 3개 + 반별 1개 + 회색별 1개
 */
const props = defineProps({
    // 0~5 사이 값 (없으면 별 숨김)
    value: { type: Number, default: null },
    // 별 아이콘 크기 (px)
    size: { type: [Number, String], default: 14 },
});

// 0~5로 고정
const clamped = computed(() => {
    if (props.value === null || !Number.isFinite(props.value)) {
        return null;
    }

    return Math.min(5, Math.max(0, props.value));
});

// 별 다섯 개 각각의 채움 비율(%) — i+1번째 슬롯 기준
const slots = computed(() => {
    const value = clamped.value;

    if (value === null) {
        return [];
    }

    return Array.from({ length: 5 }, (_, index) => {
        const border = index + 1;

        if (value >= border) {
            return 100;
        }
        if (value > index) {
            return Math.round((value - index) * 100);
        }

        return 0;
    });
});

const iconSize = computed(() => Number(props.size) || 14);
</script>

<template>
    <span
        v-if="clamped !== null"
        class="score-stars"
        :style="{ '--score-star-size': `${iconSize}px` }"
    >
        <span v-for="(fill, i) in slots" :key="i" class="score-stars__slot">
            <BaseIcon class="score-stars__base" name="star" :size="iconSize" />
            <span
                v-if="fill > 0"
                class="score-stars__fill"
                :style="{ width: `${fill}%` }"
            >
                <BaseIcon class="score-stars__gold" name="star" :size="iconSize" />
            </span>
        </span>
    </span>
</template>

<style scoped>
.score-stars {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    line-height: 1;
}

/* 별 한 칸 — 회색 기본 별 위에 금색 별이 왼쪽부터 채워진다 (반별·부분별 지원) */
.score-stars__slot {
    position: relative;
    display: inline-flex;
    width: var(--score-star-size);
    height: var(--score-star-size);
    flex-shrink: 0;
}

.score-stars__base {
    color: #d3d3da;
}

.score-stars__fill {
    position: absolute;
    top: 0;
    left: 0;
    height: var(--score-star-size);
    overflow: hidden;
    pointer-events: none;
}

.score-stars__gold {
    color: #f0a800;
}

html.dark .score-stars__base {
    color: #4f4f57;
}

html.dark .score-stars__gold {
    color: #e8b93f;
}

</style>
