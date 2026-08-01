<script setup>
const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    rowKey: {
        type: String,
        default: 'id',
    },
    rows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['row-click']);

const resolveRowKey = (row, rowIndex) => {
    return row?.[props.rowKey] ?? row?.id ?? row?.uuid ?? `${rowIndex}`;
};
</script>

<template>
    <tbody class="datatable-body">
        <template v-for="(row, rowIndex) in rows" :key="resolveRowKey(row, rowIndex)">
            <slot
                :row="row"
                :row-index="rowIndex"
                :columns="columns"
            />
        </template>
    </tbody>
</template>
