<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['close']);
</script>

<template>
    <aside class="pointer-events-none fixed bottom-4 right-4 z-30 flex max-w-sm flex-col gap-3">
        <div
            v-for="item in items"
            :key="item.id || item.message"
            class="pointer-events-auto rounded-[10px] border border-[#d6d6d6] bg-[#f7f7f7] px-4 py-3 text-sm text-[#555555] dark:border-[#343434] dark:bg-[#1a1a1a] dark:text-[#b9bbc0]"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-[#1f1f1f] dark:text-[#d6d6dd]">{{ item.title || 'Notification' }}</p>
                    <p class="mt-1">{{ item.message }}</p>
                </div>
                <button type="button" class="text-[#8b8b8b] dark:text-[#7d8087]" @click="$emit('close', item)">닫기</button>
            </div>
        </div>
    </aside>
</template>
