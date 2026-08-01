<script setup>
import BaseIcon from '../Icon/BaseIcon.vue';

defineProps({
    description: {
        type: String,
        default: '조건에 맞는 데이터가 없습니다.',
    },
    title: {
        type: String,
        default: '데이터가 없습니다.',
    },
});
</script>

<template>
    <div class="datatable-empty">
        <div class="datatable-empty__icon">
            <BaseIcon name="dashboard" :decorative="false" title="빈 데이터" :size="18" />
        </div>
        <p class="datatable-empty__title">{{ title }}</p>
        <p class="datatable-empty__description">{{ description }}</p>
    </div>
</template>
