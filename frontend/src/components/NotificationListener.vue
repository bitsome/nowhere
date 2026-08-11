<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useNotification } from 'naive-ui';
import { useNotificationsStore } from '../stores/notifications';
import { showBrowserNotification } from '../utils/browserNotify';

const router = useRouter();
const notification = useNotification();
const store = useNotificationsStore();

let timer = null;
let firstLoadDone = false;

const checkNew = async () => {
    const before = store.unreadCount;

    await store.load().catch(() => {});

    // 첫 로드는 기준점만 잡고, 이후 안 읽음 수가 증가하면 토스트로 알린다.
    if (!firstLoadDone) {
        firstLoadDone = true;

        return;
    }

    if (store.unreadCount > before) {
        const item = store.items[0];

        if (item) {
            showBrowserNotification(item.title, {
                body: item.message,
                tag: `notification-${item.id}`,
            });

            notification.info({
                title: item.title,
                content: item.message,
                duration: 5000,
                onClick: () => {
                    // 오더 관련 알림은 해당 오더 상세로, 아니면 알림 목록으로 이동
                    if (item.order_id) {
                        router.push({ name: 'order-detail', params: { id: item.order_id } });
                    } else {
                        router.push({ name: 'notifications' });
                    }
                },
            });
        }
    }
};

const onVisibility = () => {
    if (document.visibilityState === 'visible') {
        checkNew();
    }
};

const onSseRefresh = () => {
    checkNew();
};

onMounted(async () => {
    await checkNew();
    timer = setInterval(checkNew, 30000);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('app:sse-refresh', onSseRefresh);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('app:sse-refresh', onSseRefresh);
});
</script>

<template>
    <div />
</template>
