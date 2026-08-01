<script setup>
defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    row: {
        type: Object,
        required: true,
    },
    rowIndex: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['row-click']);

const resolveValue = (row, column) => {
    return row?.[column.key] ?? '';
};
</script>

<template>
    <tr
        class="datatable-row"
        title="데이터 행"
        @click="emit('row-click', { row, rowIndex })"
    >
        <td
            v-for="column in columns"
            :key="column.key"
            class="datatable-cell"
        >
            <slot
                name="cell"
                :column="column"
                :row="row"
                :row-index="rowIndex"
                :value="resolveValue(row, column)"
            />
        </td>
    </tr>
</template>
