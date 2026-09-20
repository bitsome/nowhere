<script setup>
import { computed, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useRouter } from 'vue-router';
import { apiOrders } from '../../api/orders';
import { getApiErrorMessage } from '../../api/client';
import { statusColorVar } from '../../utils/colors';
import { koreanHolidayName } from '../../utils/koreanHolidays';
import OrderCardSkeleton from '../../components/orders/OrderCardSkeleton.vue';
import SetGroupCard from '../../components/orders/SetGroupCard.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import BaseIcon from '../../components/common/BaseIcon.vue';

const message = useMessage();
const router = useRouter();

// 히스토리 — 완전히 끝난 운행만 탭으로 구분한다 (전체/완료/정산/취소)
const STATUS_TABS = [
    { label: '전체', value: '전체' },
    { label: '완료', value: '완료' },
    { label: '정산', value: '정산완료' },
    { label: '취소', value: '취소' },
];

const listTab = ref('전체');
const orders = ref([]);
const loading = ref(false);
const page = ref(1);
const pagination = ref(null);

// 보기 모드 — 목록 / 캘린더
const VIEW_MODES = [
    { label: '목록', value: 'list' },
    { label: '캘린더', value: 'calendar' },
];
const viewMode = ref('list');

// 묶음 단위: 'day'(일별) / 'month'(월별) / 'year'(연별)
const groupUnit = ref('day');
const GROUP_UNITS = [
    { label: '일별', value: 'day' },
    { label: '월별', value: 'month' },
    { label: '연별', value: 'year' },
];

const setGroupUnit = (unit) => {
    groupUnit.value = unit;
};

// 날짜별 그룹 열림/펼침 — 기본 전부 펼침
const collapsedGroups = ref(new Set());

const toggleGroup = (key) => {
    const next = new Set(collapsedGroups.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    collapsedGroups.value = next;
};

const setRows = computedFilter('set');
const singleRows = computedFilter('!set');

function computedFilter(kind) {
    return computed(() => {
        if (kind === 'set') return orders.value.filter((o) => o.kind === 'set');
        return orders.value.filter((o) => o.kind !== 'set');
    });
}

// 금액 추출 — expected_revenue/amount_value 숫자 우선, 없으면 포맷된 amount 문자열에서 파싱
const orderAmount = (o) => {
    const raw = o.expected_revenue ?? o.amount_value;

    if (raw !== null && raw !== undefined) {
        return Number(raw) || 0;
    }

    const digits = String(o.amount ?? '').replace(/[^\d]/g, '');

    return digits ? Number(digits) : 0;
};

// 완료/취소 전체 금액 합계 (단건 운행 기준)
const totalAmount = computed(() => singleRows.value.reduce((sum, o) => sum + orderAmount(o), 0));

// 건당 평균 금액
const avgAmount = computed(() => (singleRows.value.length ? Math.round(totalAmount.value / singleRows.value.length) : 0));

const amountLabel = computed(() => ({
    '완료': '완료 수익',
    '정산완료': '정산 금액',
    '취소': '취소 금액',
    '전체': '총 금액',
}[listTab.value] ?? '총 금액'));

// 건수 라벨 — 탭과 동일한 사용자 표기 사용 (내부 상태값 '정산완료'가 그대로 노출되지 않게 한다)
const countLabel = computed(() => ({
    '완료': '완료',
    '정산완료': '정산',
    '취소': '취소',
    '전체': '운행',
}[listTab.value] ?? '운행'));

// ── 날짜별 그룹핑 — 일별/월별/연별 ──
const dateGroups = computed(() => {
    const map = new Map();

    for (const row of singleRows.value) {
        const raw = row.sortDate || '';
        let key = raw
            ? groupUnit.value === 'month' ? raw.slice(0, 7) : groupUnit.value === 'year' ? raw.slice(0, 4) : raw
            : '__미정__';

        if (!map.has(key)) {
            map.set(key, { key, rows: [] });
        }

        map.get(key).rows.push(row);
    }

    return [...map.values()].sort((a, b) => {
        // 날짜 있는 그룹 먼저, 최신순(내림차순); 날짜 미정은 마지막
        if (a.key === '__미정__') return 1;
        if (b.key === '__미정__') return -1;

        return b.key.localeCompare(a.key);
    });
});

// 날짜 헤더 라벨 — 오늘/내일 강조 + 한국 공휴일·명절 표시
const dateLabel = (sortDate) => {
    if (!sortDate) {
        return '날짜 미정';
    }

    const [y, m, d] = sortDate.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    const weekdays = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];
    let label = `${m}월 ${d}일 (${weekdays[date.getDay()]})`;

    const now = new Date();
    const isToday = y === now.getFullYear() && m === now.getMonth() + 1 && d === now.getDate();
    const tomorrow = new Date(now);
    tomorrow.setDate(now.getDate() + 1);
    const isTomorrow = y === tomorrow.getFullYear() && m === tomorrow.getMonth() + 1 && d === tomorrow.getDate();

    if (isToday) label = `오늘 · ${label}`;
    if (isTomorrow) label = `내일 · ${label}`;

    const holiday = koreanHolidayName(sortDate);

    if (holiday) {
        label += ` · ${holiday}`;
    }

    return label;
};

// 그룹 헤더 라벨 — 단위별 (일별: 날짜+공휴일, 월별: 2026년 9월, 연별: 2026년)
const groupLabel = (key) => {
    if (groupUnit.value === 'month') {
        const [y, m] = key.split('-').map(Number);

        return `${y}년 ${m}월`;
    }

    if (groupUnit.value === 'year') {
        return `${key}년`;
    }

    return dateLabel(key);
};

// ── 캘린더 보기 — 월 그리드 + 날짜별 운행 ──
const todayIso = (() => {
    const n = new Date();

    return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`;
})();

const calCursor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const selectedDate = ref(todayIso);

const calTitle = computed(() => `${calCursor.value.getFullYear()}년 ${calCursor.value.getMonth() + 1}월`);

// 월 그리드 셀 — 앞/뒤 빈(이월) 셀 포함, 월요일 시작 6주
const calDays = computed(() => {
    const y = calCursor.value.getFullYear();
    const m = calCursor.value.getMonth();
    // 월요일 시작 — 일요일(0)이 마지막 열이 되도록 앞 여유 계산
    const leading = (new Date(y, m, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const cells = [];

    for (let i = leading - 1; i >= 0; i--) {
        cells.push({ date: '', day: new Date(y, m, -i).getDate(), muted: true });
    }
    for (let day = 1; day <= daysInMonth; day++) {
        cells.push({ date: `${y}-${String(m + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`, day, muted: false });
    }
    for (let lead = 1; cells.length < 42; lead++) {
        cells.push({ date: '', day: lead, muted: true });
    }

    return cells;
});

// 운행이 있는 날짜 (점 표시)
const driveDates = computed(() => new Set(singleRows.value.map((o) => o.sortDate).filter(Boolean)));

const selectedDateRows = computed(() => singleRows.value.filter((o) => o.sortDate === selectedDate.value));

const selectedDateLabel = computed(() => {
    if (!selectedDate.value) {
        return '';
    }
    const [, m, d] = selectedDate.value.split('-').map(Number);

    return `${m}월 ${d}일 운행`;
});

const prevMonth = () => {
    calCursor.value = new Date(calCursor.value.getFullYear(), calCursor.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    calCursor.value = new Date(calCursor.value.getFullYear(), calCursor.value.getMonth() + 1, 1);
};

const selectDay = (date) => {
    if (date) {
        selectedDate.value = date;
    }
};

const switchViewMode = (mode) => {
    viewMode.value = mode;
};

// 샌딩/랜딩 구분 — serviceIcon(sending/landing/pickup) 기준.
// service_type 누락 데이터는 방향(공항 포함 여부)으로 판별한다.
const serviceTypeLabel = (row) => {
    const labels = { sending: '샌딩', point: '시내', landing: '랜딩', pickup: '픽업' };

    if (labels[row.serviceIcon]) {
        return labels[row.serviceIcon];
    }

    const [pickup, dropoff] = (row.route ?? '').split('→').map((part) => part.trim());

    if (pickup?.includes('공항')) {
        return '랜딩';
    }

    if (dropoff?.includes('공항')) {
        return '샌딩';
    }

    return '운행';
};

// 상태 배지 색상 — 중앙 팔레트(utils/colors.js) 참조
const statusColor = (status) => statusColorVar[status] ?? 'var(--status-draft)';

// 상태 배지 글자색 — 밝은 배경(완료·공개 등)에서는 어두운 글자로 대비 확보
const statusTextColor = (status) =>
    ['completed', 'trading', 'published', 'driving', 'acceptance_pending'].includes(status)
        ? '#101418'
        : '#ffffff';

const openOrder = (order) => router.push({ name: 'order-detail', params: { id: order.id } });

const load = async () => {
    loading.value = true;

    try {
        const params = {
            scope: 'mine',
            source: 'history', // 완전히 끝난 운행만 (완료/정산/취소)
            tab: listTab.value,
            per_page: 100,
            page: page.value,
            sort: 'date_desc', // 최근 운행 먼저
        };

        const { data } = await apiOrders(params);

        orders.value = data.data ?? [];
        pagination.value = data.meta ?? null;
    } catch (e) {
        message.error(getApiErrorMessage(e, '운행 기록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const loadFirstPage = () => {
    page.value = 1;
    load();
};

const handlePage = (p) => {
    page.value = p;
    load();
};

const switchTab = (tab) => {
    listTab.value = tab;
    loadFirstPage();
};

onMounted(load);
</script>

<template>
    <div class="history-page page-shell">
        <div class="page-head">
            <div>
                <p class="page-head__desc">완전히 끝난 운행만 기록합니다. 완료·정산·취소 상태로 구분해 보세요. 진행 중인 운행은 내 운행에서 관리합니다.</p>
            </div>
        </div>

        <!-- 상태 탭 — 내 마켓과 동일한 칩 스타일, 우측에 새로고침 -->
        <div class="status-tabs">
            <button
                v-for="tab in STATUS_TABS"
                :key="tab.value"
                type="button"
                class="status-tabs__btn"
                :class="{ 'status-tabs__btn--active': listTab === tab.value }"
                @click="switchTab(tab.value)"
            >
                {{ tab.label }}
            </button>
            <button
                type="button"
                class="status-tabs__refresh"
                aria-label="새로고침"
                title="새로고침"
                @click="loadFirstPage"
            >
                <BaseIcon name="refresh" :size="15" />
            </button>
        </div>

        <!-- 목록/캘린더 보기 전환 -->
        <div class="status-tabs">
            <button
                v-for="mode in VIEW_MODES"
                :key="mode.value"
                type="button"
                class="status-tabs__btn"
                :class="{ 'status-tabs__btn--active': viewMode === mode.value }"
                @click="switchViewMode(mode.value)"
            >
                {{ mode.label }}
            </button>
        </div>

        <!-- 보기 단위 전환 — 일별/월별/연별 (목록 모드에서만) -->
        <div v-if="viewMode === 'list'" class="status-tabs">
            <button
                v-for="unit in GROUP_UNITS"
                :key="unit.value"
                type="button"
                class="status-tabs__btn"
                :class="{ 'status-tabs__btn--active': groupUnit === unit.value }"
                @click="setGroupUnit(unit.value)"
            >
                {{ unit.label }}
            </button>
        </div>

        <div v-if="!loading && singleRows.length" class="history-hero">
            <div class="history-hero__cell">
                <span>{{ amountLabel }}</span>
                <strong>{{ totalAmount.toLocaleString() }}원</strong>
            </div>
            <div class="history-hero__cell">
                <span>총 {{ countLabel }} 건수</span>
                <strong>{{ pagination?.total ?? singleRows.length }}건</strong>
            </div>
            <div class="history-hero__cell">
                <span>건당 평균</span>
                <strong>{{ avgAmount.toLocaleString() }}원</strong>
            </div>
        </div>

        <div v-if="loading" class="my-order-list">
            <OrderCardSkeleton v-for="n in 5" :key="n" />
        </div>
        <EmptyState
            v-else-if="!orders.length"
            icon="truck"
            title="운행 기록이 없습니다"
            hint="완료·정산·취소된 운행이 여기에 표시됩니다"
        />
        <div v-else-if="viewMode === 'calendar'" class="cal">
            <div class="cal__head">
                <button type="button" class="cal__nav" aria-label="이전 달" @click="prevMonth"><BaseIcon name="arrow-back" :size="16" /></button>
                <b>{{ calTitle }}</b>
                <button type="button" class="cal__nav" aria-label="다음 달" @click="nextMonth"><BaseIcon name="arrow-forward" :size="16" /></button>
            </div>
            <div class="cal__grid">
                <div v-for="dow in ['월', '화', '수', '목', '금', '토', '일']" :key="dow" class="cal__dow">{{ dow }}</div>
                <button
                    v-for="(cell, index) in calDays"
                    :key="index"
                    type="button"
                    class="cal__day"
                    :class="{
                        'cal__day--muted': cell.muted,
                        'cal__day--today': cell.date === todayIso,
                        'cal__day--selected': cell.date === selectedDate,
                    }"
                    :disabled="!cell.date"
                    @click="selectDay(cell.date)"
                >
                    {{ cell.day }}
                    <span v-if="driveDates.has(cell.date)" class="cal__dot" />
                </button>
            </div>
            <div class="cal__section-title">
                <b>{{ selectedDateLabel }}</b>
                <span class="cal__count">{{ selectedDateRows.length }}건</span>
            </div>
            <div v-if="selectedDateRows.length" class="my-order-list">
                <button
                    v-for="order in selectedDateRows"
                    :key="order.key"
                    type="button"
                    class="history-card"
                    @click="openOrder(order)"
                >
                    <span class="history-card__type">{{ serviceTypeLabel(order) }}</span>
                    <span class="history-card__when">{{ order.time }}</span>
                    <strong class="history-card__route">{{ order.route }}</strong>
                    <span class="history-card__status" :style="{ background: statusColor(order.status), borderColor: statusColor(order.status), color: statusTextColor(order.status) }">
                        {{ order.statusLabel }}
                    </span>
                    <span class="history-card__amount">{{ order.amount }}</span>
                </button>
            </div>
            <EmptyState
                v-else
                icon="truck"
                title="이 날짜에는 운행이 없습니다"
                hint="다른 날짜를 선택해 보세요"
            />
        </div>
        <div v-else class="my-order-list">
            <SetGroupCard v-for="order in setRows" :key="order.key" :set="order" />
            <div v-for="group in dateGroups" :key="group.key" class="schedule-group">
                <button
                    type="button"
                    class="schedule-group__head"
                    :aria-expanded="!collapsedGroups.has(group.key)"
                    @click="toggleGroup(group.key)"
                >
                    <span class="schedule-group__date">{{ groupLabel(group.key) }}</span>
                    <span class="schedule-group__right">
                        <span class="schedule-group__count">{{ group.rows.length }}건</span>
                        <BaseIcon
                            class="schedule-group__chevron"
                            :class="{ 'schedule-group__chevron--collapsed': collapsedGroups.has(group.key) }"
                            name="chevron-down"
                            :size="16"
                        />
                    </span>
                </button>
                <div v-show="!collapsedGroups.has(group.key)" class="schedule-group__body">
                    <button
                        v-for="order in group.rows"
                        :key="order.key"
                        type="button"
                        class="history-card"
                        @click="openOrder(order)"
                    >
                        <span class="history-card__type">{{ serviceTypeLabel(order) }}</span>
                        <span class="history-card__when">{{ order.time }}</span>
                        <strong class="history-card__route">{{ order.route }}</strong>
                        <span class="history-card__status" :style="{ background: statusColor(order.status), borderColor: statusColor(order.status), color: statusTextColor(order.status) }">
                            {{ order.statusLabel }}
                        </span>
                        <span class="history-card__amount">{{ order.amount }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pagination && pagination.last_page > 1" class="create-pagination">
            <n-pagination
                :page="page"
                :page-size="pagination.per_page"
                :item-count="pagination.total"
                @update:page="handlePage"
            />
        </div>
    </div>
</template>

<style scoped>
.history-page {
    min-height: 200px;
}

/* 상태 탭 — 내 마켓과 동일한 칩 스타일 */
.status-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: var(--chips-gap);
}

/* 탭 아래 하위 탭 행(보기 모드·단위 전환 등) — 위로 끌어올려 여백을 작게 (내마켓 하위 탭과 동일) */
.status-tabs + .status-tabs {
    margin-top: -4px;
}

.status-tabs__btn {
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

.status-tabs__btn--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

/* 상태 탭 우측 — 새로고침 (내 마켓과 동일한 스타일) */
.status-tabs__refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
    margin-left: auto;
}

/* hover — 데스크톱에서만. 터치 기기는 탭 후 남는 포커스로 hover가 고정돼 '선택된 것처럼' 보이므로 제외 */
@media (hover: hover) {
    .status-tabs__refresh:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}

/* 요약 히어로 — 금액/건수/평균 3칸 */
.history-hero {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.history-hero__cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.history-hero__cell span {
    color: var(--text-muted);
    font-size: 11px;
}

.history-hero__cell strong {
    color: var(--brand);
    font-size: 12px;
    font-weight: 800;
}

.my-order-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

/* 히스토리 카드 — 홈 강력추천과 동일한 컴팩트 행 카드 (시간 우선 + 샌딩/랜딩 구분) */
.history-card {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 12px var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
    font-family: inherit;
    font-size: 12px;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.12s ease;
}
.history-card:hover {
    border-color: var(--brand);
}
.history-card__type {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}
.history-card__when {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
}
.history-card__route {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text);
    font-weight: 400;
}
.history-card__status {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    color: #ffffff;
    font-size: 10px;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.history-card__amount {
    flex-shrink: 0;
    color: var(--brand);
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

/* 날짜별 그룹 — 일별/월별/연별 헤더 + 아코디언 */
.schedule-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.schedule-group__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 4px 0;
    border: 0;
    background: transparent;
    font-family: inherit;
    cursor: pointer;
}

.schedule-group__date {
    font-size: 12px;
    font-weight: 800;
    color: var(--text);
}

.schedule-group__right {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.schedule-group__count {
    color: var(--text-muted);
    font-size: 10px;
}

.schedule-group__chevron {
    transition: transform 0.15s ease;
}

.schedule-group__chevron--collapsed {
    transform: rotate(-90deg);
}

.schedule-group__body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* ── 운행 캘린더 — 내 운행 스케줄과 동일한 월 그리드 ── */
.cal {
    margin-bottom: 16px;
}
.cal__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 2px 12px;
    font-size: 11px;
}
.cal__head b {
    font-weight: 800;
    letter-spacing: -0.02em;
}
.cal__nav {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--brand);
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}
.cal__nav:hover {
    border-color: var(--brand);
    background: var(--brand-soft);
}
.cal__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 12px;
}
.cal__dow {
    text-align: center;
    color: var(--text-muted);
    font-size: 11px;
    padding-bottom: 4px;
}
.cal__day {
    position: relative;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text);
    font-size: 11px;
    cursor: pointer;
}
.cal__day--muted {
    color: var(--text-muted);
    background: transparent;
    border-color: transparent;
    cursor: default;
}
.cal__day--today {
    background: var(--brand);
    border-color: var(--brand);
    /* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝아 흰 글자 대비 약함 — 앱 표준 #07120e를 두 모드 공통 사용 */
    color: #07120e;
    font-weight: 800;
}
.cal__day--selected:not(.cal__day--today) {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
    font-weight: 700;
}
.cal__dot {
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--brand);
}
.cal__day--today .cal__dot {
    background: #07120e;
}
.cal__section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.cal__section-title b {
    font-size: 11px;
    font-weight: 800;
}
.cal__count {
    font-size: 11px;
    color: var(--text-muted);
}

.create-pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
</style>
