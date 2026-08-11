<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useNotification } from 'naive-ui';
import { useChatsStore } from '../stores/chats';
import { showBrowserNotification } from '../utils/browserNotify';

const router = useRouter();
const notification = useNotification();
const store = useChatsStore();

let timer = null;
let baselineSet = false;

const checkNew = async () => {
    const before = store.unreadTotal;

    await store.loadConversations().catch(() => {});

    // 첫 폴링은 기준점만 잡고, 이후 안 읽음 메시지가 증가하면 알린다.
    if (!baselineSet) {
        baselineSet = true;

        return;
    }

    if (store.unreadTotal > before) {
        const conversation = store.conversations.find((c) => (c.unread_count ?? 0) > 0);

        if (conversation) {
            showBrowserNotification(`${conversation.counterpart?.name ?? '상대방'}님의 새 메시지`, {
                body: conversation.last_message?.body ?? '새 메시지가 도착했습니다.',
                tag: `chat-${conversation.id}`,
            });

            notification.info({
                title: `${conversation.counterpart?.name ?? '상대방'}님의 새 메시지`,
                content: conversation.last_message?.body ?? '새 메시지가 도착했습니다.',
                duration: 5000,
                onClick: () => openConversation(conversation.id),
            });
        }
    }
};

const openConversation = async (id) => {
    await router.push({ name: 'chat' });
    await store.open(id);
};

const onVisibility = () => {
    if (document.visibilityState === 'visible') {
        checkNew();
    }
};

const onSseRefresh = () => {
    checkNew();
};

onMounted(() => {
    checkNew();
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
