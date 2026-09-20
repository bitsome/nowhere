<script setup>
import { computed } from 'vue';

/**
 * 설문 선택지 블록 — 항목별 득표 막대와 내 선택을 보여주고, 항목을 누르면 투표한다.
 * 피드 카드와 상세 화면에서 공용으로 쓴다.
 */
defineOptions({ name: 'SurveyBlock' });

const props = defineProps({
    /** { options: [{ id, text, votes }], total, my_option, closes_at, closed } */
    survey: { type: Object, required: true },
});

const emit = defineEmits(['vote']);

const closed = computed(() => Boolean(props.survey.closed));
const myOption = computed(() => props.survey.my_option);

// 득표율 — 표가 없으면 0%
const percent = (votes) => (props.survey.total > 0 ? Math.round((votes / props.survey.total) * 100) : 0);

const closesText = computed(() => {
    if (!props.survey.closes_at) {
        return '';
    }

    const date = new Date(props.survey.closes_at);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const hh = String(date.getHours()).padStart(2, '0');
    const mm = String(date.getMinutes()).padStart(2, '0');

    return `${date.getMonth() + 1}월 ${date.getDate()}일 ${hh}:${mm}`;
});

const pick = (option) => {
    if (closed.value) {
        return;
    }

    emit('vote', option.id);
};
</script>

<template>
    <div class="survey" :class="{ 'survey--closed': closed }" @click.stop>
        <div class="survey__head">
            <span class="survey__meta">
                {{ survey.total > 0 ? `${survey.total}명 참여` : '아직 투표가 없습니다' }}
            </span>
            <span v-if="closed" class="survey__state survey__state--closed">마감</span>
            <span v-else-if="closesText" class="survey__state">{{ closesText }} 마감</span>
        </div>

        <ul class="survey__options">
            <li v-for="option in survey.options" :key="option.id">
                <button
                    type="button"
                    class="survey__option"
                    :class="{ 'survey__option--picked': myOption === option.id }"
                    :disabled="closed"
                    @click.stop="pick(option)"
                >
                    <span class="survey__fill" :style="{ width: `${percent(option.votes)}%` }" />
                    <span class="survey__label">
                        <span class="survey__text">{{ option.text }}</span>
                        <em v-if="myOption === option.id" class="survey__picked">내 선택</em>
                    </span>
                    <span class="survey__count">{{ percent(option.votes) }}%</span>
                </button>
            </li>
        </ul>

        <p v-if="!closed" class="survey__hint">항목을 누르면 투표됩니다 (1인 1표, 다시 누르면 변경)</p>
    </div>
</template>

<style scoped>
.survey {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 10px 0;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .survey { background: rgba(255, 255, 255, 0.03); }

.survey__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 11px;
    color: var(--text-muted);
}

.survey__state {
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 10px;
}

.survey__state--closed {
    background: color-mix(in srgb, var(--text-muted) 14%, transparent);
    color: var(--text-muted);
}

.survey__options {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.survey__option {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 11px;
    text-align: left;
    cursor: pointer;
    overflow: hidden;
    transition: border-color 0.15s ease;
}

.survey__option:disabled { cursor: default; }

/* 득표 막대 — 선택지 배경으로 깔린다 (라벨은 위 레이어) */
.survey__fill {
    position: absolute;
    inset: 0 auto 0 0;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    transition: width 0.2s ease;
}

.survey__option--picked {
    border-color: var(--brand);
}

.survey__option--picked .survey__fill {
    background: color-mix(in srgb, var(--brand) 18%, transparent);
}

.survey__label {
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    flex: 1;
}

.survey__text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.survey__picked {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand);
    color: #07120e;
    font-size: 10px;
    font-style: normal;
}

.survey__count {
    position: relative;
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.survey__hint {
    margin: 0;
    font-size: 10px;
    color: var(--text-muted);
}
</style>
