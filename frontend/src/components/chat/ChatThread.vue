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
import ChatImageSheet from './ChatImageSheet.vue';
import ReportDialog from '../reports/ReportDialog.vue';
import { useChatRows } from '../../composables/useChatRows';
import { statusColorVar } from '../../utils/colors';
import BaseIcon from '../common/BaseIcon.vue';

const auth = useAuthStore();
const store = useChatsStore();
const router = useRouter();
const message = useMessage();

const draft = ref('');
const threadEl = ref(null);
const sending = ref(false);
const requestOpen = ref(false);
const imageOpen = ref(false);

// ── 채팅 신고 — 상대방·대화 내용 문제를 운영팀에 접수 (관리자는 신고하지 않음) ──
const reportOpen = ref(false);

const canReportChat = computed(() => Boolean(activeConversation.value && auth.user && !auth.isAdmin));

const reportSubject = computed(() => {
    const counterpart = activeConversation.value?.counterpart?.name;

    return counterpart ? `${counterpart}님과의 채팅` : '채팅';
});

const activeConversation = computed(() => store.activeConversation);

// 연결된 운행 상태 색상 — 중앙 팔레트(utils/colors.js) 참조
const orderStatusColor = computed(
    () => statusColorVar[activeConversation.value?.order?.status] ?? 'var(--status-draft)',
);

// 상태 태그 글자색 — 밝은 상태색(공개·요금 협의·운행중·완료 등) 위 흰 글자는 대비가 약해 어두운 글자 사용
// (운행 기록 화면 RideHistoryView statusTextColor와 동일 규칙)
const orderStatusTextColor = computed(() => {
    const status = activeConversation.value?.order?.status;

    return ['published', 'trading', 'driving', 'completed', 'acceptance_pending'].includes(status)
        ? '#101418'
        : '#ffffff';
});

// 메시지 목록 → 렌더 행 (날짜 구분선·이벤트 유형 구분·같은 분 그룹 규칙) — 공용 컴포저블 사용
const messageRows = useChatRows(store.messages);

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

// 메시지 삭제 — 실패하면 토스트로 알리고 목록은 그대로 둔다
const onDeleteMessage = async (messageId) => {
    try {
        await store.deleteMessage(messageId);
    } catch (e) {
        message.error(getApiErrorMessage(e, '메시지 삭제에 실패했습니다.'));
    }
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
    } catch (e) {
        // 전송 실패 시 입력값 복원 — 메시지 유실 방지
        draft.value = body;
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
                        :style="{ background: orderStatusColor, borderColor: orderStatusColor, color: orderStatusTextColor }"
                    >
                        {{ activeConversation.order.statusLabel }}
                    </span>
                    {{ activeConversation.order.service_date }} {{ activeConversation.order.service_time }}
                </span>
                <span class="chat-order-card__amount">{{ Number(activeConversation.order.amount).toLocaleString() }}원</span>
            </button>

            <!-- 채팅 신고 — 상대방과의 대화 문제를 운영팀에 접수 (대화 내용은 운영팀만 확인) -->
            <div v-if="canReportChat" class="chat-thread__guard">
                <button type="button" class="chat-thread__report" @click="reportOpen = true">
                    <BaseIcon name="warning" :size="12" />
                    신고
                </button>
            </div>

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
                    @delete="onDeleteMessage"
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

                <!-- 이미지 첨부 — 모달에서 사진 선택·관리 -->
                <button
                    type="button"
                    class="chat-thread__attach"
                    :disabled="sending"
                    title="이미지 첨부"
                    @click="imageOpen = true"
                >
                    <BaseIcon name="image" :size="20" />
                </button>

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
                    class="chat-thread__send"
                    :loading="sending"
                    :disabled="!draft.trim()"
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

        <!-- 이미지 첨부 — 모달에서 사진 선택·관리·전송 -->
        <ChatImageSheet
            v-model:show="imageOpen"
            @sent="onRequestSent"
        />

        <!-- 채팅 신고 — 상대방·대화 내용 문제 접수 -->
        <ReportDialog
            v-model:show="reportOpen"
            target-type="chat"
            :target-id="store.activeId"
            :subject-text="reportSubject"
        />
    </div>
</template>

<style scoped>
/* 대화방 — 전체 화면 고정 오버레이.
   window 스크롤과 완전 분리되어 어떤 기기에서도 레이아웃이 어긋나지 않는다.
   padding-top은 상단 헤더(62px) 높이만큼 확보. */
.chat-thread{position:fixed;inset:0;z-index:9;display:flex;flex-direction:column;padding-top:54px;background:var(--bg)}
.chat-thread__messages{flex:1;overflow-y:auto;padding:12px 14px 16px;display:flex;flex-direction:column;gap:6px;-webkit-overflow-scrolling:touch}

/* 날짜/유형 구분선은 전역 공용(base.css) — ChatThread·OrderDetailChat 동일 스타일 */

/* 연결된 운행 카드 — 대화방 상단 */
.chat-order-card{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;width:100%;text-align:left;margin-bottom:8px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--surface);cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,.05)}
.chat-order-card:hover{border-color:var(--brand)}
.chat-order-card__tag{flex-shrink:0;padding:1px 6px;border-radius:999px;background:color-mix(in srgb,var(--brand) 14%,transparent);color:var(--brand);font-size: 10px;font-weight:400}
.chat-order-card__route{flex:1;min-width:120px;font-size: 11px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-order-card__meta{width:100%;font-size: 11px;color:var(--text-muted)}
/* 운행 상태 색상 태그 — 배경은 상태별 팔레트 색상, 글자색은 상태 밝기에 따라 인라인 지정
   (RideHistoryView 운행 기록 배지와 동일 — 밝은 상태는 #101418, 나머지는 흰 글자) */
.chat-order-card__status{display:inline-flex;align-items:center;margin-right:6px;padding:1px 6px;border-radius:999px;font-size: 10px;font-weight:400;white-space:nowrap}
.chat-order-card__amount{flex-shrink:0;font-size: 11px;font-weight:700;color:var(--text)}

/* 채팅 신고 — 메시지 상단 우측 작은 유틸 버튼 */
.chat-thread__guard{display:flex;justify-content:flex-end;margin-bottom:4px}
.chat-thread__report{display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border:0;border-radius:999px;background:none;color:var(--text-muted);font-size: 10px;font-weight:500;cursor:pointer;opacity:.75;transition:color .15s ease,background .15s ease,opacity .15s ease}
.chat-thread__report:hover{color:var(--danger);background:color-mix(in srgb,var(--danger) 8%,transparent);opacity:1}

/* 입력 영역 — 입력줄을 감싼다 */
.chat-thread__composer{border-top:1px solid var(--border);background:var(--surface)}

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
.chat-thread__attach:disabled{opacity:.5;cursor:not-allowed}
.chat-thread__input input{flex:1;border:1px solid var(--border);border-radius:20px;padding:10px 16px;font-size: 11px;background:var(--bg);color:var(--text);outline:none}
.chat-thread__input input:focus{border-color:var(--brand)}

/* 새 메시지 도착 배지 — brand 채움에는 어두운 글자(#07120e) 표준 적용 */
.chat-thread__jump{position:absolute;bottom:calc(64px + env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:10;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:0;border-radius:999px;background:var(--brand);color:#07120e;font-size: 11px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px color-mix(in srgb,var(--brand) 40%,transparent)}
.chat-thread__jump svg{width:16px;height:16px}
</style>
