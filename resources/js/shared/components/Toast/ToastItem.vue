<script setup>
import { computed } from 'vue';

import BaseIcon from '../Icon/BaseIcon.vue';
import './toast.css';

const props = defineProps({
    item: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close']);

const normalizedType = computed(() => {
    const type = String(props.item.type || 'info').trim().toLowerCase();

    if (['success', 'error', 'info'].includes(type)) {
        return type;
    }

    return 'info';
});

const title = computed(() => {
    if (props.item.title) {
        return props.item.title;
    }

    return {
        success: '완료',
        error: '오류 발생',
        info: '안내',
    }[normalizedType.value];
});

const iconName = computed(() => {
    return {
        success: 'check',
        error: 'close',
        info: 'bell',
    }[normalizedType.value];
});

const classes = computed(() => [
    'toast-card',
    `is-${normalizedType.value}`,
]);
</script>

<template>
    <article :class="classes">
        <div class="toast-card__row">
            <span class="toast-card__icon" aria-hidden="true">
                <BaseIcon :name="iconName" :size="16" />
            </span>

            <div class="toast-card__body">
                <p class="toast-card__title">{{ title }}</p>
                <p class="toast-card__message">{{ item.message }}</p>
            </div>

            <button
                v-if="item.closeable !== false"
                type="button"
                class="toast-card__close"
                title="Toast 닫기"
                aria-label="Toast 닫기"
                @click="$emit('close', item)"
            >
                <BaseIcon name="close" :size="14" />
            </button>
        </div>
    </article>
</template>
