<script setup>
/**
 * UiSection — 섹션 헤더 + 본문.
 * 제목과 우측 액션('전체 보기 ›' 등)을 통일한다.
 */
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="ui-section">
        <!-- 제목/액션이 없는 섹션은 헤더(여백)를 만들지 않는다 — 칩 등 바로 아래 콘텐츠가 붙도록 -->
        <div v-if="title || $slots.action" class="ui-section__head">
            <span class="ui-section__title">{{ title }}</span>
            <span v-if="$slots.action" class="ui-section__action"><slot name="action" /></span>
        </div>
        <slot />
    </div>
</template>

<style scoped>
.ui-section {
    margin-top: 18px;
}
.ui-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.ui-section__title {
    font-size: 11px;
    font-weight: 800;
}
.ui-section__action {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    color: var(--text-muted);
    cursor: pointer;
}
</style>
