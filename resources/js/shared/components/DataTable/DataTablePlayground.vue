<script setup>
import { computed, ref } from 'vue';
import BaseBadge from '../Badge/BaseBadge.vue';
import BaseButton from '../Button/BaseButton.vue';
import FormGroup from '../Form/FormGroup.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseIcon from '../Icon/BaseIcon.vue';
import DataTable from './DataTable.vue';
import DropdownTableActions from '../Dropdown/DropdownTableActions.vue';

const searchValue = ref('');
const filterValue = ref('all');
const currentPage = ref(1);
const selectedKeys = ref([1, 4, 6]);
const sortKey = ref('');
const sortDirection = ref('');
const bulkActionItems = [
    { label: '상태 변경', icon: 'settings' },
    { label: '내보내기', icon: 'download' },
    { label: '삭제', icon: 'trash' },
];
const priorityOptions = ['낮음', '보통', '높음'];
const primaryMenuItems = [
    { label: '상세보기', icon: 'eye' },
    { label: '권한 변경', icon: 'settings' },
    { label: '삭제', icon: 'trash' },
];
const secondaryMenuItems = [
    { label: '다운로드', icon: 'download' },
    { label: '복제', icon: 'copy' },
    { label: '삭제', icon: 'trash' },
];

const allRows = [
    { id: 1, name: '홍길동', email: 'hong@example.com', role: 'Admin', status: '활성', priority: '높음', dispatchType: 'manual', dueDate: '2026-08-04' },
    { id: 2, name: '김철수', email: 'kim@example.com', role: 'Operator', status: '활성', priority: '보통', dispatchType: 'auto', dueDate: '2026-08-06' },
    { id: 3, name: '이영희', email: 'lee@example.com', role: 'Driver', status: '정지', priority: '낮음', dispatchType: 'manual', dueDate: '2026-08-08' },
    { id: 4, name: '박민수', email: 'park@example.com', role: 'Operator', status: '비활성', priority: '보통', dispatchType: 'auto', dueDate: '2026-08-10' },
    { id: 5, name: '최수진', email: 'choi@example.com', role: 'Admin', status: '활성', priority: '높음', dispatchType: 'manual', dueDate: '2026-08-12' },
    { id: 6, name: '정다은', email: 'jung@example.com', role: 'Driver', status: '활성', priority: '낮음', dispatchType: 'auto', dueDate: '2026-08-15' },
];

const columns = [
    { key: 'name', label: '이름', width: '16%', sortable: true },
    { key: 'email', label: '이메일', width: '20%' },
    { key: 'role', label: '권한', width: '11%', align: 'center' },
    { key: 'status', label: '상태', width: '11%', align: 'center', sortable: true },
    { key: 'priority', label: '우선순위', width: '12%', align: 'center', sortable: true },
    { key: 'dispatchType', label: '배차방식', width: '12%', align: 'center' },
    { key: 'dueDate', label: '희망일', width: '12%', align: 'center', sortable: true },
    { key: 'actions', label: '관리', width: '10%', align: 'right' },
];

const filterOptions = [
    { label: '전체', value: 'all' },
    { label: '활성', value: '활성' },
    { label: '비활성', value: '비활성' },
    { label: '정지', value: '정지' },
];

const filteredRows = computed(() => {
    const keyword = searchValue.value.trim().toLowerCase();

    return allRows.filter((row) => {
        const matchesFilter = filterValue.value === 'all' || row.status === filterValue.value;
        const haystack = [row.name, row.email, row.role, row.status].join(' ').toLowerCase();
        const matchesSearch = keyword.length === 0 || haystack.includes(keyword);

        return matchesFilter && matchesSearch;
    });
});

const sortedRows = computed(() => {
    const rows = [...filteredRows.value];

    if (!sortKey.value || !sortDirection.value) {
        return rows;
    }

    rows.sort((rowA, rowB) => {
        const valueA = String(rowA[sortKey.value] ?? '');
        const valueB = String(rowB[sortKey.value] ?? '');
        const comparison = valueA.localeCompare(valueB);

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });

    return rows;
});

const selectedCount = computed(() => {
    return filteredRows.value.filter((row) => selectedKeys.value.includes(row.id)).length;
});

const perPage = 4;

const pagedRows = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    const end = start + perPage;

    return sortedRows.value
        .slice(start, end)
        .map((row) => ({
            ...row,
            actions: '상세보기',
        }));
});

const handleRowClick = (payload) => {
    window.alert(`${payload.row.name} 행을 선택했습니다.`);
};

const handleDetailClick = (row) => {
    window.alert(`${row.name} 상세보기`);
};

const resolveMenuItems = (row) => {
    const items = row.role === 'Admin' ? primaryMenuItems : secondaryMenuItems;

    return items.map((item) => ({
        ...item,
        action: item.label === '상세보기'
            ? () => handleDetailClick(row)
            : () => window.alert(`${row.name} ${item.label}`),
    }));
};
</script>

<template>
    <DataTable
        title="공통 DataTable 테스트"
        :embedded="true"
        :columns="columns"
        :rows="pagedRows"
        :total="filteredRows.length"
        :per-page="perPage"
        :page="currentPage"
        :search-value="searchValue"
        :filter-value="filterValue"
        :filter-options="filterOptions"
        :show-filter="true"
        :selectable="true"
        :selected-keys="selectedKeys"
        :sort-key="sortKey"
        :sort-direction="sortDirection"
        empty-title="목록 데이터가 없습니다."
        empty-description="검색어 또는 필터 조건을 변경해보세요."
        search-placeholder="이름, 이메일, 권한, 상태 검색"
        @update:page="currentPage = $event"
        @update:search-value="searchValue = $event"
        @update:filter-value="filterValue = $event"
        @update:selected-keys="selectedKeys = $event"
        @update:sort-key="sortKey = $event"
        @update:sort-direction="sortDirection = $event"
        @row-click="handleRowClick"
    >
        <template #toolbar-main>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#d8d8d8] bg-[#efefef] px-4 py-3 dark:border-[#343434] dark:bg-[#202020]">
                <div>
                    <p class="text-sm font-semibold text-[#1f1f1f] dark:text-[#f3f3f3]">다중선택 툴바 샘플</p>
                    <p class="mt-1 text-sm text-[#6a6a6a] dark:text-[#9ea1a8]">선택된 행 {{ selectedCount }}개</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <BaseButton
                        v-for="actionItem in bulkActionItems"
                        :key="actionItem.label"
                        variant="secondary"
                        size="sm"
                        :title="actionItem.label"
                        :aria-label="actionItem.label"
                    >
                        <BaseIcon :name="actionItem.icon" :size="14" />
                        <span>{{ actionItem.label }}</span>
                    </BaseButton>
                </div>
            </div>
        </template>

        <template #toolbar-search>
            <FormGroup label="검색" for-id="datatable-playground-search">
                <BaseInput
                    id="datatable-playground-search"
                    v-model="searchValue"
                    title="데이터 검색"
                    aria-label="데이터 검색"
                    placeholder="이름, 이메일, 권한, 상태 검색"
                />
            </FormGroup>
        </template>

        <template #toolbar-actions>
            <BaseButton
                variant="primary"
                class="min-w-[44px] px-0"
                title="데이터 추가"
                aria-label="데이터 추가"
            >
                <BaseIcon name="add" :size="16" />
            </BaseButton>
        </template>

        <template #cell-role="{ value }">
            <div class="flex justify-center">
                <BaseBadge variant="strong">{{ value }}</BaseBadge>
            </div>
        </template>

        <template #cell-status="{ value }">
            <div class="flex justify-center">
                <BaseBadge :variant="value === '활성' ? 'strong' : 'default'">{{ value }}</BaseBadge>
            </div>
        </template>

        <template #cell-priority="{ value }">
            <div class="flex justify-center">
                <select
                    class="h-9 rounded-lg border border-[#d6d6d6] bg-[#f5f5f5] px-3 text-sm text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#171717] dark:text-[#d6d6dd]"
                    title="우선순위 선택"
                    @click.stop
                >
                    <option
                        v-for="option in priorityOptions"
                        :key="option"
                        :selected="value === option"
                    >
                        {{ option }}
                    </option>
                </select>
            </div>
        </template>

        <template #cell-dispatchType="{ value, row }">
            <div class="flex justify-center gap-3 text-sm text-[#4f4f4f] dark:text-[#b9bbc0]" @click.stop>
                <label class="inline-flex items-center gap-1.5">
                    <input
                        type="radio"
                        :name="`dispatch-type-${row.id}`"
                        :checked="value === 'manual'"
                        title="수동 배차"
                        aria-label="수동 배차"
                        class="h-4 w-4 border border-[#cfcfcf]"
                    >
                    <span>수동</span>
                </label>
                <label class="inline-flex items-center gap-1.5">
                    <input
                        type="radio"
                        :name="`dispatch-type-${row.id}`"
                        :checked="value === 'auto'"
                        title="자동 배차"
                        aria-label="자동 배차"
                        class="h-4 w-4 border border-[#cfcfcf]"
                    >
                    <span>자동</span>
                </label>
            </div>
        </template>

        <template #cell-dueDate="{ value }">
            <div class="flex justify-center" @click.stop>
                <input
                    type="date"
                    :value="value"
                    title="희망일 선택"
                    aria-label="희망일 선택"
                    class="h-9 rounded-lg border border-[#d6d6d6] bg-[#f5f5f5] px-3 text-sm text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#141414] dark:text-[#d6d6dd]"
                >
            </div>
        </template>

        <template #cell-actions="{ row }">
            <div class="flex justify-end" @click.stop>
                <DropdownTableActions
                    :items="resolveMenuItems(row)"
                    trigger-label="관리 메뉴"
                />
            </div>
        </template>
    </DataTable>
</template>
