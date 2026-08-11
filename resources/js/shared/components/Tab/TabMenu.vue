<script setup>
import BaseBadge from '../Badge/BaseBadge.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-1" role="tablist" aria-label="탭 메뉴">
        <a
            v-for="item in props.items" :key="item.label"
            :href="item.url || '#'"
            :role="item.active ? 'tab' : undefined"
            :aria-selected="item.active ? 'true' : undefined"
            class="flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm transition"
            :class="item.active
                ? 'border-[#d0d0d0] bg-[#ececec] font-semibold text-[#1f1f1f] dark:border-[#343434] dark:bg-[#252526] dark:text-[#d6d6dd]'
                : 'border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#2d2d2d] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]'"
            :title="`${item.label} 탭`"
        >
            {{ item.label }}
            <BaseBadge v-if="typeof item.count === 'number'" size="sm" variant="default">
                {{ item.count }}
            </BaseBadge>
        </a>
    </div>
</template>
