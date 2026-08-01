<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import ToastItem from './ToastItem.vue';
import './toast.css';

const props = defineProps({
    autoCloseDelay: {
        type: Number,
        default: 3200,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const hiddenKeys = ref([]);
const timers = new Map();

const normalizedItems = computed(() => {
    return props.items.map((item, index) => ({
        closeable: item?.closeable ?? true,
        id: item?.id ?? `${item?.type || 'info'}-${item?.title || ''}-${item?.message || ''}-${index}`,
        key: item?.id ?? `${item?.type || 'info'}-${item?.title || ''}-${item?.message || ''}-${index}`,
        message: item?.message || '',
        title: item?.title || '',
        type: item?.type || 'info',
    }));
});

const visibleItems = computed(() => {
    return normalizedItems.value.filter((item) => !hiddenKeys.value.includes(item.key) && item.message !== '');
});

function closeItem(item) {
    if (hiddenKeys.value.includes(item.key)) {
        return;
    }

    hiddenKeys.value = [...hiddenKeys.value, item.key];
    window.clearTimeout(timers.get(item.key));
    timers.delete(item.key);
    emit('close', item);
}

function syncTimers(items) {
    const activeKeys = items.map((item) => item.key);

    timers.forEach((timer, key) => {
        if (!activeKeys.includes(key)) {
            window.clearTimeout(timer);
            timers.delete(key);
        }
    });

    items.forEach((item) => {
        if (timers.has(item.key) || item.closeable === false || props.autoCloseDelay <= 0) {
            return;
        }

        const timer = window.setTimeout(() => {
            closeItem(item);
        }, props.autoCloseDelay);

        timers.set(item.key, timer);
    });
}

watch(
    normalizedItems,
    (items) => {
        hiddenKeys.value = hiddenKeys.value.filter((key) => {
            return items.some((item) => item.key === key);
        });

        syncTimers(items);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    timers.forEach((timer) => window.clearTimeout(timer));
    timers.clear();
});
</script>

<template>
    <aside v-if="visibleItems.length" class="toast-stack" aria-live="polite" aria-label="Toast 알림">
        <ToastItem
            v-for="item in visibleItems"
            :key="item.key"
            :item="item"
            @close="closeItem"
        />
    </aside>
</template>
