<script setup>
import { computed } from 'vue';
import BaseCard from '../Card/BaseCard.vue';
import TableBody from './TableBody.vue';
import TableCell from './TableCell.vue';
import TableEmpty from './TableEmpty.vue';
import TableHeader from './TableHeader.vue';
import TableLoading from './TableLoading.vue';
import TablePagination from './TablePagination.vue';
import TableSearch from './TableSearch.vue';
import TableFilter from './TableFilter.vue';
import TableRow from './TableRow.vue';
import TableToolbar from './TableToolbar.vue';
import './table.css';

const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    embedded: {
        type: Boolean,
        default: false,
    },
    emptyDescription: {
        type: String,
        default: '조건에 맞는 데이터가 없습니다.',
    },
    emptyTitle: {
        type: String,
        default: '데이터가 없습니다.',
    },
    filterOptions: {
        type: Array,
        default: () => [],
    },
    filterValue: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    page: {
        type: Number,
        default: 1,
    },
    perPage: {
        type: Number,
        default: 10,
    },
    rowKey: {
        type: String,
        default: 'id',
    },
    rows: {
        type: Array,
        default: () => [],
    },
    searchPlaceholder: {
        type: String,
        default: '검색어를 입력하세요',
    },
    searchValue: {
        type: String,
        default: '',
    },
    showFilter: {
        type: Boolean,
        default: false,
    },
    showPagination: {
        type: Boolean,
        default: true,
    },
    showSearch: {
        type: Boolean,
        default: true,
    },
    title: {
        type: String,
        default: '데이터 목록',
    },
    total: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits([
    'row-click',
    'update:filterValue',
    'update:page',
    'update:searchValue',
]);

const cardProps = computed(() => {
    if (props.embedded) {
        return {
            class: 'datatable-embedded',
        };
    }

    return {
        surface: 'default',
        title: props.title,
    };
});

const normalizedColumns = computed(() => {
    return props.columns
        .filter((column) => column && typeof column.key === 'string' && column.key.length > 0)
        .map((column) => ({
            align: column.align || 'left',
            className: column.className || '',
            key: column.key,
            label: column.label || column.key,
            width: column.width || '',
        }));
});

const resolvedTotal = computed(() => {
    if (typeof props.total === 'number') {
        return props.total;
    }

    return props.rows.length;
});

const totalPages = computed(() => {
    if (resolvedTotal.value === 0) {
        return 1;
    }

    return Math.max(1, Math.ceil(resolvedTotal.value / props.perPage));
});

const currentPage = computed(() => {
    return Math.min(Math.max(1, props.page), totalPages.value);
});

const rangeStart = computed(() => {
    if (resolvedTotal.value === 0) {
        return 0;
    }

    return (currentPage.value - 1) * props.perPage + 1;
});

const rangeEnd = computed(() => {
    if (resolvedTotal.value === 0) {
        return 0;
    }

    return Math.min(currentPage.value * props.perPage, resolvedTotal.value);
});

const hasRows = computed(() => props.rows.length > 0);

const handleSearch = (value) => {
    emit('update:searchValue', value);
    emit('update:page', 1);
};

const handleFilter = (value) => {
    emit('update:filterValue', value);
    emit('update:page', 1);
};

const handlePage = (nextPage) => {
    emit('update:page', nextPage);
};

const handleRowClick = (payload) => {
    emit('row-click', payload);
};
</script>

<template>
    <component :is="props.embedded ? 'div' : BaseCard" v-bind="cardProps">
        <TableToolbar>
            <template #default>
                <slot name="toolbar-main" />
            </template>

            <template #search>
                <slot name="toolbar-search">
                    <TableSearch
                        v-if="showSearch"
                        :model-value="searchValue"
                        :placeholder="searchPlaceholder"
                        @update:model-value="handleSearch"
                    />
                </slot>
            </template>

            <template #filter>
                <slot name="toolbar-filter">
                    <TableFilter
                        v-if="showFilter"
                        :model-value="filterValue"
                        :options="filterOptions"
                        @update:model-value="handleFilter"
                    />
                </slot>
            </template>

            <template #actions>
                <slot name="toolbar-actions" />
            </template>
        </TableToolbar>

        <div class="datatable-frame">
            <table class="datatable-table" title="데이터 테이블">
                <TableHeader :columns="normalizedColumns">
                    <template #header="{ column }">
                        <slot
                            :name="`header-${column.key}`"
                            :column="column"
                        >
                            {{ column.label }}
                        </slot>
                    </template>
                </TableHeader>

                <TableBody
                    v-if="!loading && hasRows"
                    :columns="normalizedColumns"
                    :rows="rows"
                    :row-key="rowKey"
                    @row-click="handleRowClick"
                >
                    <template #default="{ row, rowIndex }">
                        <slot name="row" :row="row" :row-index="rowIndex">
                            <TableRow
                                :columns="normalizedColumns"
                                :row="row"
                                :row-index="rowIndex"
                                @row-click="handleRowClick"
                            >
                                <template #cell="{ column, row: slotRow, rowIndex: slotRowIndex, value }">
                                    <slot
                                        :name="`cell-${column.key}`"
                                        :column="column"
                                        :row="slotRow"
                                        :row-index="slotRowIndex"
                                        :value="value"
                                    >
                                        <slot
                                            name="cell"
                                            :column="column"
                                            :row="slotRow"
                                            :row-index="slotRowIndex"
                                            :value="value"
                                        >
                                            <TableCell :column="column" :value="value" />
                                        </slot>
                                    </slot>
                                </template>
                            </TableRow>
                        </slot>
                    </template>
                </TableBody>
            </table>

            <slot v-if="loading" name="loading">
                <TableLoading />
            </slot>

            <slot
                v-else-if="!hasRows"
                name="empty"
            >
                <TableEmpty :title="emptyTitle" :description="emptyDescription" />
            </slot>
        </div>

        <slot name="pagination">
            <TablePagination
                v-if="showPagination"
                :current-page="currentPage"
                :total-pages="totalPages"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                :total-items="resolvedTotal"
                @page-change="handlePage"
            />
        </slot>
    </component>
</template>
