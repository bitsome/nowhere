<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

import {
    markAllNotificationsRead,
    onNotificationChange,
    readNotifications,
} from '../../../../../notifications/notificationStore.js';
import Notification from './Notification.vue';

const notifications = ref([]);

let detachListener = null;

const syncNotifications = (items = null) => {
    notifications.value = Array.isArray(items) ? items : readNotifications();
};

const handleReadAll = () => {
    syncNotifications(markAllNotificationsRead());
};

onMounted(() => {
    syncNotifications();
    detachListener = onNotificationChange((items) => {
        syncNotifications(items);
    });
});

onBeforeUnmount(() => {
    detachListener?.();
});
</script>

<template>
    <Notification
        :items="notifications"
        @read-all="handleReadAll"
    />
</template>
