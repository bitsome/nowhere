<script setup>
import BaseIcon from '../../../../../components/Icon/BaseIcon.vue';
import NotificationItem from './NotificationItem.vue';

defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['close', 'read-all']);
</script>

<template>
    <div
        v-if="isOpen"
        class="notification-dropdown"
        role="dialog"
        aria-label="알림 목록"
        @click.stop
    >
        <div class="notification-dropdown__header">
            <div>
                <p class="notification-dropdown__title">알림</p>
                <p class="notification-dropdown__subtitle">최근 업데이트를 확인하세요.</p>
            </div>

            <button
                type="button"
                class="notification-dropdown__action notification-dropdown__action--icon"
                title="모든 알림 읽음 처리"
                aria-label="모든 알림 읽음 처리"
                :disabled="!items.length"
                @click="$emit('read-all')"
            >
                <BaseIcon name="check" :size="14" />
            </button>
        </div>

        <div v-if="items.length" class="notification-dropdown__list">
            <NotificationItem
                v-for="(item, index) in items"
                :key="item.id || `${item.message || item.title || 'notification'}-${index}`"
                :item="item"
            />
        </div>

        <div v-else class="notification-dropdown__empty">
            <p class="notification-dropdown__empty-title">알림이 없습니다.</p>
            <p class="notification-dropdown__empty-description">새로운 알림이 들어오면 여기에 표시됩니다.</p>
        </div>
    </div>
</template>
