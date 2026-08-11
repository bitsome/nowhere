<script setup>
import { computed } from 'vue';

const props = defineProps({
    as: {
        type: String,
        default: 'button',
    },
    block: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    href: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
    },
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'primary',
    },
});

const emit = defineEmits(['click']);

const componentTag = computed(() => (props.href ? 'a' : props.as));

const sizeClassMap = {
    sm: 'h-9 px-3 text-sm',
    md: 'h-11 px-4 text-sm',
    lg: 'h-12 px-5 text-sm',
};

const variantClassMap = {
    ghost: 'border-transparent bg-transparent text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]',
    primary: 'border-[#d0d0d0] bg-[#ececec] text-[#1f1f1f] hover:border-[#c7c7c7] hover:bg-[#e4e4e4] dark:border-[#343434] dark:bg-[#252526] dark:text-[#d6d6dd] dark:hover:bg-[#2d2d30] dark:hover:text-[#f3f3f3]',
    secondary: 'border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#2d2d2d] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]',
    danger: 'border-[#512727] bg-transparent text-[#512727] hover:border-[#451e1e] hover:bg-[#f3e9e9] hover:text-[#451e1e] dark:border-[#c47070] dark:text-[#c47070] dark:hover:border-[#d08080] dark:hover:bg-[#2a1d1d] dark:hover:text-[#d98a8a]',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg border font-medium transition focus:outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:border-[#d8d8d8] disabled:bg-[#f4f4f4] disabled:text-[#8b8b8b] dark:disabled:border-[#2a2a2a] dark:disabled:bg-[#171717] dark:disabled:text-[#6d6d6d]',
    sizeClassMap[props.size] || sizeClassMap.md,
    variantClassMap[props.variant] || variantClassMap.primary,
    props.block ? 'w-full' : '',
]);

const componentAttrs = computed(() => {
    if (componentTag.value === 'button') {
        return {
            disabled: props.disabled,
            type: props.type,
        };
    }

    return {
        'aria-disabled': props.disabled ? 'true' : undefined,
        href: props.disabled ? undefined : props.href,
        role: 'button',
        tabindex: props.disabled ? -1 : undefined,
    };
});

const handleClick = (event) => {
    if (props.disabled) {
        event.preventDefault();

        return;
    }

    emit('click', event);
};
</script>

<template>
    <component
        :is="componentTag"
        v-bind="componentAttrs"
        :class="classes"
        @click="handleClick"
    >
        <slot />
    </component>
</template>
