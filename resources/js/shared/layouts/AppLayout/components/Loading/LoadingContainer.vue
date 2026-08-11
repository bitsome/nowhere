<script setup>
defineProps({
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        v-if="active"
        class="fixed inset-0 z-50 flex items-center justify-center bg-[#f3f3f3]/70 backdrop-blur-[1px] dark:bg-[#171717]/70"
    >
        <div class="rounded-[10px] border border-[#d6d6d6] bg-[#f7f7f7] px-4 py-3 text-sm text-[#555555] dark:border-[#343434] dark:bg-[#1a1a1a] dark:text-[#b9bbc0]">
            Loading...
        </div>
    </div>
</template>
