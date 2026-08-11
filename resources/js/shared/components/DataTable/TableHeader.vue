<script setup>
import TableColumn from './TableColumn.vue';

const props = defineProps({
    allSelected: {
        type: Boolean,
        default: false,
    },
    columns: {
        type: Array,
        default: () => [],
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    someSelected: {
        type: Boolean,
        default: false,
    },
    sortDirection: {
        type: String,
        default: '',
    },
    sortKey: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select-all', 'sort']);
</script>

<template>
    <thead class="datatable-head">
        <tr>
            <th
                v-if="selectable"
                class="datatable-head__column datatable-head__select"
            >
                <input
                    type="checkbox"
                    :checked="allSelected"
                    :indeterminate="someSelected && !allSelected"
                    title="전체 선택"
                    aria-label="전체 선택"
                    class="h-4 w-4 rounded border border-[#cfcfcf]"
                    @change="emit('select-all', $event)"
                >
            </th>

            <TableColumn
                v-for="column in columns"
                :key="column.key"
                :column="column"
                :sortable="column.sortable"
                :sort-key="sortKey"
                :sort-direction="sortDirection"
                scope="col"
                @sort="emit('sort', column)"
            >
                <slot name="header" :column="column">
                    {{ column.label }}
                </slot>
            </TableColumn>
        </tr>
    </thead>
</template>
