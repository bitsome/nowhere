<script setup>
import { computed, onActivated, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationsStore } from '../stores/notifications';
import { useUiStore } from '../stores/ui';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import UiCard from '../components/ui/UiCard.vue';
import UiChip from '../components/ui/UiChip.vue';
import UiListRow from '../components/ui/UiListRow.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'NotificationsView' });

const router = useRouter();
const store = useNotificationsStore();
const ui = useUiStore();

// 필터: 전체 / 요청 / 완료 / 채팅 / 시스템
const filter = ref('all');

// 펼쳐진 날짜 그룹 — 오늘만 기본 펼침, 어제·이전 날짜는 접힘
const expandedDays = ref(new Set(['오늘']));
const isExpanded = (label) => expandedDays.value.has(label);
const toggleDay = (label) => {
    const next = new Set(expandedDays.value);

    if (next.has(label)) {
        next.delete(label);
    } else {
        next.add(label);
    }
    expandedDays.value = next;
};

// 필터가 바뀌면 접힘 상태를 초기화한다
watch(filter, () => {
    expandedDays.value = new Set(['오늘']);
});

// ── 상황별 분류 — 제목·내용 키워드로 알림 유형을 판별한다 ──
const KIND = {
    offer: { icon: 'cash', cls: 'price', tag: '제안', tagCls: 'price', group: 'request' },
    request: { icon: 'check', cls: 'req', tag: '요청', tagCls: 'need', group: 'request' },
    warning: { icon: 'calendar', cls: 'warn', tag: '확인', tagCls: 'warn', group: 'request' },
    cancel: { icon: 'close', cls: 'cancel', tag: '취소', tagCls: 'danger', group: 'request' },
    price: { icon: 'cash', cls: 'price', tag: '요금', tagCls: 'price', group: 'request' },
    success: { icon: 'check-done', cls: 'success', tag: '완료', tagCls: 'success', group: 'complete' },
    chat: { icon: 'chat', cls: 'chat', tag: '채팅', tagCls: 'info', group: 'chat' },
    flight: { icon: 'airplane', cls: 'flight', tag: '항공', tagCls: 'flight', group: 'system' },
    ride: { icon: 'car', cls: 'teal', tag: '운행중', tagCls: 'teal', group: 'system' },
    system: { icon: 'info', cls: 'system', tag: '시스템', tagCls: '', group: 'system' },
};

const classify = (notification) => {
    // 요금 제안 알림 — offer_id가 있으면 제안으로 분류 (알림에서 바로 수락/거절)
    if (notification.offer_id) return 'offer';

    const text = `${notification.title ?? ''} ${notification.message ?? ''}`;

    if (/항공|항공편/.test(text)) return 'flight';
    if (/채팅|메시지/.test(notification.title ?? '')) return 'chat';
    if (/취소/.test(notification.title ?? '')) return 'cancel';
    if (/거절|반려/.test(notification.title ?? '')) return 'cancel';
    if (/요금|금액/.test(text)) return 'price';
    if (/정산|출금/.test(text)) return 'success';
    if (/완료/.test(notification.title ?? '')) return 'success';
    if (/승인/.test(notification.title ?? '')) {
        return /요청/.test(notification.title ?? '') ? 'request' : 'success';
    }
    if (/리뷰/.test(notification.title ?? '')) return 'system';
    if (/시간|변경/.test(notification.title ?? '')) return 'warning';
    if (/도착|출발|픽업|탑승/.test(text)) return 'ride';
    if (/요청/.test(notification.title ?? '')) return 'request';
    return 'system';
};

const kindOf = (notification) => KIND[classify(notification)] ?? KIND.system;

// 알림 유형 → 공통 칩 색상 매핑
const TAG_VARIANT = {
    offer: 'yellow',
    request: 'green',
    warning: 'yellow',
    cancel: 'red',
    price: 'yellow',
    success: 'green',
    chat: 'blue',
    flight: 'purple',
    ride: 'teal',
    system: 'default',
};
const tagVariant = (notification) => TAG_VARIANT[classify(notification)] ?? 'default';

const filteredItems = computed(() => {
    const items = store.items;

    if (filter.value === 'all') return items;

    return items.filter((n) => kindOf(n).group === filter.value);
});

// 지금 처리할 알림 — 읽지 않은 요청형(승인·시간·취소·요금)
const priorityItems = computed(() =>
    store.items.filter((n) => !n.read && kindOf(n).group === 'request'),
);

const priorityCls = (n) => ({
    offer: 'price',
    request: 'success',
    warning: 'warn',
    cancel: 'danger',
    price: 'price',
}[classify(n)]);

// 진행 상태 배지 색상 — 운행 상태값 기준
const STATUS_TONE = {
    draft: 'muted',
    published: 'info',
    trading: 'warn',
    accepted: 'info',
    driving: 'ride',
    completed: 'success',
    settled: 'success',
    cancelled: 'danger',
    acceptance_pending: 'warn',
};
const statusTone = (notification) => STATUS_TONE[notification.order_status] ?? 'muted';

// 필터 카운트
const groupCount = (group) => store.items.filter((n) => kindOf(n).group === group).length;

// 날짜 그룹 — 오늘/어제/날짜별
const dayLabel = (iso) => {
    if (!iso) return '최근';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '최근';
    const today = new Date();
    const startOfDay = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
    const diffDays = Math.round((startOfDay(today) - startOfDay(d)) / 86400000);

    if (diffDays === 0) return '오늘';
    if (diffDays === 1) return '어제';

    return `${d.getMonth() + 1}월 ${d.getDate()}일`;
};

const dayGroups = computed(() => {
    const groups = new Map();

    for (const n of filteredItems.value) {
        const label = dayLabel(n.created_at_iso);
        if (!groups.has(label)) groups.set(label, []);
        groups.get(label).push(n);
    }

    return [...groups.entries()].map(([label, items]) => ({ label, items }));
});

// 한글 상대 시간 포맷
function formatTime(val) {
    if (!val) return '';
    const s = String(val);
    const map = [
        [/^just now$/i, '방금'],
        [/^(\d+) second(s)? ago$/i, (_, n) => `${n}초 전`],
        [/^(\d+) minute(s)? ago$/i, (_, n) => `${n}분 전`],
        [/^(\d+) hour(s)? ago$/i, (_, n) => `${n}시간 전`],
        [/^(\d+) day(s)? ago$/i, (_, n) => `${n}일 전`],
        [/^(\d+) week(s)? ago$/i, (_, n) => `${n}주 전`],
        [/^(\d+) month(s)? ago$/i, (_, n) => `${n}개월 전`],
        [/^(\d+) year(s)? ago$/i, (_, n) => `${n}년 전`],
    ];
    for (const [pattern, replacement] of map) {
        const match = s.match(pattern);
        if (match) return typeof replacement === 'function' ? replacement(...match) : replacement;
    }
    return s;
}

onMounted(() => {
    store.load().catch(() => {});
});

// keep-alive 복귀 시 목록 갱신
onActivated(() => {
    store.load().catch(() => {});
});

// 헤더 '···' 메뉴 — 모두 읽음 / 새로고침 수신
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'notifications:read-all') {
            store.markAllRead();
        } else if (ui.actionName === 'refresh') {
            store.load().catch(() => {});
        }
    },
);

const openNotification = async (notification) => {
    if (!notification.read) {
        await store.markRead([notification.id]);
    }

    if (notification.order_id) {
        router.push({ name: 'order-detail', params: { id: notification.order_id } });
    }
};

const formatWon = (v) => `${Number(v ?? 0).toLocaleString('ko-KR')}원`;

// 제안·승인 처리는 액션 센터(처리할 일)에서 — 알림은 안내 역할만 한다
const goActions = () => {
    router.push({ name: 'actions' });
};
</script>

<template>
    <div class="alerts-page">
        <!-- 로딩 스켈레톤 — 알림 카드 목록 골격 -->
        <div v-if="!store.loaded" class="alerts-skeleton">
            <div v-for="n in 5" :key="n" class="sk-card alerts-skeleton__card">
                <div class="alerts-skeleton__row">
                    <div class="sk-circle" />
                    <div class="alerts-skeleton__lines">
                        <div class="sk-line" style="width: 45%; height: 14px;" />
                        <div class="sk-line" style="width: 85%;" />
                        <div class="sk-line" style="width: 30%;" />
                    </div>
                </div>
            </div>
        </div>
        <template v-else>
            <template v-if="store.items.length > 0">
                <!-- 헤더 — 읽지 않음 배지 -->
                <div class="v8-head">
                    <div>
                        <h1 class="v8-head__title">알림센터</h1>
                        <div class="v8-head__sub">상황에 따라 색상을 구분해 빠르게 확인합니다.</div>
                    </div>
                    <span v-if="store.unreadCount > 0" class="v8-total">읽지 않음 {{ store.unreadCount }}</span>
                </div>

                <!-- 지금 처리할 알림 — 상황별 색상 -->
                <UiCard v-if="priorityItems.length > 0" tone="accent" class="v8-priority">
                    <div class="v8-priority-head">
                        <b>지금 처리할 알림</b>
                        <span>{{ priorityItems.length }}건</span>
                    </div>

                    <a
                        v-for="notification in priorityItems.slice(0, 4)"
                        :key="notification.id"
                        class="v8-priority-item"
                        :class="priorityCls(notification)"
                        @click.prevent="openNotification(notification)"
                    >
                        <div class="v8-priority-icon">
                            <BaseIcon :name="kindOf(notification).icon" :size="17" />
                        </div>
                        <div class="v8-priority-body">
                            <b>{{ notification.title }}</b>
                            <p v-if="notification.order_route" class="v8-priority-route">{{ notification.order_route }}</p>
                            <div class="v8-priority-meta">
                                <span
                                    v-if="notification.order_status_label"
                                    class="v8-priority-status"
                                    :class="`v8-priority-status--${statusTone(notification)}`"
                                >
                                    {{ notification.order_status_label }}
                                </span>
                                <span class="v8-priority-time">{{ formatTime(notification.created_at) }}</span>
                            </div>
                            <!-- 요금 제안 — 처리는 액션 센터에서 -->
                            <div v-if="notification.offer_id" class="v8-offer-actions" @click.stop>
                                <span v-if="notification.offer_amount" class="v8-offer-actions__amount">
                                    {{ formatWon(notification.offer_amount) }}
                                </span>
                                <button type="button" class="v8-offer-btn v8-offer-btn--link" @click="goActions">
                                    처리할 일에서 처리
                                </button>
                            </div>
                        </div>
                        <div v-if="!notification.offer_id" class="v8-arrow"><BaseIcon name="arrow-forward" :size="14" /></div>
                    </a>

                    <div class="v8-legend">
                        <UiChip variant="yellow">요금 · 금액</UiChip>
                        <UiChip variant="red">취소 · 거절 · 문제</UiChip>
                        <UiChip variant="green">완료 · 정산 · 정상</UiChip>
                        <UiChip variant="blue">채팅 · 요청</UiChip>
                        <UiChip variant="purple">항공편</UiChip>
                    </div>

                    <button type="button" class="v8-priority-go" @click="goActions">
                        처리할 일(운행 승인 · 제안 · 요청)로 이동 →
                    </button>
                </UiCard>

                <!-- 필터 탭 -->
                <div class="v8-filter">
                    <button type="button" :class="{ active: filter === 'all' }" @click="filter = 'all'">전체</button>
                    <button type="button" :class="{ active: filter === 'request' }" @click="filter = 'request'">요청 {{ groupCount('request') }}</button>
                    <button type="button" :class="{ active: filter === 'complete' }" @click="filter = 'complete'">완료 {{ groupCount('complete') }}</button>
                    <button type="button" :class="{ active: filter === 'chat' }" @click="filter = 'chat'">채팅 {{ groupCount('chat') }}</button>
                    <button type="button" :class="{ active: filter === 'system' }" @click="filter = 'system'">시스템 {{ groupCount('system') }}</button>
                </div>

                <!-- 모두 읽음 -->
                <div class="v8-toolbar">
                    <span>최근 알림</span>
                    <button
                        v-if="store.unreadCount > 0"
                        type="button"
                        class="v8-toolbar__readall"
                        @click="store.markAllRead()"
                    >
                        모두 읽음
                    </button>
                </div>

                <EmptyState
                    v-if="filteredItems.length === 0"
                    icon="bell"
                    :title="filter === 'all' ? '알림이 없습니다' : '해당 알림이 없습니다'"
                    hint="매칭·운행·채팅 소식이 도착하면 여기 표시돼요"
                />

                <!-- 날짜 그룹 목록 — 오늘만 펼침, 어제·이전 날짜는 접힘 -->
                <template v-for="group in dayGroups" :key="group.label">
                    <button type="button" class="v8-day v8-day--toggle" @click="toggleDay(group.label)">
                        <span class="v8-day__label">{{ group.label }}</span>
                        <span class="v8-day__count">{{ group.items.length }}건</span>
                        <span class="v8-day__arrow">{{ isExpanded(group.label) ? '▾' : '▸' }}</span>
                    </button>
                    <UiCard v-if="isExpanded(group.label)" tone="tint" :padded="false" class="v8-list">
                        <UiListRow
                            v-for="notification in group.items"
                            :key="notification.id"
                            :tag="notification.offer_id ? 'div' : 'a'"
                            :dot="!notification.read"
                            @click.prevent="openNotification(notification)"
                        >
                            <template #icon>
                                <div class="v8-icon" :class="kindOf(notification).cls">
                                    <BaseIcon :name="kindOf(notification).icon" :size="16" />
                                </div>
                            </template>
                            <div class="v8-title">
                                {{ notification.title }}
                                <UiChip :variant="tagVariant(notification)">{{ kindOf(notification).tag }}</UiChip>
                            </div>
                            <div class="v8-desc">{{ notification.message }}</div>
                            <div v-if="notification.offer_id" class="v8-offer-inline" @click.stop>
                                <span class="v8-offer-inline__amount">{{ formatWon(notification.offer_amount) }} 제안</span>
                                <button type="button" class="v8-offer-inline__btn v8-offer-inline__btn--link" @click="goActions">
                                    처리할 일에서 처리
                                </button>
                            </div>
                            <div class="v8-time">{{ formatTime(notification.created_at) }}</div>
                        </UiListRow>
                    </UiCard>
                </template>
            </template>

            <EmptyState
                v-else
                icon="bell"
                title="알림이 없습니다"
                hint="매칭·운행·채팅 소식이 도착하면 여기 표시돼요"
            />
            </template>
    </div>
</template>

<style scoped>
/* ── 로딩 스켈레톤 ── */
.alerts-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.alerts-skeleton__card {
    padding: 14px 15px;
}

.alerts-skeleton__row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.alerts-skeleton__lines {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

/* 알림센터 — v8 디자인 (상황별 컬러) */
.alerts-page {
    width: 100%;
    max-width: 880px;
    margin: 0 auto;
    padding: 4px 20px 24px;
}

/* 모바일 — 홈·더보기와 동일하게 좌우 여백 없이 화면 폭을 꽉 채운다 */
@media (max-width: 480px) {
    .alerts-page {
        width: calc(100% + 40px);
        margin: 0 -20px;
        padding: 4px 14px 24px;
        max-width: none;
    }
}

.v8-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
}
.v8-head__title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
}
.v8-head__sub {
    margin-top: 3px;
    font-size: 12px;
    color: var(--text-muted);
}
.v8-total {
    flex-shrink: 0;
    padding: 6px 10px;
    border-radius: 9px;
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 25%, transparent);
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
}

/* 지금 처리할 알림 */
.v8-priority {
    margin-top: 16px;
    padding: 14px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}
html.dark .v8-priority {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}
.v8-priority-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.v8-priority-head b {
    font-size: 13px;
    font-weight: 800;
}
.v8-priority-head span {
    padding: 4px 8px;
    border-radius: 7px;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 22%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 700;
}
.v8-priority-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    padding: 11px;
    border-radius: 12px;
    background: #f0f7f3;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}
.v8-priority-item.success {
    background: #e9f6ef;
    border: 1px solid color-mix(in srgb, var(--brand) 40%, transparent);
}
.v8-priority-item.warn {
    background: #fbf3e2;
    border: 1px solid rgba(217, 141, 0, 0.35);
}
.v8-priority-item.danger {
    background: #fdeeee;
    border: 1px solid rgba(229, 72, 77, 0.35);
}
.v8-priority-item.price {
    background: #fbf5e3;
    border: 1px solid rgba(217, 141, 0, 0.35);
}
html.dark .v8-priority-item {
    background: #1e2622;
}
html.dark .v8-priority-item.success {
    background: #202b26;
    border: 1px solid color-mix(in srgb, var(--brand) 40%, transparent);
}
html.dark .v8-priority-item.warn {
    background: #29251c;
    border: 1px solid rgba(242, 184, 75, 0.4);
}
html.dark .v8-priority-item.danger {
    background: #291c1e;
    border: 1px solid rgba(240, 107, 107, 0.4);
}
html.dark .v8-priority-item.price {
    background: #29251b;
    border: 1px solid rgba(242, 184, 75, 0.4);
}
.v8-priority-item.success .v8-priority-icon {
    background: var(--brand);
    color: #07120e;
}
.v8-priority-item.warn .v8-priority-icon {
    background: #f2b84b;
    color: #171208;
}
.v8-priority-item.danger .v8-priority-icon {
    background: #f06b6b;
    color: #180707;
}
.v8-priority-item.price .v8-priority-icon {
    background: #f2b84b;
    color: #171208;
}
.v8-priority-icon {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    border-radius: 10px;
    background: var(--brand);
    color: #07120e;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 15px;
}
.v8-priority-body {
    flex: 1;
    min-width: 0;
}
.v8-priority-body b {
    font-size: 11px;
    font-weight: 800;
}
.v8-priority-route {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* 진행 상태 배지 + 시간 */
.v8-priority-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}
.v8-priority-status {
    display: inline-flex;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
}
.v8-priority-time {
    font-size: 9px;
    color: var(--text-muted);
    opacity: 0.75;
    white-space: nowrap;
}
.v8-priority-status--warn { background: rgba(217, 141, 0, 0.14); color: #9a6a00; }
html.dark .v8-priority-status--warn { color: #ffd071; }
.v8-priority-status--success { background: rgba(14, 157, 108, 0.12); color: #0d8a63; }
html.dark .v8-priority-status--success { color: #70e8be; }
.v8-priority-status--danger { background: rgba(229, 72, 77, 0.12); color: #c03a3f; }
html.dark .v8-priority-status--danger { color: #ff8a8a; }
.v8-priority-status--info { background: rgba(77, 143, 232, 0.12); color: #1d5fd0; }
html.dark .v8-priority-status--info { color: #79a8ff; }
.v8-priority-status--ride { background: rgba(45, 212, 191, 0.12); color: #0e9d8d; }
html.dark .v8-priority-status--ride { color: #5de4d2; }
.v8-priority-status--muted { background: color-mix(in srgb, var(--text-muted) 14%, transparent); color: var(--text-muted); }
.v8-arrow {
    color: var(--brand);
    font-size: 16px;
}

/* 범례 */
.v8-legend {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 12px;
}

/* 필터 */
.v8-filter {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    margin: 15px 0 12px;
    padding-bottom: 2px;
}
.v8-filter button {
    flex: none;
    padding: 8px 12px;
    border-radius: 9px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}
.v8-filter button.active {
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    border-color: color-mix(in srgb, var(--brand) 33%, transparent);
    color: var(--brand);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 20%, transparent);
}

/* 툴바 */
.v8-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 4px 2px 8px;
}
.v8-toolbar span {
    font-size: 11px;
    color: var(--text-muted);
}
.v8-toolbar__readall {
    border: 0;
    background: transparent;
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

/* 날짜 그룹 — 탭하면 펼침/접힘 */
.v8-day {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    font-size: 10px;
    font-weight: 800;
    color: var(--text-muted);
    margin: 14px 0 7px;
    padding: 6px 4px;
    border: 0;
    border-radius: 9px;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
}
.v8-day:hover {
    background: color-mix(in srgb, var(--brand) 5%, transparent);
}
.v8-day__count {
    padding: 1px 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--text-muted) 14%, transparent);
    font-size: 9px;
    font-weight: 700;
}
.v8-day__arrow {
    margin-left: auto;
    color: var(--text-muted);
    font-size: 11px;
    transition: transform 0.15s ease;
}
.v8-list {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 15px;
    overflow: hidden;
}

/* 알림 항목 */
.v8-noti {
    display: flex;
    gap: 10px;
    padding: 12px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
    cursor: pointer;
    transition: background 0.12s ease;
}
.v8-noti:last-child {
    border-bottom: 0;
}
.v8-noti:hover {
    background: color-mix(in srgb, var(--brand) 4%, transparent);
}
.v8-icon {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
}
.v8-icon.req { background: rgba(14, 157, 108, 0.1); color: #0d8a63; border: 1px solid rgba(14, 157, 108, 0.3); }
.v8-icon.warn { background: rgba(242, 169, 59, 0.14); color: #9a6a00; border: 1px solid rgba(217, 141, 0, 0.38); }
.v8-icon.cancel { background: rgba(229, 72, 77, 0.1); color: #c03a3f; border: 1px solid rgba(229, 72, 77, 0.35); }
.v8-icon.price { background: rgba(242, 184, 75, 0.14); color: #9a6a00; border: 1px solid rgba(217, 141, 0, 0.38); }
.v8-icon.success { background: rgba(14, 157, 108, 0.1); color: #0e9a6e; border: 1px solid rgba(14, 157, 108, 0.32); }
.v8-icon.chat { background: rgba(77, 143, 232, 0.12); color: #1d5fd0; border: 1px solid rgba(77, 143, 232, 0.32); }
.v8-icon.flight { background: rgba(139, 120, 232, 0.12); color: #6b46d6; border: 1px solid rgba(139, 120, 232, 0.32); }
.v8-icon.teal { background: rgba(45, 212, 191, 0.12); color: #0e9d8d; border: 1px solid rgba(45, 212, 191, 0.32); }
.v8-icon.system { background: rgba(140, 151, 148, 0.12); color: #5c6670; border: 1px solid rgba(115, 125, 121, 0.32); }

html.dark .v8-icon.req { background: rgba(99, 226, 183, 0.08); color: var(--brand); border: 1px solid rgba(99, 226, 183, 0.3); }
html.dark .v8-icon.warn { background: rgba(242, 169, 59, 0.12); color: #ffd071; border: 1px solid rgba(242, 184, 75, 0.4); }
html.dark .v8-icon.cancel { background: rgba(224, 91, 91, 0.1); color: #ff8a8a; border: 1px solid rgba(224, 91, 91, 0.4); }
html.dark .v8-icon.price { background: rgba(242, 184, 75, 0.1); color: #ffd071; border: 1px solid rgba(242, 184, 75, 0.4); }
html.dark .v8-icon.success { background: rgba(99, 226, 183, 0.09); color: #70e8be; border: 1px solid rgba(99, 226, 183, 0.33); }
html.dark .v8-icon.chat { background: rgba(44, 111, 255, 0.13); color: #79a8ff; border: 1px solid rgba(77, 143, 232, 0.33); }
html.dark .v8-icon.flight { background: rgba(155, 123, 255, 0.12); color: #b9a7ff; border: 1px solid rgba(139, 120, 232, 0.33); }
html.dark .v8-icon.teal { background: rgba(45, 212, 191, 0.1); color: #5de4d2; border: 1px solid rgba(45, 212, 191, 0.33); }
html.dark .v8-icon.system { background: rgba(140, 151, 148, 0.1); color: #b6c0bc; border: 1px solid rgba(115, 125, 121, 0.33); }

.v8-title {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 800;
    color: var(--text);
}
.v8-desc {
    font-size: 10px;
    color: var(--text-muted);
    line-height: 1.45;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.v8-time {
    font-size: 9px;
    color: var(--text-muted);
    opacity: 0.75;
    margin-top: 4px;
}

/* 요금 제안 — 액션 센터로 안내 (우선 알림 영역) */
.v8-offer-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
}
.v8-offer-actions__amount {
    font-size: 12px;
    font-weight: 800;
    color: var(--brand);
}
.v8-offer-btn {
    padding: 5px 12px;
    border-radius: 999px;
    border: 0;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.12s ease, transform 0.1s ease;
}
.v8-offer-btn:disabled {
    opacity: 0.5;
    cursor: default;
}
.v8-offer-btn:not(:disabled):active {
    transform: scale(0.96);
}
.v8-offer-btn--link {
    border: 1px solid color-mix(in srgb, var(--brand) 40%, transparent);
    background: transparent;
    color: var(--brand);
}

/* 요금 제안 — 목록 행 인라인 액션 */
.v8-offer-inline {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}
.v8-offer-inline__amount {
    font-size: 11px;
    font-weight: 800;
    color: var(--brand);
}
.v8-offer-inline__btn {
    padding: 4px 10px;
    border-radius: 999px;
    border: 0;
    font-family: inherit;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
}
.v8-offer-inline__btn:disabled {
    opacity: 0.5;
    cursor: default;
}
.v8-offer-inline__btn--link {
    border: 1px solid color-mix(in srgb, var(--brand) 40%, transparent);
    background: transparent;
    color: var(--brand);
}

/* 제안 관리 허브 이동 */
.v8-priority-go {
    width: 100%;
    margin-top: 10px;
    padding: 9px;
    border: 1px dashed color-mix(in srgb, var(--brand) 40%, transparent);
    border-radius: 10px;
    background: transparent;
    color: var(--brand);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.12s ease;
}
.v8-priority-go:hover {
    background: color-mix(in srgb, var(--brand) 8%, transparent);
}
</style>
