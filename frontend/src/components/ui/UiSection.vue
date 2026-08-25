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
        <div class="ui-section__head">
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
    font-size: 13px;
    font-weight: 800;
}
.ui-section__action {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    color: var(--text-muted);
    cursor: pointer;
}
</style>
