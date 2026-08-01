<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-[#141414]/30 px-4">
        <div class="w-full max-w-lg rounded-[10px] border border-[#d6d6d6] bg-[#f7f7f7] p-4 dark:border-[#343434] dark:bg-[#1a1a1a]">
            <slot>
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-[#555555] dark:text-[#b9bbc0]">Dialog Container</p>
                    <button type="button" class="btn-secondary" @click="$emit('close')">닫기</button>
                </div>
            </slot>
        </div>
    </div>
</template>
