<script setup>
import AppBreadcrumb from '../Breadcrumb/AppBreadcrumb.vue';
import AppPageTitle from '../PageTitle/AppPageTitle.vue';

defineProps({
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    description: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <main class="flex-1 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
        <div class="space-y-4">
            <slot name="header">
                <AppBreadcrumb :items="breadcrumbs" />
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <AppPageTitle :description="description" :title="title" />
                    <div v-if="$slots.toolbar" class="shrink-0">
                        <slot name="toolbar" />
                    </div>
                </div>
            </slot>
            <div>
                <slot />
            </div>
        </div>
    </main>
</template>
