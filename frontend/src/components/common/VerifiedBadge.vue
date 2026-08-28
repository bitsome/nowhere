<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <span v-if="show" class="verified-badge" title="검증 완료된 차량">검증</span>
</template>

<style scoped>
/* 검증 배지 — 배지 컨벤션(padding 1px 6px / 10px / 굵기 제거) 준수 */
.verified-badge {
    display: inline-block;
    padding: 1px 6px;
    font-size: 10px;
    font-weight: 400;
    line-height: 1.5;
    border-radius: 999px;
    color: #1f9d63;
    background: color-mix(in srgb, #1f9d63 12%, transparent);
    border: 1px solid color-mix(in srgb, #1f9d63 30%, transparent);
    vertical-align: middle;
}
</style>
