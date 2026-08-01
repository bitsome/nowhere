<script setup>
import { computed } from 'vue';

const props = defineProps({
    description: {
        type: String,
        default: '',
    },
    padded: {
        type: Boolean,
        default: true,
    },
    surface: {
        type: String,
        default: 'default',
    },
    title: {
        type: String,
        default: '',
    },
});

const surfaceClassMap = {
    default: 'border-[#dddddd] bg-[#f7f7f7] dark:border-[#2a2a2a] dark:bg-[#1a1a1a]',
    muted: 'border-[#d6d6d6] bg-[#efefef] dark:border-[#2a2a2a] dark:bg-[#181818]',
    plain: 'border-[#dddddd] bg-transparent dark:border-[#2a2a2a] dark:bg-transparent',
};

const classes = computed(() => [
    'rounded-[10px] border shadow-none',
    surfaceClassMap[props.surface] || surfaceClassMap.default,
    props.padded ? 'p-4' : '',
]);
</script>

<template>
    <section :class="classes">
        <div v-if="title || description || $slots.header" class="mb-4">
            <slot name="header">
                <h3 v-if="title" class="text-base font-semibold text-[#202020] dark:text-[#d6d6dd]">
                    {{ title }}
                </h3>
                <p v-if="description" class="mt-2 text-sm leading-6 text-[#6a6a6a] dark:text-[#9ea1a8]">
                    {{ description }}
                </p>
            </slot>
        </div>

        <slot />

        <div v-if="$slots.footer" class="mt-4 border-t border-[#dddddd] pt-4 dark:border-[#2a2a2a]">
            <slot name="footer" />
        </div>
    </section>
</template>
