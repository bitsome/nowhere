<script setup>
import { computed } from 'vue';

import BaseIcon from '../../../../components/Icon/BaseIcon.vue';
import Notification from './Notification/Notification.vue';

const props = defineProps({
    alarms: {
        type: Array,
        default: () => [],
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    profileName: {
        type: String,
        default: '',
    },
    notifications: {
        type: Array,
        default: () => [],
    },
    searchPlaceholder: {
        type: String,
        default: '검색',
    },
});

const emit = defineEmits(['alarm-click', 'logout', 'profile-click', 'search', 'sidebar-toggle']);

const handleSearch = (event) => {
    emit('search', event.target.value);
};

const headerNotifications = computed(() => {
    return props.notifications.length ? props.notifications : props.alarms;
});
</script>

<template>
    <header class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#d6d6d6] bg-[#f1f1f1] text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd] lg:hidden"
                title="사이드바 열기"
                aria-label="사이드바 토글"
                @click="$emit('sidebar-toggle')"
            >
                <BaseIcon name="menu" :size="20" />
            </button>

            <slot name="brand">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#d6d6d6] bg-[#f1f1f1] text-sm font-semibold text-[#1f1f1f] dark:border-[#343434] dark:bg-[#202020] dark:text-[#d6d6dd]">
                        N
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold uppercase tracking-[0.2em] text-[#1f1f1f] dark:text-[#d6d6dd]">Nowhere</p>
                        <div class="flex min-w-0 items-center gap-2 text-sm text-[#6a6a6a] dark:text-[#9ea1a8]">
                            <span v-for="(item, index) in breadcrumbs" :key="`${item.label}-${index}`" class="flex min-w-0 items-center gap-2">
                                <span class="truncate">{{ item.label }}</span>
                                <span v-if="index !== breadcrumbs.length - 1">/</span>
                            </span>
                        </div>
                    </div>
                </div>
            </slot>

            <div class="ml-auto flex flex-1 flex-wrap items-center justify-end gap-3">
                <slot name="search">
                    <label class="relative min-w-[180px] flex-1 sm:max-w-xs">
                        <span class="sr-only">검색</span>
                        <input
                            type="search"
                            :placeholder="searchPlaceholder"
                            class="h-10 w-full rounded-lg border border-[#d6d6d6] bg-[#f3f3f3] px-3 text-sm text-[#1f1f1f] outline-none transition focus:border-[#b9b9b9] dark:border-[#2a2a2a] dark:bg-[#141414] dark:text-[#d6d6dd] dark:focus:border-[#3a3a3a]"
                            @input="handleSearch"
                        >
                    </label>
                </slot>

                <slot name="actions">
                    <Notification
                        :items="headerNotifications"
                        @click="$emit('alarm-click')"
                    />

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#d6d6d6] bg-[#f3f3f3] text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                        :title="profileName || '프로필'"
                        :aria-label="profileName || '프로필'"
                        @click="$emit('profile-click')"
                    >
                        <BaseIcon name="user" :size="18" />
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#d6d6d6] bg-[#f3f3f3] text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                        title="로그아웃"
                        aria-label="로그아웃"
                        @click="$emit('logout')"
                    >
                        <BaseIcon name="logout" :size="18" />
                    </button>
                </slot>
            </div>
        </div>
    </header>
</template>
