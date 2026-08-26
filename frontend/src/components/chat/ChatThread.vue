<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useChatsStore } from '../../stores/chats';
import { getApiErrorMessage } from '../../api/client';
import MessageBubble from './MessageBubble.vue';
import ChatRequestEvent from './ChatRequestEvent.vue';
import ChatRequestSheet from './ChatRequestSheet.vue';
import { getChatTimestamp, isSameDay, formatDayLabel } from '../../utils/chatTime';
import { statusColorVar } from '../../utils/colors';
import BaseIcon from '../common/BaseIcon.vue';

const auth = useAuthStore();
const store = useChatsStore();
const router = useRouter();
const message = useMessage();

const draft = ref('');
const threadEl = ref(null);
const sending = ref(false);
const pendingImage = ref(null);
const pendingPreview = ref('');
const imageInput = ref(null);
const requestOpen = ref(false);

const clearPendingImage = () => {
    if (pendingPreview.value) {
        URL.revokeObjectURL(pendingPreview.value);
    }
    pendingImage.value = null;
    pendingPreview.value = '';
};

const pickImage = (event) => {
    const file = event.target.files?.[0];

    if (file) {
        clearPendingImage();
        pendingImage.value = file;
        pendingPreview.value = URL.createObjectURL(file);
    }

    event.target.value = '';
};

const activeConversation = computed(() => store.activeConversation);

// 연결된 운행 상태 색상 — 중앙 팔레트(utils/colors.js) 참조
const orderStatusColor = computed(
    () => statusColorVar[activeConversation.value?.order?.status] ?? 'var(--status-draft)',
);

// 메시지 목록 + 날짜/유형 카테고리 + 그룹 메타
// - 날짜 카테고리: 날짜가 바뀔 때마다 '오늘/어제/8월 17일' 구분선
// - 유형 카테고리: 요청 이벤트(승인/시간변경/경로변경/요금협의/취소)를 유형별로 구분
// - 그룹 = 같은 상대 + 같은 분(HH:MM) 연속 메시지 (아바타·이름·시간 노출 규칙)
const minuteKeyOf = (msg) => {
    const ts = getChatTimestamp(msg.created_at_iso ?? msg.created_at);

    return ts ? `${ts.getFullYear()}-${ts.getMonth()}-${ts.getDate()}-${ts.getHours()}-${ts.getMinutes()}` : null;
};

const typeLabelOf = (type) => ({
    approval: '승인 요청',
    time_change: '시간 변경 요청',
    route_change: '경로 변경 요청',
    payment_change: '요금 협의 요청',
    cancel: '운행 취소 요청',
}[type] ?? '요청');

const messageRows = computed(() => {
    const rows = [];
    let prevDayKey = null;
    let prevUserId = null;
    let prevMinuteKey = null;
    let prevTypeLabel = '';
    const now = new Date();

    for (let i = 0; i < store.messages.length; i++) {
        const msg = store.messages[i];
        const next = store.messages[i + 1];
        const ts = getChatTimestamp(msg.created_at_iso ?? msg.created_at);
        const dayKey = ts ? `${ts.getFullYear()}-${ts.getMonth()}-${ts.getDate()}` : null;
        const minuteKey = minuteKeyOf(msg);
        const showSep = Boolean(ts && dayKey && dayKey !== prevDayKey);

        // 요청 이벤트는 말풍선 그룹에 섞이지 않도록 항상 단독 행으로 처리한다
        const isEvent = (msg.type ?? 'text') !== 'text';
        const typeLabel = isEvent ? typeLabelOf(msg.type) : '';
        const showTypeSep = isEvent && typeLabel !== prevTypeLabel;
        const isGroupStart = isEvent || !(msg.user_id === prevUserId && minuteKey && minuteKey === prevMinuteKey);
        const isGroupEnd = isEvent || !(next && next.user_id === msg.user_id && minuteKey && minuteKeyOf(next) === minuteKey);

        rows.push({
            msg,
            showSep,
            dayLabel: showSep ? (isSameDay(ts, now) ? '오늘' : formatDayLabel(ts)) : '',
            showTypeSep,
            typeLabel,
            isFirst: isGroupStart,
            isLast: isGroupEnd,
            isEvent,
        });

        if (dayKey) {
            prevDayKey = dayKey;
        }
        prevUserId = msg.user_id;
        prevMinuteKey = minuteKey;
        prevTypeLabel = typeLabel;
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

// 요청 전송 완료 후 바닥으로
const onRequestSent = async () => {
    isNearBottom.value = true;
    newMessages.value = 0;
    await scrollToBottom();
};

const send = async () => {
    const body = draft.value.trim();
    if ((!body && !pendingImage.value) || sending.value) return;
    sending.value = true;
    const image = pendingImage.value;
    clearPendingImage();
    draft.value = '';
    try {
        await store.send(body, image);
        isNearBottom.value = true;
        newMessages.value = 0;
    } catch (e) {
        // 전송 실패 시 입력값 복원 — 메시지 유실 방지
        if (body) draft.value = body;
        if (image) {
            pendingImage.value = image;
            pendingPreview.value = URL.createObjectURL(image);
        }
        message.error(getApiErrorMessage(e, '메시지 전송에 실패했습니다.'));
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
                    <span
                        class="chat-order-card__status"
                        :style="{ background: orderStatusColor, borderColor: orderStatusColor }"
                    >
                        {{ activeConversation.order.statusLabel }}
                    </span>
                    {{ activeConversation.order.service_date }} {{ activeConversation.order.service_time }}
                </span>
                <span class="chat-order-card__amount">{{ Number(activeConversation.order.amount).toLocaleString() }}원</span>
            </button>

            <template v-for="row in messageRows" :key="row.msg.id">
                <div v-if="row.showSep" class="chat-day-sep">{{ row.dayLabel }}</div>
                <div v-if="row.showTypeSep" class="chat-type-sep">{{ row.typeLabel }}</div>
                <ChatRequestEvent
                    v-if="row.isEvent"
                    :msg="row.msg"
                    :is-mine="row.msg.user_id === auth.user?.id"
                />
                <MessageBubble
                    v-else
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
            <BaseIcon name="chevron-down" :size="14" />
            새 메시지 {{ newMessages }}
        </button>

        <form class="chat-thread__composer" @submit.prevent="send">
            <!-- 첨부 이미지 미리보기 — 사진 선택 후 썸네일 + 취소 -->
            <div v-if="pendingImage" class="chat-thread__preview">
                <div class="chat-thread__preview-thumb">
                    <img :src="pendingPreview" alt="첨부 이미지 미리보기" />
                    <button
                        type="button"
                        class="chat-thread__preview-remove"
                        aria-label="첨부 취소"
                        @click="clearPendingImage"
                    >
                        <BaseIcon name="close" :size="12" />
                    </button>
                </div>
            </div>

            <div class="chat-thread__input">
                <!-- 운행 요청 메뉴 -->
                <button
                    type="button"
                    class="chat-thread__request"
                    :disabled="sending"
                    title="운행 요청"
                    @click="requestOpen = true"
                >
                    <BaseIcon name="my-posts" :size="20" />
                </button>

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
                    <BaseIcon name="image" :size="20" />
                </button>

                <input
                    v-model="draft"
                    type="text"
                    :placeholder="pendingImage ? '사진과 함께 보낼 메시지 (선택)' : '메시지를 입력하세요...'"
                    :disabled="sending"
                />
                <n-button
                    type="primary"
                    attr-type="submit"
                    circle
                    class="chat-thread__send"
                    :loading="sending"
                    :disabled="!draft.trim() && !pendingImage"
                    title="보내기"
                >
                    <BaseIcon name="send" :size="18" />
                </n-button>
            </div>
        </form>

        <!-- 운행 요청 메뉴/폼 -->
        <ChatRequestSheet
            v-model:show="requestOpen"
            :order="activeConversation?.order"
            @sent="onRequestSent"
        />
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

/* 채팅 유형 카테고리 — 요청 이벤트 유형별 구분 라벨 */
.chat-type-sep{align-self:center;margin:6px 0 2px;padding:3px 12px;border-radius:999px;background:color-mix(in srgb,var(--status-accepted) 10%,transparent);border:1px solid color-mix(in srgb,var(--status-accepted) 28%,transparent);color:var(--status-accepted);font-size:10px;font-weight:700;flex-shrink:0}

/* 연결된 운행 카드 — 대화방 상단 */
.chat-order-card{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;width:100%;text-align:left;margin-bottom:8px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--surface);cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,.05)}
.chat-order-card:hover{border-color:var(--brand)}
.chat-order-card__tag{flex-shrink:0;padding:2px 8px;border-radius:999px;background:color-mix(in srgb,var(--brand) 14%,transparent);color:var(--brand);font-size:11px;font-weight:700}
.chat-order-card__route{flex:1;min-width:120px;font-size:13px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-order-card__meta{width:100%;font-size:12px;color:var(--text-muted)}
/* 운행 상태 색상 태그 — 상태별 팔레트 색상 */
.chat-order-card__status{display:inline-flex;align-items:center;margin-right:6px;padding:2px 8px;border-radius:999px;color:#fff;font-size:10px;font-weight:700;white-space:nowrap}
html.dark .chat-order-card__status{color:#101418}
.chat-order-card__amount{flex-shrink:0;font-size:13px;font-weight:700;color:var(--text)}

/* 입력 영역 — 미리보기 + 입력줄을 함께 감싼다 */
.chat-thread__composer{border-top:1px solid var(--border);background:var(--surface)}

/* 첨부 이미지 미리보기 — 썸네일 + 취소 버튼 */
.chat-thread__preview{display:flex;padding:10px 14px 0}
.chat-thread__preview-thumb{position:relative;width:64px;height:64px}
.chat-thread__preview-thumb img{width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid var(--border)}
.chat-thread__preview-remove{position:absolute;top:-7px;right:-7px;display:flex;align-items:center;justify-content:center;width:20px;height:20px;border:0;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;font-size:14px;line-height:1;cursor:pointer}
.chat-thread__preview-remove:hover{background:rgba(0,0,0,.85)}

/* 전송 버튼 — 아이콘 우선 (38px 원형) */
.chat-thread__send{width:38px;height:38px;flex-shrink:0}
.chat-thread__send svg{width:17px;height:17px}

/* 입력줄 — 하단 고정 컨테이너 안에 자연 배치 */
.chat-thread__input{display:flex;gap:8px;padding:10px 14px calc(10px + env(safe-area-inset-bottom))}

/* 운행 요청 메뉴 버튼 */
.chat-thread__request{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid var(--border);border-radius:50%;background:var(--bg);color:var(--brand);cursor:pointer;flex-shrink:0;transition:color .15s ease,border-color .15s ease,background .15s ease}
.chat-thread__request svg{width:18px;height:18px}
.chat-thread__request:hover{color:var(--brand);border-color:var(--brand);background:color-mix(in srgb,var(--brand) 10%,transparent)}
.chat-thread__request:disabled{opacity:.5;cursor:not-allowed}

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
