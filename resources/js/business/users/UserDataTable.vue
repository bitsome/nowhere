<script setup>
import { computed, ref } from 'vue';
import BaseBadge from '../../shared/components/Badge/BaseBadge.vue';
import DataTable from '../../shared/components/DataTable/DataTable.vue';
import DropdownTableActions from '../../shared/components/Dropdown/DropdownTableActions.vue';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
});

const currentPage = ref(1);
const perPage = 10;

const columns = [
    { key: 'id', label: '고유번호', width: '10%' },
    { key: 'name', label: '이름', width: '16%' },
    { key: 'phone', label: '전화번호', width: '18%' },
    { key: 'role', label: 'Role', width: '12%', align: 'center' },
    { key: 'status', label: '상태', width: '10%', align: 'center' },
    { key: 'createdAt', label: '가입일', width: '16%' },
    { key: 'actions', label: '액션', width: '10%', align: 'right' },
];

const pagedRows = computed(() => {
    const start = (currentPage.value - 1) * perPage;

    return props.rows.slice(start, start + perPage);
});

const openUserDetail = (userId) => {
    window.__nowhereOpenUserDetail?.(userId);
};
</script>

<template>
    <DataTable
        :embedded="true"
        :columns="columns"
        :rows="pagedRows"
        :total="rows.length"
        :per-page="perPage"
        :page="currentPage"
        :show-search="false"
        :show-filter="false"
        :show-pagination="true"
        empty-title="회원이 없습니다."
        empty-description="검색 조건에 맞는 회원이 없습니다."
        @update:page="currentPage = $event"
    >
        <template #cell-name="{ row, value }">
            <button
                type="button"
                data-user-detail-trigger
                :data-user-id="row.id"
                :title="`${value} 상세보기`"
                class="text-gray-900 underline-offset-4 transition hover:underline dark:text-gray-100"
            >
                {{ value }}
            </button>
        </template>

        <template #cell-role="{ value }">
            <div class="flex justify-center">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ value }}</span>
            </div>
        </template>

        <template #cell-status="{ value }">
            <div class="flex justify-center">
                <BaseBadge :variant="value === '활성' ? 'strong' : 'default'">{{ value }}</BaseBadge>
            </div>
        </template>

        <template #cell-createdAt="{ value }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ String(value).slice(0, 10) }}</span>
        </template>

        <template #cell-actions="{ row }">
            <div class="flex justify-end">
                <DropdownTableActions
                    :items="[
                        { icon: 'eye', label: '상세보기', action: () => openUserDetail(row.id) },
                        { icon: 'shield', label: '권한관리', href: row.permissionsUrl },
                    ]"
                    :trigger-label="`${row.name} 액션`"
                />
            </div>
        </template>
    </DataTable>
</template>
