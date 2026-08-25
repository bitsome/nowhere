<script setup>
/**
 * 세그먼트 버튼 그룹 — naive-ui n-radio-group(n-radio-button)을 감싼 재사용 컴포넌트.
 * 용도에 맞게 크기(small/medium/large)와 폭(stretch)을 정할 수 있다.
 *
 * <SegmentedGroup size="small" stretch>
 *   <n-radio-group v-model:value="mode">...</n-radio-group>
 * </SegmentedGroup>
 */
defineOptions({ name: 'SegmentedGroup' });

const props = defineProps({
    /** 버튼 크기 — small(컴팩트 칩) / medium(기본) / large(주요 액션) */
    size: { type: String, default: 'medium' },
    /** true면 그룹이 부모 폭을 꽉 채우고 버튼이 균등 배분된다 */
    stretch: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="segmented-group"
        :class="[`segmented-group--${size}`, { 'segmented-group--stretch': stretch }]"
    >
        <slot />
    </div>
</template>

<style scoped>
.segmented-group :deep(.n-radio-group) {
    display: flex;
}

.segmented-group :deep(.n-radio-button) {
    justify-content: center;
}

/* 균등 폭 — 버튼이 컨테이너 폭에 맞춰 펼쳐진다 */
.segmented-group--stretch :deep(.n-radio-group) {
    width: 100%;
}

.segmented-group--stretch :deep(.n-radio-button) {
    flex: 1;
}

/* 크기 프리셋 — 용도별 */
.segmented-group--small :deep(.n-radio-button) {
    font-size: 12px;
    min-width: 52px;
}

.segmented-group--medium :deep(.n-radio-button) {
    font-size: 13px;
    min-width: 64px;
}

.segmented-group--large :deep(.n-radio-button) {
    font-size: 15px;
    min-width: 96px;
    font-weight: 700;
    padding: 0 24px;
}
</style>
