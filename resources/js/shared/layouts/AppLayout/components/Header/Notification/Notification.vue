<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import NotificationBell from './NotificationBell.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import './notification.css';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['click', 'read-all']);

const isOpen = ref(false);
const rootElement = ref(null);
const autoOpenTimer = ref(null);

const unreadCount = computed(() => {
    const unreadItems = props.items.filter((item) => item?.read === false);

    return unreadItems.length;
});

const closeDropdown = () => {
    isOpen.value = false;
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    emit('click');
};

const handleReadAll = () => {
    emit('read-all');
};

const openDropdown = () => {
    isOpen.value = true;
};

const handleDocumentClick = (event) => {
    if (!rootElement.value?.contains(event.target)) {
        closeDropdown();
    }
};

const handleDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        closeDropdown();
    }
};

watch(unreadCount, (nextCount, previousCount = 0) => {
    if (nextCount <= previousCount || nextCount === 0) {
        return;
    }

    openDropdown();
    window.clearTimeout(autoOpenTimer.value);
    autoOpenTimer.value = window.setTimeout(() => {
        closeDropdown();
    }, 2600);
});

onMounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleDocumentKeydown);
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    window.clearTimeout(autoOpenTimer.value);
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
    <div ref="rootElement" class="notification">
        <NotificationBell
            :count="unreadCount"
            :is-open="isOpen"
            @click="toggleDropdown"
        />

        <NotificationDropdown
            :is-open="isOpen"
            :items="items"
            @close="closeDropdown"
            @read-all="handleReadAll"
        />
    </div>
</template>
