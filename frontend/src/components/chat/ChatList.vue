<script setup>
import { computed } from 'vue';
import { formatTime } from '../../utils/formatTime';
import { getChatTimestamp } from '../../utils/chatTime';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
    conversations: { type: Array, required: true },
});

const emit = defineEmits(['open']);

const auth = useAuthStore();

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

const sections = computed(() => {
    const now = new Date();
    const order = [];
    const groups = {};

    for (const conv of props.conversations) {
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
    <div class="chat-list">
        <template v-for="section in sections" :key="section.key">
            <div class="chat-list__sep">{{ section.key }}</div>
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
</template>

<style scoped>
/* 대화 목록 — 날짜 카테고리 + 개별 카드형 */
.chat-list{display:flex;flex-direction:column;gap:10px;padding-top:12px;overflow:visible}
.chat-list__sep{margin:2px 2px 0;font-size: 11px;font-weight:800;color:var(--text-muted)}
.chat-list__sep:not(:first-child){margin-top:18px}
.chat-list__item{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.04);cursor:pointer;transition:border-color .12s ease,box-shadow .12s ease}
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
.chat-list__order-chip{flex-shrink:0;padding:1px 6px;border-radius:6px;background:color-mix(in srgb,var(--brand) 12%,transparent);color:var(--brand);font-size: 11px;font-weight:700}
/* 운행 경로 + 시간 */
.chat-list__route{display:flex;align-items:center;gap:6px;margin-top:6px;min-width:0}
.chat-list__route-dot{width:6px;height:6px;flex-shrink:0;border-radius:50%;background:var(--brand)}
.chat-list__route-text{color:var(--text-muted);font-size: 11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-list__type-chip{flex-shrink:0;padding:1px 6px;border-radius:6px;font-size: 11px;font-weight:700}
.chat-list__type-chip--green{background:rgba(99,226,183,.12);color:var(--brand)}
.chat-list__type-chip--yellow{background:rgba(242,184,75,.14);color:#ffd071}
.chat-list__type-chip--red{background:rgba(224,91,91,.12);color:#ff8a8a}
</style>
