<script setup>
import { computed, useSlots } from 'vue';

import AppContent from './components/Content/AppContent.vue';
import AppFooter from './components/Footer/AppFooter.vue';
import AppHeader from './components/Header/AppHeader.vue';
import AppSidebar from './components/Sidebar/AppSidebar.vue';
import DialogContainer from './components/Dialog/DialogContainer.vue';
import LoadingContainer from './components/Loading/LoadingContainer.vue';
import NotificationContainer from './components/Notification/NotificationContainer.vue';
import ToastContainer from './components/Toast/ToastContainer.vue';
import { appLayoutDefaults } from './constants/layout.js';
import { appSidebarMenu } from './constants/navigation.js';

const props = defineProps({
    alarms: {
        type: Array,
        default: () => [],
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    buildText: {
        type: String,
        default: '',
    },
    currentPath: {
        type: String,
        default: '',
    },
    footerText: {
        type: String,
        default: '',
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    isSidebarCollapsed: {
        type: Boolean,
        default: false,
    },
    isSidebarOpen: {
        type: Boolean,
        default: false,
    },
    notifications: {
        type: Array,
        default: () => [],
    },
    pageDescription: {
        type: String,
        default: '',
    },
    pageTitle: {
        type: String,
        default: '',
    },
    profileName: {
        type: String,
        default: '',
    },
    searchPlaceholder: {
        type: String,
        default: '검색',
    },
    showFooter: {
        type: Boolean,
        default: false,
    },
    sidebarItems: {
        type: Array,
        default: () => [],
    },
    toasts: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'alarm-click',
    'logout',
    'profile-click',
    'search',
    'sidebar-toggle',
    'toast-close',
    'dialog-close',
    'notification-close',
]);
const slots = useSlots();

const resolvedSearchPlaceholder = computed(() => props.searchPlaceholder || appLayoutDefaults.searchPlaceholder);
const resolvedShowFooter = computed(() => props.showFooter ?? appLayoutDefaults.showFooter);
const resolvedSidebarItems = computed(() => props.sidebarItems.length ? props.sidebarItems : appSidebarMenu);
</script>

<template>
    <div class="min-h-screen bg-[#f3f3f3] text-[#1f1f1f] dark:bg-[#171717] dark:text-[#d6d6dd]">
        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-4 lg:px-8 lg:py-6">
            <AppHeader
                :alarms="alarms"
                :breadcrumbs="breadcrumbs"
                :notifications="notifications"
                :profile-name="profileName"
                :search-placeholder="resolvedSearchPlaceholder"
                @alarm-click="$emit('alarm-click', $event)"
                @logout="$emit('logout')"
                @profile-click="$emit('profile-click')"
                @search="$emit('search', $event)"
                @sidebar-toggle="$emit('sidebar-toggle')"
            >
                <template v-if="slots['header-brand']" #brand>
                    <slot name="header-brand" />
                </template>

                <template v-if="slots['header-search']" #search>
                    <slot name="header-search" />
                </template>

                <template v-if="slots['header-actions']" #actions>
                    <slot name="header-actions" />
                </template>
            </AppHeader>

            <div class="mt-4 grid flex-1 gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                <button
                    v-if="isSidebarOpen"
                    type="button"
                    class="fixed inset-0 z-20 bg-[#171717]/40 lg:hidden"
                    aria-label="사이드바 닫기"
                    @click="$emit('sidebar-toggle')"
                />

                <AppSidebar
                    :current-path="currentPath"
                    :is-collapsed="isSidebarCollapsed"
                    :is-open="isSidebarOpen"
                    :items="resolvedSidebarItems"
                    @toggle="$emit('sidebar-toggle')"
                >
                    <template v-if="slots['sidebar-brand']" #brand>
                        <slot name="sidebar-brand" />
                    </template>

                    <template v-if="slots['sidebar-footer']" #footer>
                        <slot name="sidebar-footer" />
                    </template>
                </AppSidebar>

                <AppContent
                    :breadcrumbs="breadcrumbs"
                    :description="pageDescription"
                    :title="pageTitle"
                >
                    <template v-if="slots['content-header']" #header>
                        <slot name="content-header" />
                    </template>

                    <template v-if="slots['content-toolbar']" #toolbar>
                        <slot name="content-toolbar" />
                    </template>

                    <slot />
                </AppContent>
            </div>

            <AppFooter
                v-if="resolvedShowFooter"
                :build-text="buildText"
                :text="footerText"
            >
                <slot name="footer" />
            </AppFooter>
        </div>

        <ToastContainer :items="toasts" @close="$emit('toast-close', $event)" />
        <DialogContainer @close="$emit('dialog-close')" />
        <LoadingContainer :active="isLoading" />
        <NotificationContainer
            :items="notifications"
            @close="$emit('notification-close', $event)"
        />
    </div>
</template>
