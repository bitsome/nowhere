<script setup>
import DropdownActions from './DropdownActions.vue';

/**
 * 테이블 행에서 반복 사용하는 점 3개 액션 드롭다운.
 *
 * 공용 DropdownActions 모듈에 테이블 전용 기본값을 적용해서 사용한다.
 * items 구조:
 * - { label, href }     : 링크 이동
 * - { label, action }   : 클릭 콜백
 * - { label, icon, danger, disabled } : 아이콘/위험/비활성
 * - { divider: true }   : 구분선
 */
const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    items: {
        type: Array,
        default: () => [],
    },
    teleport: {
        type: Boolean,
        default: true,
    },
    triggerLabel: {
        type: String,
        default: '행 액션',
    },
    width: {
        type: String,
        default: '160px',
    },
});
</script>

<template>
    <DropdownActions
        :align="align"
        :items="items"
        :teleport="teleport"
        :trigger-class="'shared-table-actions__trigger'"
        trigger-icon="ellipsis-vertical"
        :trigger-label="triggerLabel"
        :width="width"
        :icon-size="16"
    />
</template>
