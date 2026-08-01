<script setup>
import TableColumn from './TableColumn.vue';

defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <thead class="datatable-head">
        <tr>
            <TableColumn
                v-for="column in columns"
                :key="column.key"
                :column="column"
                scope="col"
            >
                <slot name="header" :column="column">
                    {{ column.label }}
                </slot>
            </TableColumn>
        </tr>
    </thead>
</template>
