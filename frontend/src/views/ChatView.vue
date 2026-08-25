<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useChatsStore } from '../stores/chats';
import ChatList from '../components/chat/ChatList.vue';
import ChatThread from '../components/chat/ChatThread.vue';
import EmptyState from '../components/common/EmptyState.vue';

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
        <!-- 대화 목록 로딩 스켈레톤 — 첫 불러오기 동안 카드 골격 표시 -->
        <div v-if="!store.loaded" class="chat-skeleton" aria-hidden="true">
            <div v-for="n in 5" :key="`conv-skel-${n}`" class="sk-card chat-skeleton__item">
                <div class="sk-circle" />
                <div class="chat-skeleton__body">
                    <div class="chat-skeleton__row">
                        <div class="sk-line sk-line--md" style="width: 40%;" />
                        <div class="sk-line sk-line--sm" style="width: 40px;" />
                    </div>
                    <div class="sk-line sk-line--sm" style="width: 80%;" />
                </div>
            </div>
        </div>

        <EmptyState
            v-else-if="store.loaded && store.conversations.length === 0"
            icon="chat"
            title="대화가 없습니다"
            hint="마켓이나 매칭 운행에서 대화를 시작해 보세요"
        />

        <ChatList
            v-else-if="!store.activeId"
            :conversations="store.conversations"
            @open="openConversation"
        />
        <ChatThread v-else />
    </div>
</template>

<style scoped>
/* 대화 목록 로딩 스켈레톤 — 실제 대화 카드와 동일한 폭/간격 */
.chat-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-top: 12px;
}

.chat-skeleton__item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
}

.chat-skeleton__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chat-skeleton__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
</style>
