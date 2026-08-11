<script setup>
import { computed, ref } from 'vue';
import BaseIcon from '../../shared/components/Icon/BaseIcon.vue';

/**
 * 오더 워크스페이스 카드형 리스트.
 *
 * 기사와 운영자가 한눈에 판단할 수 있도록
 * 노선 / 픽업일시(오늘 강조) / 차량 / 금액 / 상태만 카드로 보여준다.
 */
const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    perPage: {
        type: Number,
        default: 10,
    },
    variant: {
        type: String,
        default: 'grid',
    },
});

const currentPage = ref(1);
const expandedKeys = ref(new Set());

const pagedRows = computed(() => {
    const start = (currentPage.value - 1) * props.perPage;

    return props.rows.slice(start, start + props.perPage);
});

const totalPages = computed(() => Math.max(1, Math.ceil(props.rows.length / props.perPage)));

const isExpanded = (key) => expandedKeys.value.has(key);

const toggleExpand = (key) => {
    const next = new Set(expandedKeys.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expandedKeys.value = next;
};

const goToOrder = (row) => {
    if (row.showUrl) {
        window.location.href = row.showUrl;
    }
};

const statusClass = (label) => ({
    'status-badge--completed': label === '완료',
    'status-badge--settled': label === '정산',
    'status-badge--active': label === '운행중',
});

const cardDate = (row) => (row.isToday ? '오늘' : row.date);

const cardVehicle = (row) => row.vehicle || row.orders?.[0]?.vehicle || '-';

const cardPassenger = (row) => row.passengerCount || 0;

const VEHICLE_ICON_RULES = [
    { icon: 'bus', keywords: ['버스', 'bus'] },
    { icon: 'suv', keywords: ['싼타페', '모하비', '투싼', '스포티지', '쏘렌토', 'suv'] },
    { icon: 'van', keywords: ['스타렉스', '카고', '벤', 'van'] },
    { icon: 'sedan', keywords: ['소나타', '그랜저', '아반떼', '세단', 'sedan'] },
    { icon: 'minivan', keywords: ['카니발', '스타리아', '승합', 'minivan'] },
];

const cardVehicleIcon = (row) => {
    const vehicle = String(cardVehicle(row)).toLowerCase();
    const matched = VEHICLE_ICON_RULES.find((rule) => rule.keywords.some((keyword) => vehicle.includes(keyword)));

    return matched ? matched.icon : 'minivan';
};

const cardRoute = (row) => {
    if (row.kind === 'set') {
        return row.routes?.[0]?.route || row.route || '-';
    }

    return row.route || '-';
};

const cardAmount = (row) => (row.kind === 'set' ? row.totalAmount : row.amount);
</script>

<template>
    <div>
        <div
            v-if="variant === 'grid'"
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            data-order-card-grid
        >
            <article
                v-for="row in pagedRows"
                :key="row.key"
                class="group rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 transition hover:border-[#c3c3c3] hover:bg-[#f2f2f2] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:hover:border-[#3a3a3a] dark:hover:bg-[#202020]"
                :data-order-card="row.key"
                @click="goToOrder(row)"
            >
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center" :aria-hidden="true">
                        <span v-if="row.kind === 'set'">📦</span>
                        <BaseIcon
                            v-else
                            :name="row.serviceIcon || 'sending'"
                            :size="18"
                            class="text-gray-500 dark:text-gray-400"
                        />
                    </span>
                    <span v-if="row.kind === 'set'" class="meta-badge">셋트 ({{ row.count }})</span>
                    <span v-if="row.kind === 'set'" class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ row.name }}
                    </span>
                    <span v-else class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ row.serviceLabel }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="status-badge" :class="statusClass(row.statusLabel)">{{ row.statusLabel }}</span>
                    <button
                        v-if="row.kind === 'set'"
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-md border border-[#d8d8d8] text-gray-500 transition hover:bg-[#ececec] dark:border-[#2a2a2a] dark:text-gray-400 dark:hover:bg-[#262626]"
                        :aria-expanded="String(isExpanded(row.key))"
                        :title="isExpanded(row.key) ? '일정 접기' : '일정 펼치기'"
                        @click.stop="toggleExpand(row.key)"
                    >
                        <BaseIcon :name="isExpanded(row.key) ? 'chevron-up' : 'chevron-down'" :size="16" />
                    </button>
                </div>
            </div>

            <!-- 노선(좌) · 픽업 시간(우) -->
            <div class="mt-3 flex items-center justify-between gap-3">
                <p class="min-w-0 truncate text-base font-semibold text-gray-900 dark:text-gray-100" :title="cardRoute(row)">
                    {{ cardRoute(row) }}
                </p>
                <p class="flex shrink-0 items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                    <BaseIcon name="view" :size="14" class="text-gray-400 dark:text-gray-500" />
                    <span>{{ cardDate(row) }} {{ row.time }}</span>
                    <span v-if="row.kind === 'set' && row.count > 1" class="text-xs text-gray-400 dark:text-gray-500">
                        외 {{ row.count - 1 }}건
                    </span>
                </p>
            </div>

            <!-- 셋트 펼침: 멤버 일정 목록 -->
            <div
                v-if="row.kind === 'set' && isExpanded(row.key)"
                class="mt-3 space-y-1.5 border-t border-[#e5e5e5] pt-3 dark:border-[#262626]"
                data-order-card-set-detail
            >
                <div
                    v-for="(route, index) in row.routes"
                    :key="index"
                    class="flex items-center justify-between gap-3 text-sm"
                >
                    <span class="min-w-0 truncate text-gray-600 dark:text-gray-300">{{ route.route }}</span>
                    <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ route.time }}</span>
                </div>
            </div>

            <!-- 푸터: 금액(좌) · 상세보기(우) -->
            <div class="mt-3 flex items-center justify-between gap-3 border-t border-[#e5e5e5] pt-3 dark:border-[#262626]">
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ cardAmount(row) }}
                </p>
                <span class="flex items-center gap-1 text-sm font-medium text-gray-500 transition group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-gray-100">
                    상세보기
                    <BaseIcon name="chevron-right" :size="16" />
                </span>
            </div>
        </article>
        </div>

        <div
            v-else
            class="divide-y divide-[#e5e5e5] dark:divide-[#262626]"
            data-order-line-list
        >
            <button
                v-for="row in pagedRows"
                :key="row.key"
                type="button"
                class="block w-full px-2 py-3 text-left transition hover:bg-[#f2f2f2] dark:hover:bg-[#202020]"
                :data-order-card="row.key"
                @click="goToOrder(row)"
            >
                <!-- 1줄: 서비스(좌) · 금액(우) -->
                <div class="flex items-center justify-between gap-3">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center" :aria-hidden="true">
                            <span v-if="row.kind === 'set'">📦</span>
                            <BaseIcon
                                v-else
                                :name="row.serviceIcon || 'sending'"
                                :size="16"
                                class="text-gray-400 dark:text-gray-500"
                            />
                        </span>
                        <span class="truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ row.kind === 'set' ? '셋트' : row.serviceLabel }}
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ cardDate(row) }} {{ row.time }}
                        </span>
                    </span>
                    <span class="shrink-0 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ cardAmount(row) }}
                    </span>
                </div>
                <!-- 2줄: 노선 -->
                <p class="mt-1 line-clamp-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ cardRoute(row) }}
                </p>
                <!-- 3줄: 차량 · 인원(좌) · 상태(우) -->
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5">
                        <BaseIcon :name="cardVehicleIcon(row)" :size="14" />
                        <span>{{ cardVehicle(row) }}</span>
                    </span>
                    <span v-if="cardPassenger(row) > 0" class="flex items-center gap-1.5">
                        <BaseIcon name="user" :size="14" />
                        <span>{{ cardPassenger(row) }}명</span>
                    </span>
                    <span class="ml-auto status-badge" :class="statusClass(row.statusLabel)">{{ row.statusLabel }}</span>
                </div>
            </button>
        </div>

        <div
            v-if="pagedRows.length === 0"
            class="datatable-empty"
            data-order-card-empty
        >
            <BaseIcon name="search" :size="40" class="text-gray-400 dark:text-gray-500" />
            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">등록된 오더가 없습니다.</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">검색 조건에 맞는 오더가 없습니다.</p>
        </div>

        <nav
            v-if="totalPages > 1"
            class="mt-4 flex items-center justify-between border-t border-[#e5e5e5] pt-3 dark:border-[#262626]"
            aria-label="페이지네이션"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ currentPage }} / {{ totalPages }} 페이지
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="btn-secondary"
                    title="이전 페이지"
                    aria-label="이전 페이지"
                    :disabled="currentPage <= 1"
                    @click="currentPage -= 1"
                >
                    이전
                </button>
                <button
                    type="button"
                    class="btn-secondary"
                    title="다음 페이지"
                    aria-label="다음 페이지"
                    :disabled="currentPage >= totalPages"
                    @click="currentPage += 1"
                >
                    다음
                </button>
            </div>
        </nav>
    </div>
</template>
