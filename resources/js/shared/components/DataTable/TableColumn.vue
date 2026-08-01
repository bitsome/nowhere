<script setup>
import { computed } from 'vue';

const props = defineProps({
    column: {
        type: Object,
        required: true,
    },
    scope: {
        type: String,
        default: '',
    },
});

const classes = computed(() => [
    'datatable-head__column',
    props.column.align === 'center' ? 'text-center' : '',
    props.column.align === 'right' ? 'text-right' : '',
    props.column.className || '',
]);

const style = computed(() => {
    return props.column.width
        ? { width: props.column.width }
        : undefined;
});
</script>

<template>
    <th
        :class="classes"
        :scope="scope || undefined"
        :style="style"
    >
        <slot />
    </th>
</template>
