<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useChatsStore } from '../../stores/chats';
import MessageBubble from './MessageBubble.vue';
import { getChatTimestamp, isSameDay, formatDayLabel } from '../../utils/chatTime';

const auth = useAuthStore();
const store = useChatsStore();
const router = useRouter();

const draft = ref('');
const threadEl = ref(null);
const sending = ref(false);
const pendingImage = ref(null);
const imageInput = ref(null);

const pickImage = (event) => {
    const file = event.target.files?.[0];

    if (file) {
        pendingImage.value = file;
    }

    event.target.value = '';
};

const activeConversation = computed(() => store.activeConversation);

// 메시지 목록 + 날짜 구분선/그룹 메타
// 그룹 = 같은 상대 + 같은 분(HH:MM) 연속 메시지.
// - 그룹 시작: 아바타·이름 노출
// - 그룹 마지막: 그 아래에 시간만 표시 (중간 말풍선엔 시간 생략)
// - 날짜: 오늘은 시간만, 이전 날짜는 구분선
const minuteKeyOf = (msg) => {
    const ts = getChatTimestamp(msg.created_at_iso ?? msg.created_at);

    return ts ? `${ts.getFullYear()}-${ts.getMonth()}-${ts.getDate()}-${ts.getHours()}-${ts.getMinutes()}` : null;
};

const messageRows = computed(() => {
    const rows = [];
    let prevDayKey = null;
    let prevUserId = null;
    let prevMinuteKey = null;
    const now = new Date();

    for (let i = 0; i < store.messages.length; i++) {
        const msg = store.messages[i];
        const next = store.messages[i + 1];
        const ts = getChatTimestamp(msg.created_at_iso ?? msg.created_at);
        const dayKey = ts ? `${ts.getFullYear()}-${ts.getMonth()}-${ts.getDate()}` : null;
        const minuteKey = minuteKeyOf(msg);
        const showSep = Boolean(ts && dayKey && dayKey !== prevDayKey && !isSameDay(ts, now));

        const isGroupStart = !(msg.user_id === prevUserId && minuteKey && minuteKey === prevMinuteKey);
        const isGroupEnd = !(next && next.user_id === msg.user_id && minuteKey && minuteKeyOf(next) === minuteKey);

        rows.push({
            msg,
            showSep,
            dayLabel: showSep ? formatDayLabel(ts) : '',
            isFirst: isGroupStart,
            isLast: isGroupEnd,
        });

        if (dayKey) {
            prevDayKey = dayKey;
        }
        prevUserId = msg.user_id;
        prevMinuteKey = minuteKey;
    }

    return rows;
});

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
    if ((!body && !pendingImage.value) || sending.value) return;
    sending.value = true;
    const image = pendingImage.value;
    pendingImage.value = null;
    draft.value = '';
    try {
        await store.send(body, image);
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
            <!-- 연결된 운행 정보 카드 -->
            <button
                v-if="activeConversation?.order"
                type="button"
                class="chat-order-card"
                @click="router.push({ name: 'order-detail', params: { id: activeConversation.order.id } })"
            >
                <span class="chat-order-card__tag">운행</span>
                <span class="chat-order-card__route">{{ activeConversation.order.route }}</span>
                <span class="chat-order-card__meta">
                    {{ activeConversation.order.service_date }} {{ activeConversation.order.service_time }} · {{ activeConversation.order.statusLabel }}
                </span>
                <span class="chat-order-card__amount">{{ Number(activeConversation.order.amount).toLocaleString() }}원</span>
            </button>

            <template v-for="row in messageRows" :key="row.msg.id">
                <div v-if="row.showSep" class="chat-day-sep">{{ row.dayLabel }}</div>
                <MessageBubble
                    :msg="row.msg"
                    :is-mine="row.msg.user_id === auth.user?.id"
                    :is-first="row.isFirst"
                    :is-last="row.isLast"
                    :counterpart-name="activeConversation?.counterpart?.name"
                />
            </template>
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
            <!-- 이미지 첨부 -->
            <input ref="imageInput" type="file" accept="image/*" hidden @change="pickImage" />
            <button
                type="button"
                class="chat-thread__attach"
                :class="{ 'chat-thread__attach--active': pendingImage }"
                :disabled="sending"
                title="이미지 첨부"
                @click="imageInput?.click()"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
            </button>

            <input
                v-model="draft"
                type="text"
                :placeholder="pendingImage ? '사진과 함께 보낼 메시지 (선택)' : '메시지를 입력하세요...'"
                :disabled="sending"
            />
            <n-button type="primary" attr-type="submit" :loading="sending" :disabled="!draft.trim() && !pendingImage">보내기</n-button>
        </form>
    </div>
</template>

<style scoped>
/* 대화방 — 전체 화면 고정 오버레이.
   window 스크롤과 완전 분리되어 어떤 기기에서도 레이아웃이 어긋나지 않는다.
   padding-top은 상단 헤더(62px) 높이만큼 확보. */
.chat-thread{position:fixed;inset:0;z-index:9;display:flex;flex-direction:column;padding-top:54px;background:var(--bg)}
.chat-thread__messages{flex:1;overflow-y:auto;padding:12px 14px 16px;display:flex;flex-direction:column;gap:6px;-webkit-overflow-scrolling:touch}

/* 날짜 구분선 — 양쪽 라인 + 가운데 날짜 */
.chat-day-sep{display:flex;align-items:center;gap:10px;margin:14px 0 8px;color:var(--text-muted);font-size:12px;font-weight:600;flex-shrink:0}
.chat-day-sep::before,.chat-day-sep::after{content:'';flex:1;height:1px;background:var(--border)}

/* 연결된 운행 카드 — 대화방 상단 */
.chat-order-card{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;width:100%;text-align:left;margin-bottom:8px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--surface);cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,.05)}
.chat-order-card:hover{border-color:var(--brand)}
.chat-order-card__tag{flex-shrink:0;padding:2px 8px;border-radius:999px;background:color-mix(in srgb,var(--brand) 14%,transparent);color:var(--brand);font-size:11px;font-weight:700}
.chat-order-card__route{flex:1;min-width:120px;font-size:13px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-order-card__meta{width:100%;font-size:12px;color:var(--text-muted)}
.chat-order-card__amount{flex-shrink:0;font-size:13px;font-weight:700;color:var(--text)}

/* 입력창 — flex 하단에 자연 배치 (fixed 불필요) */
.chat-thread__input{display:flex;gap:8px;padding:10px 14px calc(10px + env(safe-area-inset-bottom));border-top:1px solid var(--border);background:var(--surface)}
.chat-thread__attach{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid var(--border);border-radius:50%;background:var(--bg);color:var(--text-muted);cursor:pointer;flex-shrink:0;transition:color .15s ease,border-color .15s ease}
.chat-thread__attach svg{width:18px;height:18px}
.chat-thread__attach--active{color:var(--brand);border-color:var(--brand)}
.chat-thread__attach:disabled{opacity:.5;cursor:not-allowed}
.chat-thread__input input{flex:1;border:1px solid var(--border);border-radius:20px;padding:10px 16px;font-size:14px;background:var(--bg);color:var(--text);outline:none}
.chat-thread__input input:focus{border-color:var(--brand)}

/* 새 메시지 도착 배지 */
.chat-thread__jump{position:absolute;bottom:calc(64px + env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:10;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:0;border-radius:999px;background:var(--brand);color:#fff;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px color-mix(in srgb,var(--brand) 40%,transparent)}
.chat-thread__jump svg{width:16px;height:16px}
</style>
