<script setup>
import { computed } from 'vue';

const props = defineProps({
    decorative: {
        type: Boolean,
        default: true,
    },
    name: {
        type: String,
        required: true,
    },
    size: {
        type: [Number, String],
        default: 20,
    },
    strokeWidth: {
        type: [Number, String],
        default: 1.8,
    },
    title: {
        type: String,
        default: '',
    },
});

const normalizedName = computed(() => {
    return props.name.trim().toLowerCase();
});

const isIcon = (iconName) => {
    return normalizedName.value === iconName;
};
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
        :width="size"
        :height="size"
        :stroke-width="strokeWidth"
        :aria-hidden="decorative ? 'true' : undefined"
        :role="decorative ? undefined : 'img'"
    >
        <title v-if="!decorative && title">{{ title }}</title>

        <template v-if="isIcon('menu')">
            <path d="M4 7h16" />
            <path d="M4 12h16" />
            <path d="M4 17h16" />
        </template>

        <template v-else-if="isIcon('close')">
            <path d="M6 6l12 12" />
            <path d="M18 6l-12 12" />
        </template>

        <template v-else-if="isIcon('dashboard')">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
        </template>

        <template v-else-if="isIcon('bell')">
            <path d="M14.86 17H9.14a2 2 0 0 0-1.94 1.53L7 19h10l-.2-.47A2 2 0 0 0 14.86 17Z" />
            <path d="M18 11a6 6 0 1 0-12 0c0 2.18-.7 3.41-1.37 4.28-.32.42-.13 1.04.41 1.22h13.92c.54-.18.73-.8.41-1.22C18.7 14.41 18 13.18 18 11Z" />
        </template>

        <template v-else-if="isIcon('user')">
            <path d="M12 12a4 4 0 1 0 0-8a4 4 0 0 0 0 8Z" />
            <path d="M5 20a7 7 0 0 1 14 0" />
        </template>

        <template v-else-if="isIcon('logout')">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="M16 17l5-5l-5-5" />
            <path d="M21 12H9" />
        </template>

        <template v-else-if="isIcon('search')">
            <circle cx="11" cy="11" r="7" />
            <path d="M20 20l-3.5-3.5" />
        </template>

        <template v-else-if="isIcon('add') || isIcon('plus')">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
        </template>

        <template v-else-if="isIcon('upload')">
            <path d="M12 16V4" />
            <path d="M7 9l5-5l5 5" />
            <path d="M4 20h16" />
        </template>

        <template v-else-if="isIcon('download')">
            <path d="M12 4v12" />
            <path d="M7 11l5 5l5-5" />
            <path d="M4 20h16" />
        </template>

        <template v-else-if="isIcon('trash') || isIcon('delete')">
            <path d="M4 7h16" />
            <path d="M10 11v6" />
            <path d="M14 11v6" />
            <path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
            <path d="M9 7V4h6v3" />
        </template>

        <template v-else-if="isIcon('check')">
            <path d="M5 12l4.5 4.5L19 7" />
        </template>

        <template v-else-if="isIcon('chevron-left')">
            <path d="M15 18l-6-6l6-6" />
        </template>

        <template v-else-if="isIcon('chevron-right')">
            <path d="M9 18l6-6l-6-6" />
        </template>

        <template v-else-if="isIcon('eye') || isIcon('view')">
            <path d="M2.5 12s3.5-6 9.5-6s9.5 6 9.5 6s-3.5 6-9.5 6s-9.5-6-9.5-6Z" />
            <circle cx="12" cy="12" r="2.5" />
        </template>

        <template v-else-if="isIcon('settings')">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2a1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9a1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1a1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6a1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2a1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9a1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1a1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.4 1.9Z" />
        </template>

        <template v-else-if="isIcon('refresh')">
            <path d="M20 6v5h-5" />
            <path d="M4 18v-5h5" />
            <path d="M6.5 9A7 7 0 0 1 18 11" />
            <path d="M17.5 15A7 7 0 0 1 6 13" />
        </template>

        <template v-else-if="isIcon('copy') || isIcon('duplicate')">
            <rect x="9" y="9" width="11" height="11" rx="2" />
            <path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" />
        </template>
    </svg>
</template>
