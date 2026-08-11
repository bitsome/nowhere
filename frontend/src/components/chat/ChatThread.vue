<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useChatsStore } from '../../stores/chats';
import MessageBubble from './MessageBubble.vue';

const auth = useAuthStore();
const store = useChatsStore();
const router = useRouter();

const draft = ref('');
const threadEl = ref(null);
const sending = ref(false);

const activeConversation = computed(() => store.activeConversation);

const scrollToBottom = async () => {
    await nextTick();
    if (!threadEl.value) return;
    threadEl.value.scrollTop = threadEl.value.scrollHeight;
    // 일부 렌더링이 늦게 반영되는 경우(말풍선 높이) 한 번 더 보정
    requestAnimationFrame(() => {
        if (threadEl.value) {
            threadEl.value.scrollTop = threadEl.value.scrollHeight;
        }
    });
};

// 바닥 근처 여부 — 여기 있을 때만 새 메시지에 자동으로 바닥으로 내려간다
const isNearBottom = ref(true);
const newMessages = ref(0);

const onThreadScroll = () => {
    const el = threadEl.value;
    if (!el) return;
    isNearBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 80;

    // 바닥으로 내려오면 새 메시지 배지를 지운다
    if (isNearBottom.value && newMessages.value > 0) {
        newMessages.value = 0;
    }
};

const jumpToBottom = async () => {
    newMessages.value = 0;
    isNearBottom.value = true;
    await scrollToBottom();
};

const send = async () => {
    const body = draft.value.trim();
    if (!body || sending.value) return;
    sending.value = true;
    draft.value = '';
    try {
        await store.send(body);
        isNearBottom.value = true;
        newMessages.value = 0;
    } finally {
        sending.value = false;
    }
    await scrollToBottom();
};

// 메시지 수 증가 감지 — 바닥에 있으면 자동 스크롤, 아니면 새 메시지 배지로 안내
watch(
    () => store.messages.length,
    (length, prev) => {
        if (length <= prev) return;
        if (isNearBottom.value) {
            scrollToBottom();
        } else {
            newMessages.value += length - prev;
        }
    },
);

// 대화방 진입 직후 바닥으로 + window 스크롤 잠금 (내부 스크롤만 동작)
watch(
    () => store.activeId,
    async (id) => {
        if (id) {
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        } else {
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
        }
        newMessages.value = 0;
        isNearBottom.value = true;
        await scrollToBottom();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
});

// 브라우저/기기 뒤로가기로 대화방 → 목록 전환을 지원하기 위한 히스토리 항목
onMounted(() => {
    history.pushState({ chatThread: true }, '');
});
</script>

<template>
    <div class="chat-thread">
        <div ref="threadEl" class="chat-thread__messages" @scroll="onThreadScroll">
            <!-- 연결된 오더 정보 카드 -->
            <button
                v-if="activeConversation?.order"
                type="button"
                class="chat-order-card"
                @click="router.push({ name: 'order-detail', params: { id: activeConversation.order.id } })"
            >
                <span class="chat-order-card__tag">오더</span>
                <span class="chat-order-card__route">{{ activeConversation.order.route }}</span>
                <span class="chat-order-card__meta">
                    {{ activeConversation.order.service_date }} {{ activeConversation.order.service_time }} · {{ activeConversation.order.statusLabel }}
                </span>
                <span class="chat-order-card__amount">{{ Number(activeConversation.order.amount).toLocaleString() }}원</span>
            </button>

            <MessageBubble
                v-for="msg in store.messages"
                :key="msg.id"
                :msg="msg"
                :is-mine="msg.user_id === auth.user?.id"
                :counterpart-name="activeConversation?.counterpart?.name"
            />
        </div>

        <!-- 새 메시지 도착 배지 — 과거 메시지를 보고 있으면 안내 -->
        <button
            v-if="newMessages > 0"
            type="button"
            class="chat-thread__jump"
            @click="jumpToBottom"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9" />
            </svg>
            새 메시지 {{ newMessages }}
        </button>

        <form class="chat-thread__input" @submit.prevent="send">
            <input v-model="draft" type="text" placeholder="메시지를 입력하세요..." :disabled="sending" />
            <n-button type="primary" attr-type="submit" :loading="sending" :disabled="!draft.trim()">보내기</n-button>
        </form>
    </div>
</template>

<style scoped>
/* 대화방 — 전체 화면 고정 오버레이.
   window 스크롤과 완전 분리되어 어떤 기기에서도 레이아웃이 어긋나지 않는다.
   padding-top은 상단 헤더(62px) 높이만큼 확보. */
.chat-thread{position:fixed;inset:0;z-index:9;display:flex;flex-direction:column;padding-top:54px;background:var(--bg)}
.chat-thread__messages{flex:1;overflow-y:auto;padding:12px 14px 16px;display:flex;flex-direction:column;gap:6px;-webkit-overflow-scrolling:touch}

/* 연결된 오더 카드 — 대화방 상단 */
.chat-order-card{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;width:100%;text-align:left;margin-bottom:8px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--surface);cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,.05)}
.chat-order-card:hover{border-color:#36adff}
.chat-order-card__tag{flex-shrink:0;padding:2px 8px;border-radius:999px;background:rgba(54,173,255,.14);color:#36adff;font-size:11px;font-weight:700}
.chat-order-card__route{flex:1;min-width:120px;font-size:13px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-order-card__meta{width:100%;font-size:12px;color:var(--text-muted)}
.chat-order-card__amount{flex-shrink:0;font-size:13px;font-weight:700;color:var(--text)}

/* 입력창 — flex 하단에 자연 배치 (fixed 불필요) */
.chat-thread__input{display:flex;gap:8px;padding:10px 14px calc(10px + env(safe-area-inset-bottom));border-top:1px solid var(--border);background:var(--surface)}
.chat-thread__input input{flex:1;border:1px solid var(--border);border-radius:20px;padding:10px 16px;font-size:14px;background:var(--bg);color:var(--text);outline:none}
.chat-thread__input input:focus{border-color:#36adff}

/* 새 메시지 도착 배지 */
.chat-thread__jump{position:absolute;bottom:calc(64px + env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:10;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:0;border-radius:999px;background:#36adff;color:#fff;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(54,173,255,.4)}
.chat-thread__jump svg{width:16px;height:16px}
</style>
