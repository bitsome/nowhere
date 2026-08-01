<script setup>
import DropdownActionMenu from './DropdownActionMenu.vue';

defineProps({
    csrfToken: {
        type: String,
        default: '',
    },
    dashboardUrl: {
        type: String,
        default: '#',
    },
    description: {
        type: String,
        default: '',
    },
    notificationUrl: {
        type: String,
        default: '#',
    },
    logoutUrl: {
        type: String,
        default: '#',
    },
    profileUrl: {
        type: String,
        default: '#',
    },
    title: {
        type: String,
        default: 'Dashboard',
    },
    triggerLabel: {
        type: String,
        default: '바로가기 메뉴',
    },
});
</script>

<template>
    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Shared Dropdown 테스트</p>
        <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">대시보드 액션 메뉴</h4>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <DropdownActionMenu
                :csrf-token="csrfToken"
                :dashboard-url="dashboardUrl"
                :description="description"
                :notification-url="notificationUrl"
                :logout-url="logoutUrl"
                :profile-url="profileUrl"
                :title="title"
                :trigger-label="triggerLabel"
            />
        </div>
    </div>
</template>
