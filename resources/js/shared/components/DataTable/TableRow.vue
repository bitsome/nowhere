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
    selectable: {
        type: Boolean,
        default: false,
    },
    selected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['row-click', 'select-toggle']);

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
            v-if="selectable"
            class="datatable-cell datatable-cell--select"
            @click.stop
        >
            <input
                type="checkbox"
                :checked="selected"
                title="행 선택"
                aria-label="행 선택"
                class="h-4 w-4 rounded border border-[#cfcfcf]"
                @change="emit('select-toggle', { row, rowIndex })"
            >
        </td>

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
