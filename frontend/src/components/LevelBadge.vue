<script setup>
import { computed, ref } from 'vue';
import { NButton, NModal } from 'naive-ui';
import { LEVEL_LIST, XP_RULES, iconSvg, tierForLevel } from '../data/levels';

const props = defineProps({
    level: { type: Number, required: true },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const showGuide = ref(false);

const tier = computed(() => tierForLevel(props.level));

const levels = computed(() =>
    LEVEL_LIST.map((row) => ({ ...row, tier: tierForLevel(row.level) })),
);
</script>

<template>
    <button
        type="button"
        class="level-badge"
        :class="`level-badge--${size}`"
        :style="{ background: tier.gradient }"
        :title="`Lv.${level} ${tier.label} · 탭해서 레벨 가이드 보기`"
        @click.stop="showGuide = true"
    >
        <svg
            class="level-badge__icon"
            viewBox="0 0 24 24"
            fill="currentColor"
            stroke="none"
            v-html="iconSvg(tier.icon)"
        />
        <span class="level-badge__num">{{ level }}</span>
    </button>

    <n-modal
        v-model:show="showGuide"
        preset="card"
        title="레벨 가이드"
        :style="{ maxWidth: '480px' }"
    >
        <div class="guide">
            <p class="guide__intro">
                활동으로 XP를 모아 레벨이 올라갑니다. 레벨은 내려가지 않아요. 내 현재 레벨은
                <strong>Lv.{{ level }}</strong
                >입니다.
            </p>

            <section class="guide__section">
                <h3>XP 획득 규칙</h3>
                <div class="rule-row" v-for="rule in XP_RULES" :key="rule.label">
                    <span>{{ rule.label }}</span>
                    <strong>+{{ rule.xp }} XP</strong>
                </div>
            </section>

            <section class="guide__section">
                <h3>레벨 단계 (1 ~ 10)</h3>
                <div class="level-row-list">
                    <div
                        v-for="row in levels"
                        :key="row.level"
                        class="level-row"
                        :class="{ 'level-row--current': row.level === level }"
                    >
                        <span
                            class="level-row__icon"
                            :style="{ background: row.tier.gradient }"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" v-html="iconSvg(row.tier.icon)" />
                        </span>
                        <div class="level-row__text">
                            <strong>Lv.{{ row.level }} {{ row.title }}</strong>
                            <span>요구 XP {{ row.minXp.toLocaleString() }}점</span>
                        </div>
                        <n-tag v-if="row.level === level" size="small" round type="primary">현재</n-tag>
                    </div>
                </div>
            </section>

            <n-button size="large" block @click="showGuide = false">닫기</n-button>
        </div>
    </n-modal>
</template>

<style scoped>
.level-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 0;
    border-radius: 999px;
    color: #ffffff;
    font-weight: 800;
    line-height: 1;
    vertical-align: middle;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
    flex-shrink: 0;
    cursor: pointer;
    transition: transform 0.12s ease, filter 0.12s ease;
}

.level-badge:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
}

.level-badge:active {
    transform: translateY(0);
}

.level-badge__icon {
    display: block;
    flex-shrink: 0;
}

.level-badge__num {
    font-family: inherit;
}

.level-badge--sm {
    height: 18px;
    padding: 0 6px;
    font-size: 10px;
}

.level-badge--sm .level-badge__icon {
    width: 10px;
    height: 10px;
}

.level-badge--md {
    height: 24px;
    padding: 0 8px;
    font-size: 12px;
}

.level-badge--md .level-badge__icon {
    width: 13px;
    height: 13px;
}

.level-badge--lg {
    height: 30px;
    padding: 0 10px;
    font-size: 14px;
}

.level-badge--lg .level-badge__icon {
    width: 16px;
    height: 16px;
}

/* 가이드 모달 */
.guide {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.guide__intro {
    margin: 0;
    color: var(--text-muted);
    font-size: 13px;
}

.guide__section h3 {
    margin: 0 0 10px;
    font-size: 14px;
}

.rule-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.rule-row:last-child {
    border-bottom: 0;
}

.rule-row strong {
    color: #2f54eb;
}

.level-row-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 320px;
    overflow-y: auto;
    padding-right: 4px;
}

.level-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.level-row--current {
    border-color: #36adff;
    background: rgba(54, 173, 255, 0.08);
}

.level-row__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 999px;
    color: #ffffff;
    flex-shrink: 0;
}

.level-row__icon svg {
    width: 16px;
    height: 16px;
}

.level-row__text {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.level-row__text strong {
    font-size: 13px;
}

.level-row__text span {
    color: var(--text-muted);
    font-size: 11px;
}
</style>
