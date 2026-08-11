<script setup>
import { ref } from 'vue';

const props = defineProps({
    initialView: { type: String, default: 'grid' },
    changeHandler: { type: Function, default: null },
});

const activeView = ref(props.initialView);

const setView = (view) => {
    activeView.value = view;
    props.changeHandler?.(view);
};

const selected  = 'border border-[#d0d0d0] bg-[#ececec] font-semibold text-[#1f1f1f] dark:border-[#343434] dark:bg-[#252526] dark:text-[#d6d6dd]';
const unselected = 'border border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#2d2d2d] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]';
</script>

<template>
    <div class="inline-flex" role="group" aria-label="보기 방식">
        <button type="button" class="flex h-9 items-center gap-1.5 rounded-l-lg border-r-0 px-3 text-sm transition"
            :class="activeView === 'grid' ? selected : unselected"
            title="그리드 보기" aria-label="그리드 보기" :aria-pressed="String(activeView === 'grid')" @click="setView('grid')">
            그리드
        </button>
        <button type="button" class="flex h-9 items-center gap-1.5 rounded-r-lg px-3 text-sm transition"
            :class="activeView === 'list' ? selected : unselected"
            title="리스트 보기" aria-label="리스트 보기" :aria-pressed="String(activeView === 'list')" @click="setView('list')">
            리스트
        </button>
    </div>
</template>
