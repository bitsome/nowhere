<script setup>
defineProps({
    buildText: {
        type: String,
        default: '',
    },
    text: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <footer class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-[#6a6a6a] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#9ea1a8]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <p>{{ text }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <span v-if="buildText">{{ buildText }}</span>
                <slot />
            </div>
        </div>
    </footer>
</template>
