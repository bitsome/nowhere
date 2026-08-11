<script setup>
const props = defineProps({
    active: {
        type: Boolean,
        default: true,
    },
    inline: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: 'Loading...',
    },
});
</script>

<template>
    <div
        v-if="active"
        :class="[
            inline
                ? 'inline-flex items-center gap-3'
                : 'fixed inset-0 z-50 flex items-center justify-center bg-[#f3f3f3]/70 px-4 backdrop-blur-[1px] dark:bg-[#171717]/70',
        ]"
    >
        <div
            :class="[
                'flex items-center gap-3 rounded-[10px] border border-[#d6d6d6] bg-[#f7f7f7] px-4 py-3 text-sm text-[#555555] shadow-none dark:border-[#343434] dark:bg-[#1a1a1a] dark:text-[#b9bbc0]',
                inline ? 'border-transparent bg-transparent px-0 py-0 dark:bg-transparent' : '',
            ]"
        >
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#cfcfcf] border-t-[#555555] dark:border-[#343434] dark:border-t-[#d6d6dd]" />
            <span>{{ label }}</span>
        </div>
    </div>
</template>
