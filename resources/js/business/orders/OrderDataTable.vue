<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BaseIcon from '../../shared/components/Icon/BaseIcon.vue';
import DataTable from '../../shared/components/DataTable/DataTable.vue';
import OrderTableCells from './OrderTableCells.vue';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    perPage: {
        type: Number,
        default: 10,
    },
});

const currentPage = ref(1);
const expandedGroupIds = ref(new Set());
const actionOpenGroupId = ref(null);

const columns = [
    { key: 'service', label: '구분', width: '13%' },
    { key: 'route', label: '노선', width: '23%' },
    { key: 'pickupDateTime', label: '픽업일시', width: '14%' },
    { key: 'vehicle', label: '차량', width: '14%' },
    { key: 'passenger', label: '인원', width: '8%', align: 'center' },
    { key: 'amount', label: '금액', width: '10%', align: 'right' },
    { key: 'status', label: '상태', width: '8%', align: 'center' },
    { key: 'actions', label: '액션', width: '10%', align: 'right' },
];

const pagedRows = computed(() => {
    const start = (currentPage.value - 1) * props.perPage;

    return props.rows.slice(start, start + props.perPage);
});

const isExpanded = (groupId) => expandedGroupIds.value.has(groupId);

const toggleGroup = (groupId) => {
    const next = new Set(expandedGroupIds.value);

    if (next.has(groupId)) {
        next.delete(groupId);
    } else {
        next.add(groupId);
    }

    expandedGroupIds.value = next;
};

const toggleGroupActions = (groupId) => {
    actionOpenGroupId.value = actionOpenGroupId.value === groupId ? null : groupId;
};

const closeGroupActions = () => {
    actionOpenGroupId.value = null;
};

const goToOrder = (order) => {
    if (order.showUrl) {
        window.location.href = order.showUrl;
    }
};

const statusClass = (label) => ({
    'status-badge--completed': label === '완료',
    'status-badge--settled': label === '정산',
    'status-badge--active': label === '운행중',
});

onMounted(() => document.addEventListener('click', closeGroupActions));
onBeforeUnmount(() => document.removeEventListener('click', closeGroupActions));
</script>

<template>
    <DataTable
        embedded
        :columns="columns"
        :rows="pagedRows"
        :total="rows.length"
        :per-page="perPage"
        :page="currentPage"
        row-key="key"
        :show-search="false"
        :show-filter="false"
        empty-title="등록된 오더가 없습니다."
        empty-description="검색 조건에 맞는 오더가 없습니다."
        @update:page="currentPage = $event"
    >
        <template #row="{ row }">
            <template v-if="row.kind === 'set'">
                <tr
                    class="order-group-row"
                    :class="{ 'is-expanded': isExpanded(row.id) }"
                    :aria-expanded="String(isExpanded(row.id))"
                    @click="toggleGroup(row.id)"
                >
                    <td class="datatable-cell">
                        <div class="order-group-row__title">
                            <BaseIcon
                                :name="isExpanded(row.id) ? 'chevron-up' : 'chevron-down'"
                                :size="16"
                                class="order-group-row__caret"
                            />
                            <span class="meta-badge">SET</span>
                            <span class="order-group-row__name">{{ row.name }}</span>
                        </div>
                    </td>
                    <td class="datatable-cell">
                        <div class="order-group-row__routes">
                            <span class="order-group-row__route">
                                <span class="order-group-row__route-index">1.</span>
                                <span class="order-group-row__route-path">{{ row.routes[0].route }}</span>
                                <span
                                    v-if="row.routes.length > 2"
                                    class="order-group-row__more"
                                    :title="`${row.routes.length}개 노선 · 펼치기`"
                                    @click.stop="toggleGroup(row.id)"
                                >
                                    <span>+{{ row.routes.length - 1 }}</span>
                                </span>
                            </span>

                            <span
                                v-if="row.routes.length === 2"
                                class="order-group-row__route"
                            >
                                <span class="order-group-row__route-index">2.</span>
                                <span class="order-group-row__route-path">{{ row.routes[1].route }}</span>
                            </span>
                        </div>
                    </td>
                    <td class="datatable-cell">
                        <span class="order-group-row__time">{{ row.pickupDateTime }}</span>
                    </td>
                    <td class="datatable-cell" />
                    <td class="datatable-cell" />
                    <td class="datatable-cell">
                        <span class="order-group-row__amount">{{ row.totalAmount }}</span>
                    </td>
                    <td class="datatable-cell">
                        <span class="status-badge" :class="statusClass(row.statusLabel)">{{ row.statusLabel }}</span>
                    </td>
                    <td class="datatable-cell">
                        <div class="order-table-actions">
                            <button
                                type="button"
                                class="icon-button order-table-actions__toggle"
                                :class="{ 'is-open': actionOpenGroupId === row.id }"
                                title="셋트 액션"
                                aria-label="셋트 액션"
                                :aria-expanded="String(actionOpenGroupId === row.id)"
                                @click.stop="toggleGroupActions(row.id)"
                            >
                                <BaseIcon name="ellipsis-vertical" :size="16" />
                            </button>
                            <div
                                v-if="actionOpenGroupId === row.id"
                                class="menu-panel order-table-actions__menu"
                            >
                                <a
                                    href="#"
                                    class="menu-item"
                                    title="셋트 상세보기"
                                    @click.prevent="goToOrder(row)"
                                >
                                    <BaseIcon name="view" :size="16" />
                                    상세보기
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>

                <template v-if="isExpanded(row.id)">
                    <tr
                        v-for="order in row.orders"
                        :key="order.key"
                        class="datatable-row order-member-row"
                        @click="goToOrder(order)"
                    >
                        <OrderTableCells :columns="columns" :order="order" />
                    </tr>
                </template>
            </template>

            <tr
                v-else
                class="datatable-row order-single-row"
                @click="goToOrder(row)"
            >
                <OrderTableCells :columns="columns" :order="row" />
            </tr>
        </template>
    </DataTable>
</template>
