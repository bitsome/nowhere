<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationsStore } from '../stores/notifications';

const router = useRouter();
const store = useNotificationsStore();

onMounted(() => {
    store.load().catch(() => {});
});

const openNotification = async (notification) => {
    if (!notification.read) {
        await store.markRead([notification.id]);
    }

    if (notification.order_id) {
        router.push({ name: 'order-detail', params: { id: notification.order_id } });
    }
};

const openAll = () => {
    router.push({ name: 'notifications' });
};

// 벨을 열 때마다 서버에서 최신 알림을 다시 불러온다 (모바일 백그라운드 타이머 대응)
const handleShow = async (show) => {
    if (show) {
        await store.load().catch(() => {});
    }
};
</script>

<template>
    <n-popover trigger="click" placement="bottom-end" @update:show="handleShow">
        <template #trigger>
            <button type="button" class="bell-button" aria-label="알림">
                <n-badge :value="store.unreadCount" :show="store.unreadCount > 0" :max="99">
                    <svg
                        class="bell-button__icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
                    </svg>
                </n-badge>
            </button>
        </template>

        <div class="notify-panel">
            <div class="notify-panel__head">
                <strong>알림</strong>
                <div class="notify-panel__actions">
                    <n-button text size="small" :disabled="store.unreadCount === 0" @click="store.markAllRead()">
                        모두 읽음
                    </n-button>
                    <n-button text size="small" type="primary" @click="openAll">모두 보기</n-button>
                </div>
            </div>

            <div v-if="!store.loaded" class="notify-panel__loading">
                <n-spin size="small" />
            </div>
            <n-empty v-else-if="store.items.length === 0" description="알림이 없습니다." :image-size="60" />

            <ul v-else class="notify-list">
                <li
                    v-for="notification in store.items.slice(0, 8)"
                    :key="notification.id"
                    class="notify-item"
                    :class="{ 'notify-item--unread': !notification.read }"
                    @click="openNotification(notification)"
                >
                    <span class="notify-item__dot" :class="{ 'notify-item__dot--hidden': notification.read }" />
                    <div class="notify-item__body">
                        <div class="notify-item__title">{{ notification.title }}</div>
                        <div class="notify-item__message">{{ notification.message }}</div>
                        <div class="notify-item__time">{{ notification.created_at }}</div>
                    </div>
                </li>
            </ul>
        </div>
    </n-popover>
</template>
