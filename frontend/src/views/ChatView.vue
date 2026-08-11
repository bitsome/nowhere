<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useChatsStore } from '../stores/chats';
import ChatList from '../components/chat/ChatList.vue';
import ChatThread from '../components/chat/ChatThread.vue';

const store = useChatsStore();

let timer = null;

const openConversation = (id) => {
    store.open(id);
};

onMounted(async () => {
    await store.loadConversations().catch(() => {});

    // 주기 폴링 — 목록과 활성 대화 모두 갱신
    timer = setInterval(async () => {
        await store.loadConversations().catch(() => {});
        if (store.activeId) {
            await store.reloadMessages().catch(() => {});
        }
    }, 10000);

    // SSE 실시간 — 새 메시지 신호 시 즉시 갱신
    window.addEventListener('app:sse-refresh', onSseRefresh);
});

const onSseRefresh = async () => {
    await store.loadConversations().catch(() => {});
    if (store.activeId) {
        await store.reloadMessages().catch(() => {});
    }
};

onBeforeUnmount(() => {
    clearInterval(timer);
    window.removeEventListener('app:sse-refresh', onSseRefresh);
});
</script>

<template>
    <div>
        <n-empty
            v-if="store.loaded && store.conversations.length === 0"
            description="대화가 없습니다."
            :image-size="80"
        />

        <ChatList
            v-else-if="!store.activeId"
            :conversations="store.conversations"
            @open="openConversation"
        />
        <ChatThread v-else />
    </div>
</template>
