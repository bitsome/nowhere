<script setup>
import { computed } from 'vue';

const props = defineProps({
    column: {
        type: Object,
        required: true,
    },
    value: {
        type: [String, Number, Boolean],
        default: '',
    },
});

const classes = computed(() => [
    'datatable-cell__value',
    props.column.align === 'center' ? 'text-center' : '',
    props.column.align === 'right' ? 'text-right' : '',
]);
</script>

<template>
    <div :class="classes" :title="String(value ?? '')">
        {{ value }}
    </div>
</template>
