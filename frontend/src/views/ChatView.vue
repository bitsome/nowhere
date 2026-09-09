<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useChatsStore } from '../stores/chats';
import ChatList from '../components/chat/ChatList.vue';
import ChatThread from '../components/chat/ChatThread.vue';
import EmptyState from '../components/common/EmptyState.vue';

const store = useChatsStore();

let timer = null;

const openConversation = (id) => {
    store.open(id);
};

// 목록의 '모두 읽음' — 모든 대화방 안 읽은 메시지를 한 번에 읽음 처리
const markAllRead = async () => {
    try {
        await store.markAllRead();
    } catch {
        // 실패해도 다음 3초 폴링이 목록을 복구한다 — 조용히 넘긴다
    }
};

onMounted(async () => {
    await store.loadConversations().catch(() => {});

    // 주기 폴링 — 활성 대화를 먼저 동기화(읽음 처리 반영)한 뒤 목록을 갱신한다.
    // 순서가 반대면 대화를 읽고 탭을 빠져나갈 때 하단 배지에 옛 안 읽음 수가 남는다.
    timer = setInterval(poll, 3000);

    // SSE 실시간 — 새 메시지 신호 시 즉시 갱신
    window.addEventListener('app:sse-refresh', onSseRefresh);
});

const poll = async () => {
    // 탭이 숨겨진 동안에는 요청을 보내지 않는다 — 복귀 시 SSE/다음 폴링이 갱신한다 (부하 절감)
    if (document.visibilityState === 'hidden') {
        return;
    }

    if (store.activeId) {
        await store.reloadMessages().catch(() => {});
    }

    await store.loadConversations().catch(() => {});
};

const onSseRefresh = async () => {
    if (store.activeId) {
        await store.reloadMessages().catch(() => {});
    }

    await store.loadConversations().catch(() => {});
};

onBeforeUnmount(() => {
    clearInterval(timer);
    window.removeEventListener('app:sse-refresh', onSseRefresh);
});
</script>

<template>
    <div :class="{ 'page-shell': !store.activeId }">
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
            @mark-all-read="markAllRead"
        />
        <ChatThread v-else />
    </div>
</template>

<style scoped>
/* 대화 목록 로딩 스켈레톤 — 실제 대화 카드와 동일한 폭/간격 */
.chat-skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
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
