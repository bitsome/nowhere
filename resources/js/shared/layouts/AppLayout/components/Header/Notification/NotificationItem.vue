<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        default: () => ({}),
    },
});

const itemMessage = computed(() => {
    return props.item.message || props.item.title || '알림 내용이 없습니다.';
});

const itemTime = computed(() => {
    return props.item.time || '';
});

const itemTypeClass = computed(() => {
    const type = String(props.item.type || 'info').toLowerCase();

    if (type === 'success') {
        return 'is-success';
    }

    if (type === 'warning') {
        return 'is-warning';
    }

    return 'is-info';
});
</script>

<template>
    <article class="notification-item">
        <div class="notification-item__main">
            <span class="notification-item__dot" :class="itemTypeClass" aria-hidden="true" />

            <div class="notification-item__content">
                <p class="notification-item__message">{{ itemMessage }}</p>
            </div>
        </div>

        <time v-if="itemTime" class="notification-item__time">{{ itemTime }}</time>
    </article>
</template>
