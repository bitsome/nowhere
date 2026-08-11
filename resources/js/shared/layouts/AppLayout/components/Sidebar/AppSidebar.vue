<script setup>
defineProps({
    currentPath: {
        type: String,
        default: '',
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['toggle']);

const isVisible = (item) => item.visible !== false;
</script>

<template>
    <aside
        :class="[
            'rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 transition dark:border-[#2a2a2a] dark:bg-[#1a1a1a]',
            isOpen ? 'fixed inset-y-4 left-4 z-30 w-[calc(100vw-2rem)] max-w-[280px] lg:static lg:inset-auto lg:z-auto lg:w-auto' : 'hidden lg:block',
            isCollapsed ? 'lg:w-[88px]' : '',
        ]"
    >
        <div class="flex min-h-full flex-col">
            <div class="flex items-center justify-between border-b border-[#dddddd] pb-4 dark:border-[#2a2a2a]">
                <slot name="brand">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#1f1f1f] dark:text-[#d6d6dd]">Menu</p>
                        <p v-if="!isCollapsed" class="mt-1 text-sm text-[#6a6a6a] dark:text-[#9ea1a8]">공통 운영 메뉴</p>
                    </div>
                </slot>

                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#d6d6d6] bg-[#f3f3f3] text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#171717] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    @click="$emit('toggle')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" d="M9 6l6 6-6 6" />
                    </svg>
                </button>
            </div>

            <nav class="mt-4 flex-1 space-y-2">
                <a
                    v-for="item in items.filter(isVisible)"
                    :key="item.key || item.label"
                    :href="item.href || '#'"
                    :class="[
                        'flex items-center gap-3 rounded-lg border px-3 py-2 text-sm transition',
                        currentPath === item.href
                            ? 'border-[#c7c7c7] bg-[#ececec] text-[#1f1f1f] dark:border-[#343434] dark:bg-[#252526] dark:text-[#f3f3f3]'
                            : 'border-transparent text-[#4f4f4f] hover:border-[#d8d8d8] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:border-[#343434] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]',
                    ]"
                >
                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center text-xs font-semibold">
                        {{ item.icon || item.label.slice(0, 1) }}
                    </span>
                    <span v-if="!isCollapsed" class="truncate">{{ item.label }}</span>
                </a>
            </nav>

            <div v-if="$slots.footer" class="mt-4 border-t border-[#dddddd] pt-4 dark:border-[#2a2a2a]">
                <slot name="footer" />
            </div>
        </div>
    </aside>
</template>
