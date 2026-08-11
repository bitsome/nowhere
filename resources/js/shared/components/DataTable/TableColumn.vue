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
    sortDirection: {
        type: String,
        default: '',
    },
    sortKey: {
        type: String,
        default: '',
    },
    sortable: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['sort']);

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

const isActiveSort = computed(() => {
    return props.sortKey === props.column.key && props.sortDirection !== '';
});
</script>

<template>
    <th
        :class="classes"
        :scope="scope || undefined"
        :style="style"
    >
        <button
            v-if="sortable"
            type="button"
            class="datatable-sort"
            :class="{ 'is-active': isActiveSort }"
            :title="`${column.label} 정렬`"
            @click="emit('sort')"
        >
            <slot />
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5" aria-hidden="true">
                <path
                    v-if="isActiveSort && sortDirection === 'asc'"
                    stroke-linecap="round"
                    d="M6 15l6-6l6 6"
                />
                <path
                    v-else
                    stroke-linecap="round"
                    d="M6 9l6 6l6-6"
                />
            </svg>
        </button>

        <slot v-else />
    </th>
</template>
