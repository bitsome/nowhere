<script setup>
import BaseIcon from '../../../../../components/Icon/BaseIcon.vue';
import NotificationBadge from './NotificationBadge.vue';

defineProps({
    count: {
        type: Number,
        default: 0,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="notification-bell"
        :class="{ 'is-open': isOpen }"
        aria-label="알림"
        title="알림"
        :aria-expanded="isOpen ? 'true' : 'false'"
        @click="$emit('click')"
    >
        <BaseIcon name="bell" :size="20" class="notification-bell__icon" />

        <NotificationBadge :count="count" />
    </button>
</template>
