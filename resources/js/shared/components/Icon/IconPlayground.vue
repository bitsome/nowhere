<script setup>
import BaseIcon from './BaseIcon.vue';

/**
 * 공용 아이콘(Icon) 플레이그라운드.
 *
 * 현재 BaseIcon에 정의되어 실제로 사용 중인 SVG 아이콘 전체 목록을 보여준다.
 */
const icons = [
    { name: 'menu', label: 'menu' },
    { name: 'close', label: 'close' },
    { name: 'dashboard', label: 'dashboard' },
    { name: 'bell', label: 'bell' },
    { name: 'user', label: 'user' },
    { name: 'logout', label: 'logout' },
    { name: 'search', label: 'search' },
    { name: 'add', label: 'add (plus)' },
    { name: 'upload', label: 'upload' },
    { name: 'download', label: 'download' },
    { name: 'trash', label: 'trash (delete)' },
    { name: 'check', label: 'check' },
    { name: 'chevron-left', label: 'chevron-left' },
    { name: 'chevron-right', label: 'chevron-right' },
    { name: 'chevron-down', label: 'chevron-down' },
    { name: 'chevron-up', label: 'chevron-up' },
    { name: 'view', label: 'view (eye)' },
    { name: 'settings', label: 'settings' },
    { name: 'refresh', label: 'refresh' },
    { name: 'copy', label: 'copy (duplicate)' },
    { name: 'edit', label: 'edit (pencil)' },
    { name: 'ellipsis-vertical', label: 'ellipsis-vertical (점 3개)' },
    { name: 'shield', label: 'shield' },
    { name: 'pickup', label: 'pickup (공항픽업)' },
    { name: 'sending', label: 'sending (공항샌딩)' },
    { name: 'sedan', label: 'sedan (세단)' },
    { name: '승용차', label: '승용차 (스포츠카)' },
    { name: 'suv', label: 'suv' },
    { name: 'van', label: 'van (벤)' },
    { name: 'minivan', label: 'minivan (승합차)' },
    { name: 'bus', label: 'bus (버스)' },
];
</script>

<template>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-3">
        <div
            v-for="icon in icons"
            :key="icon.name"
            class="flex flex-col items-center gap-2 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]"
        >
            <button type="button" class="icon-button" :title="icon.name" :aria-label="icon.label">
                <BaseIcon :name="icon.name" :size="18" />
            </button>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ icon.label }}</p>
        </div>
    </div>
</template>
