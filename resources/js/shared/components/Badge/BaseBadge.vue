<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: {
        type: String,
        default: 'md',
    },
    variant: {
        type: String,
        default: 'default',
    },
});

const sizeClassMap = {
    md: 'px-2.5 py-1 text-xs',
    sm: 'px-2 py-0.5 text-[11px]',
};

const variantClassMap = {
    default: 'border-[#d8d8d8] bg-[#f1f1f1] text-[#555555] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]',
    outline: 'border-[#d8d8d8] bg-transparent text-[#555555] dark:border-[#343434] dark:text-[#b9bbc0]',
    strong: 'border-[#d0d0d0] bg-[#ececec] text-[#1f1f1f] dark:border-[#343434] dark:bg-[#252526] dark:text-[#d6d6dd]',
};

const classes = computed(() => [
    'inline-flex items-center rounded-full border font-medium',
    sizeClassMap[props.size] || sizeClassMap.md,
    variantClassMap[props.variant] || variantClassMap.default,
]);
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
