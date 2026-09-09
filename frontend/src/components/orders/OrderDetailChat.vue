<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../../stores/auth';
import { getApiErrorMessage } from '../../api/client';
import { apiChatMessages, apiChatMessagesSync, apiChats, apiCreateChat, apiDeleteChatMessage, apiSendChatMessage } from '../../api/chats';
import { useChatRows } from '../../composables/useChatRows';
import MessageBubble from '../chat/MessageBubble.vue';
import ChatRequestEvent from '../chat/ChatRequestEvent.vue';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    orderId: {
        type: Number,
        required: true,
    },
    userId: {
        type: Number,
        required: true,
    },
    // 상대(운행 등록자) 이름 — 헤더에 표시
    counterpartName: {
        type: String,
        default: '',
    },
    // 바텀 시트 임베드 시 자체 헤더를 숨긴다 (시트 헤더가 제목을 보여주므로)
    hideHead: {
        type: Boolean,
        default: false,
    },
    // 바텀 시트 임베드 시 테두리·라운드를 없애고 시트를 꽉 채운다
    bare: {
        type: Boolean,
        default: false,
    },
});

const auth = useAuthStore();
const message = useMessage();
const myId = auth.user?.id;

const conversationId = ref(null);
const messages = ref([]);
// 증분 동기화 기준점 — 마지막으로 받은 메시지 id (0이면 아직 전체를 안 받은 상태)
const lastMessageId = ref(0);
const draft = ref('');
const sending = ref(false);
const loading = ref(true);
const listEl = ref(null);

let timer = null;

const scrollBottom = () => {
    requestAnimationFrame(() => {
        if (listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight;
        }
    });
};

// 증분 동기화 — 기준점 이후의 새 메시지와 읽음 변화만 받아 가볍게 유지한다.
// (기준점이 없으면 처음이므로 전체를 받아 기준점을 만든다)
const loadMessages = async () => {
    if (!conversationId.value) return;

    if (lastMessageId.value > 0) {
        const { data } = await apiChatMessagesSync(conversationId.value, lastMessageId.value);
        const { messages: fresh, read_ids: readIds } = data.data;

        // 상대가 읽은 내 메시지는 '읽음' 표시를 즉시 갱신한다
        for (const id of readIds ?? []) {
            const existing = messages.value.find((m) => m.id === id);

            if (existing && !existing.read) {
                existing.read = true;
            }
        }

        if (fresh.length > 0) {
            messages.value = [...messages.value, ...fresh];
            lastMessageId.value = fresh.reduce((max, m) => Math.max(max, m.id), lastMessageId.value);
            scrollBottom();
        }

        return;
    }

    const { data } = await apiChatMessages(conversationId.value);
    messages.value = data.data;
    lastMessageId.value = data.data.reduce((max, m) => Math.max(max, m.id), 0);
    scrollBottom();
};

// 상대(운행 등록자)와 (운행 연동) 대화를 찾거나 새로 만든다.
// 같은 운행 대화가 이미 있으면 재사용해 중복 대화가 생기지 않게 한다.
const ensureConversation = async () => {
    const { data } = await apiChats();
    const existing = (data.data ?? []).find(
        (c) => c.order_id === props.orderId && c.counterpart?.id === props.userId,
    );

    if (existing) {
        conversationId.value = existing.id;
    } else {
        const created = await apiCreateChat({ user_id: props.userId, order_id: props.orderId });
        conversationId.value = created.data.data.id;
    }

    await loadMessages();
};

// 메시지 렌더 행 — 공용 컴포저블(날짜/유형 구분선·그룹 규칙)로 ChatThread와 동일한 말풍선을 쓴다
const messageRows = useChatRows(messages);

const send = async () => {
    const body = draft.value.trim();

    if (!body || sending.value || !conversationId.value) return;

    sending.value = true;
    draft.value = '';

    try {
        await apiSendChatMessage(conversationId.value, body);
        await loadMessages();
    } catch (e) {
        // 전송 실패 시 입력값 복원 — 메시지 유실 방지
        draft.value = body;
        message.error(getApiErrorMessage(e, '메시지 전송에 실패했습니다.'));
    } finally {
        sending.value = false;
    }
};

// 내가 보낸 메시지 삭제 — 길게 누르기(MessageBubble)로 호출된다
const deleteMessage = async (messageId) => {
    if (!conversationId.value) return;

    try {
        await apiDeleteChatMessage(conversationId.value, messageId);
        messages.value = messages.value.filter((m) => m.id !== messageId);
    } catch (e) {
        message.error(getApiErrorMessage(e, '메시지 삭제에 실패했습니다.'));
    }
};

const onSse = () => {
    if (conversationId.value) {
        loadMessages().catch(() => {});
    }
};

onMounted(async () => {
    loading.value = true;

    try {
        await ensureConversation();
    } catch (e) {
        message.error(getApiErrorMessage(e, '대화를 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }

    // 주기 폴링 + SSE 실시간 — 새 메시지·읽음 표시를 대화창에 빠르게 반영
    timer = setInterval(() => {
        // 탭이 숨겨진 동안에는 요청을 보내지 않는다 (복귀 시 SSE/다음 폴링이 갱신)
        if (document.visibilityState === 'hidden') {
            return;
        }

        if (conversationId.value) {
            loadMessages().catch(() => {});
        }
    }, 3000);
    window.addEventListener('app:sse-refresh', onSse);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    window.removeEventListener('app:sse-refresh', onSse);
});
</script>

<template>
    <div class="detail-chat" :class="{ 'detail-chat--bare': bare }">
        <div v-if="!hideHead" class="detail-chat__head">
            <span class="detail-chat__title">등록자와 대화</span>
            <span class="detail-chat__name">{{ counterpartName || '등록자' }}</span>
        </div>

        <div ref="listEl" class="detail-chat__list">
            <p v-if="loading" class="detail-chat__empty">대화를 불러오는 중...</p>
            <p v-else-if="!messages.length" class="detail-chat__empty">
                아직 대화가 없습니다. 등록자에게 궁금한 점을 물어보세요.
            </p>
            <template v-for="row in messageRows" :key="row.msg.id">
                <div v-if="row.showSep" class="chat-day-sep">{{ row.dayLabel }}</div>
                <div v-if="row.showTypeSep" class="chat-type-sep">{{ row.typeLabel }}</div>
                <ChatRequestEvent
                    v-if="row.isEvent"
                    :msg="row.msg"
                    :is-mine="row.msg.user_id === myId"
                />
                <MessageBubble
                    v-else
                    :msg="row.msg"
                    :is-mine="row.msg.user_id === myId"
                    :is-first="row.isFirst"
                    :is-last="row.isLast"
                    :counterpart-name="counterpartName"
                    @delete="deleteMessage"
                />
            </template>
        </div>

        <form class="detail-chat__composer" @submit.prevent="send">
            <input
                v-model="draft"
                type="text"
                placeholder="메시지를 입력하세요..."
                :disabled="sending"
            />
            <n-button
                type="primary"
                attr-type="submit"
                circle
                class="detail-chat__send"
                :loading="sending"
                :disabled="!draft.trim()"
                title="보내기"
            >
                <BaseIcon name="send" :size="17" />
            </n-button>
        </form>
    </div>
</template>

<style scoped>
/* 운행 상세 — 등록자와 소통하는 인라인 대화창 (말풍선·요청 카드는 공용 컴포넌트 사용) */
.detail-chat {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
    overflow: hidden;
}

/* 바텀 시트 모드 — 테두리 제거, 시트 높이를 꽉 채운다 */
.detail-chat--bare {
    border: 0;
    border-radius: 0;
    height: 100%;
}

.detail-chat--bare .detail-chat__list {
    flex: 1;
    min-height: 0;
    max-height: none;
}

.detail-chat__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
}

.detail-chat__title {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.detail-chat__name {
    flex-shrink: 0;
    max-width: 55%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.detail-chat__list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 260px;
    min-height: 120px;
    padding: 12px 14px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.detail-chat__empty {
    margin: auto;
    color: var(--text-muted);
    font-size: 11px;
    text-align: center;
}

/* 입력 영역 — 메인 채팅방과 동일한 스타일의 입력줄 + 원형 전송 버튼 */
.detail-chat__composer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-top: 1px solid var(--border);
    background: var(--surface);
}

.detail-chat__composer input {
    flex: 1;
    min-width: 0;
    padding: 10px 16px;
    border: 1px solid var(--border);
    border-radius: 20px;
    background: var(--bg);
    color: var(--text);
    font-size: 11px;
    outline: none;
}

.detail-chat__composer input:focus {
    border-color: var(--brand);
}

/* 전송 버튼 — 아이콘 우선 (38px 원형) */
.detail-chat__send {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
}

.detail-chat__send svg {
    width: 17px;
    height: 17px;
}
</style>
