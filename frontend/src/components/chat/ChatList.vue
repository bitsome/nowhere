<script setup>
import { computed, ref } from 'vue';
import { formatTime } from '../../utils/formatTime';
import { getChatTimestamp } from '../../utils/chatTime';
import { useAuthStore } from '../../stores/auth';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    conversations: { type: Array, required: true },
});

const emit = defineEmits(['open', 'mark-all-read']);

const auth = useAuthStore();

// 안 읽은 대화 집계 — 목록 위 '모두 읽음' 툴바 노출 조건
const unreadTotal = computed(() => props.conversations.reduce((sum, c) => sum + (c.unread_count ?? 0), 0));
const hasUnread = computed(() => unreadTotal.value > 0);

// 대화 유형 칩 — 마지막 메시지의 채팅 유형을 라벨로 보여준다
const typeChipOf = (msg) => {
    if (!msg) return '';

    const t = msg.type ?? 'text';
    const s = msg.payload?.status;

    if (t === 'approval') {
        if (s === 'approved') return '승인 확인';
        if (s === 'rejected') return '승인 거절';
        return '승인 요청';
    }

    return {
        time_change: '시간 변경',
        route_change: '경로 변경',
        payment_change: '요금 협의',
        cancel: '취소 요청',
    }[t] ?? '';
};

const typeChipClass = (msg) => {
    const t = msg?.type ?? 'text';
    const s = msg?.payload?.status;

    if (t === 'cancel') return 'chat-list__type-chip--red';
    if (t === 'approval') return s === 'rejected' ? 'chat-list__type-chip--red' : 'chat-list__type-chip--green';
    if (t === 'time_change' || t === 'route_change' || t === 'payment_change') return 'chat-list__type-chip--yellow';

    return '';
};

// 운행 일정 — "8/20(목) 10:00" (KST 날짜 문자열 기준)
const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];

const formatSchedule = (date, time) => {
    if (!date) return time || '';

    const d = new Date(`${date}T00:00:00`);

    if (isNaN(d.getTime())) return time || '';

    const label = `${d.getMonth() + 1}/${d.getDate()}(${WEEKDAYS[d.getDay()]})`;

    return time ? `${label} ${time}` : label;
};

// 대화의 운행 요약 — "출발지 → 도착지 · 8/20(목) 10:00"
const orderSchedule = (conv) => {
    const order = conv.order;
    if (!order?.route) return '';

    const when = formatSchedule(order.service_date, order.service_time);

    return when ? `${order.route} · ${when}` : order.route;
};

// 날짜 카테고리 — 오늘/어제/이번 주/이전
const startOfDay = (ts) => new Date(ts.getFullYear(), ts.getMonth(), ts.getDate()).getTime();
const startOfWeek = (ts) => {
    const x = new Date(ts);
    x.setDate(x.getDate() - ((x.getDay() + 6) % 7));
    x.setHours(0, 0, 0, 0);

    return x.getTime();
};

// 칩 탭 — 대화를 오늘 / 어제 / 지난주로 분류한다.
// (지난주는 오늘·어제를 뺀 이전 대화 전체를 담아 대화가 숨겨지지 않게 한다)
const CHAT_TABS = [
    { key: 'today', label: '오늘' },
    { key: 'yesterday', label: '어제' },
    { key: 'past', label: '지난주' },
];

const dayKeyOf = (ts) => {
    const now = new Date();
    const dayDiff = Math.floor((startOfDay(now) - startOfDay(ts)) / 86400000);

    if (dayDiff <= 0) return 'today';
    if (dayDiff === 1) return 'yesterday';

    return 'past';
};

const activeTab = ref('today');

// 칩 건수 — 탭에서 볼 수 있는 대화 수를 함께 보여준다
const tabCounts = computed(() => {
    const counts = { today: 0, yesterday: 0, past: 0 };

    for (const conv of props.conversations) {
        const ts = getChatTimestamp(conv.last_message_at) ?? new Date();
        counts[dayKeyOf(ts)] += 1;
    }

    return counts;
});

// 선택한 탭의 대화만 남긴 뒤, 안쪽에서 날짜 섹션으로 나눈다
const filteredConversations = computed(() =>
    props.conversations.filter((conv) => dayKeyOf(getChatTimestamp(conv.last_message_at) ?? new Date()) === activeTab.value),
);

const sections = computed(() => {
    const now = new Date();
    const order = [];
    const groups = {};

    for (const conv of filteredConversations.value) {
        // last_message_at은 상대시간("12 minutes ago") 또는 ISO로 온다 — 채팅 파서로 변환
        const ts = getChatTimestamp(conv.last_message_at) ?? new Date();
        const t = ts.getTime();
        const dayDiff = Math.floor((startOfDay(now) - startOfDay(ts)) / 86400000);

        let key = '이전';

        if (dayDiff <= 0) key = '오늘';
        else if (dayDiff === 1) key = '어제';
        else if (t >= startOfWeek(now)) key = '이번 주';

        if (!groups[key]) {
            groups[key] = [];
            order.push(key);
        }

        groups[key].push(conv);
    }

    return order.map((key) => ({ key, items: groups[key] }));
});
</script>

<template>
    <div>
        <!-- 모두 읽음 툴바 — 안 읽은 대화가 있을 때만 노출 -->
        <div v-if="hasUnread" class="chat-toolbar">
            <span class="chat-toolbar__count">안 읽은 메시지 {{ unreadTotal }}개</span>
            <button
                type="button"
                class="chat-toolbar__read-all"
                @click="emit('mark-all-read')"
            >
                <BaseIcon name="check-done" :size="13" />
                모두 읽음
            </button>
        </div>
        <!-- 분류 칩 — 오늘 / 어제 / 지난주 -->
        <div class="chat-chip-row" role="tablist" aria-label="대화 기간 분류">
            <button
                v-for="tab in CHAT_TABS"
                :key="tab.key"
                type="button"
                role="tab"
                class="chat-chip"
                :class="{ 'chat-chip--active': activeTab === tab.key }"
                :aria-selected="activeTab === tab.key"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
                <em class="chat-chip__count">{{ tabCounts[tab.key] }}</em>
            </button>
        </div>

        <div v-if="filteredConversations.length" class="chat-list">
            <template v-for="(section, index) in sections" :key="section.key">
                <!-- 첫 그룹 앞 구분 라벨은 두지 않는다 — 칩 바로 아래 첫 대화가 붙도록 (마켓 등과 동일) -->
                <div v-if="index > 0" class="chat-list__sep">{{ section.key }}</div>
                <div
                    v-for="conv in section.items"
                    :key="conv.id"
                    class="chat-list__item"
                    :class="{ 'chat-list__item--unread': conv.unread_count > 0 }"
                    @click="emit('open', conv.id)"
                >
                    <span
                        class="chat-list__avatar"
                        :class="{ 'chat-list__avatar--unread': conv.unread_count > 0 }"
                    >
                        {{ conv.counterpart?.name?.charAt(0) ?? '?' }}
                    </span>
                    <div class="chat-list__body">
                        <div class="chat-list__row">
                            <strong>{{ conv.counterpart?.name }}</strong>
                            <span class="chat-list__time">{{ formatTime(conv.last_message_at) }}</span>
                        </div>
                        <div class="chat-list__row">
                            <span
                                class="chat-list__preview"
                                :class="{ 'chat-list__preview--unread': conv.unread_count > 0 }"
                            >
                                <template v-if="conv.last_message">
                                    <span v-if="conv.last_message.user_id === auth.user?.id" class="chat-list__mine">나: </span>{{ conv.last_message.body }}
                                </template>
                                <template v-else>대화를 시작해보세요</template>
                            </span>
                            <span v-if="typeChipOf(conv.last_message)" class="chat-list__type-chip" :class="typeChipClass(conv.last_message)">
                                {{ typeChipOf(conv.last_message) }}
                            </span>
                            <span v-if="conv.order" class="chat-list__order-chip">운행</span>
                            <n-badge v-if="conv.unread_count > 0" :value="conv.unread_count" :max="99" />
                        </div>
                        <div v-if="conv.order?.route" class="chat-list__route">
                            <span class="chat-list__route-dot" />
                            <span class="chat-list__route-text">{{ orderSchedule(conv) }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <p v-else class="chat-empty">이 기간의 대화가 없습니다.</p>
    </div>
</template>

<style scoped>
/* 분류 칩 — 오늘 / 어제 / 지난주 (마켓 칩과 동일한 필 언어) */
.chat-chip-row{display:flex;gap:6px;margin-bottom:var(--chips-gap);overflow-x:auto;scrollbar-width:none}
.chat-chip-row::-webkit-scrollbar{display:none}
.chat-chip{flex-shrink:0;display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid var(--border);border-radius:999px;background:var(--surface);color:var(--text-muted);font-size:11.5px;font-weight:600;font-family:inherit;cursor:pointer;transition:border-color .15s ease,color .15s ease,background .15s ease}
.chat-chip:hover{border-color:color-mix(in srgb,var(--brand) 40%,transparent);color:var(--brand)}
.chat-chip--active{border-color:var(--brand);background:var(--brand-soft);color:var(--brand);font-weight:700}
.chat-chip__count{min-width:18px;padding:1px 5px;border-radius:999px;background:color-mix(in srgb,var(--text-muted) 12%,transparent);color:var(--text-muted);font-size:10px;font-weight:600;font-style:normal;text-align:center;line-height:16px}
/* 활성 칩 — brand 채움에는 어두운 글자(#07120e) 표준 적용 */
.chat-chip--active .chat-chip__count{background:var(--brand);color:#07120e}
/* 빈 기간 안내 — 다른 화면의 빈 상태처럼 칩 아래 8px에 카드로 붙는다 */
.chat-empty{margin:0;padding:22px 16px;text-align:center;color:var(--text-muted);font-size:11px;background:var(--surface);border:1px solid var(--border);border-radius:var(--card-radius)}

/* 대화 목록 — 날짜 카테고리 + 개별 카드형 */
.chat-list{display:flex;flex-direction:column;gap:var(--card-gap);overflow:visible}
.chat-list__sep{margin:0 2px;font-size: 11px;font-weight:800;color:var(--text-muted)}
.chat-list__item{display:flex;align-items:center;gap:12px;padding:12px var(--card-pad);background:var(--surface);border:1px solid var(--border);border-radius:var(--card-radius);box-shadow:0 1px 3px rgba(0,0,0,.04);cursor:pointer;transition:border-color .12s ease,box-shadow .12s ease}
.chat-list__item:hover{border-color:color-mix(in srgb,var(--brand) 35%,transparent);box-shadow:0 2px 8px rgba(0,0,0,.06)}

/* 안읽음 대화 강조 */
.chat-list__item--unread{border-color:color-mix(in srgb,var(--brand) 40%,transparent);background:color-mix(in srgb,var(--brand) 4%,transparent)}
.chat-list__item--unread:hover{border-color:color-mix(in srgb,var(--brand) 55%,transparent)}
.chat-list__item--unread .chat-list__row > strong{font-weight:800}
.chat-list__avatar--unread{box-shadow:0 0 0 2px var(--accent)}
.chat-list__preview--unread{font-weight:700;color:var(--text)}
.chat-list__avatar{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;background:var(--accent);color:#fff;font-size: 12px;font-weight:700;flex-shrink:0}
.chat-list__body{flex:1;min-width:0}
.chat-list__row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.chat-list__time{color:var(--text-muted);font-size: 11px;flex-shrink:0}
.chat-list__preview{color:var(--text-muted);font-size: 11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-list__mine{font-weight:600;color:var(--text)}
.chat-list__order-chip{flex-shrink:0;padding:1px 6px;border-radius:6px;background:color-mix(in srgb,var(--brand) 12%,transparent);color:var(--brand);font-size: 10px;font-weight:400}
/* 운행 경로 + 시간 */
.chat-list__route{display:flex;align-items:center;gap:6px;margin-top:6px;min-width:0}
.chat-list__route-dot{width:6px;height:6px;flex-shrink:0;border-radius:50%;background:var(--brand)}
.chat-list__route-text{color:var(--text-muted);font-size: 11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-list__type-chip{flex-shrink:0;padding:1px 6px;border-radius:6px;font-size: 10px;font-weight:400}
/* 기본(라이트) — 알림센터 v8 상태 칩과 동일 팔레트(대비 확보) */
.chat-list__type-chip--green{background:rgba(99,226,183,.12);color:#0e9d8d}
.chat-list__type-chip--yellow{background:rgba(242,184,75,.14);color:#9a6a00}
.chat-list__type-chip--red{background:rgba(224,91,91,.12);color:#c03a3f}
/* 다크 — 밝은 톤으로 대비 유지 */
html.dark .chat-list__type-chip--green{color:var(--brand)}
html.dark .chat-list__type-chip--yellow{color:#ffd071}
html.dark .chat-list__type-chip--red{color:#ff8a8a}

/* 모두 읽음 툴바 — 안 읽은 대화 수 + 일괄 읽음 버튼 */
.chat-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:var(--chips-gap)}
.chat-toolbar__count{color:var(--text-muted);font-size:11px;font-weight:600}
.chat-toolbar__read-all{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border:1px solid var(--border);border-radius:999px;background:var(--surface);color:var(--brand);font-size:11px;font-weight:600;font-family:inherit;cursor:pointer;transition:border-color .15s ease,background .15s ease}
.chat-toolbar__read-all:hover{border-color:color-mix(in srgb,var(--brand) 50%,transparent);background:var(--brand-soft)}
.chat-toolbar__read-all :deep(svg){flex-shrink:0}
</style>
