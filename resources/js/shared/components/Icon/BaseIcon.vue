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

        <template v-else-if="isIcon('list')">
            <path d="M8 6h13" />
            <path d="M8 12h13" />
            <path d="M8 18h13" />
            <path d="M3.5 6h.01" />
            <path d="M3.5 12h.01" />
            <path d="M3.5 18h.01" />
        </template>

        <template v-else-if="isIcon('grid')">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
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

        <template v-else-if="isIcon('chevron-down')">
            <path d="M6 9l6 6l6-6" />
        </template>

        <template v-else-if="isIcon('chevron-up')">
            <path d="M6 15l6-6l6 6" />
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

        <template v-else-if="isIcon('edit') || isIcon('pencil')">
            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
        </template>

        <template v-else-if="isIcon('ellipsis-vertical') || isIcon('more-vertical')">
            <circle cx="12" cy="5" r="1.6" />
            <circle cx="12" cy="12" r="1.6" />
            <circle cx="12" cy="19" r="1.6" />
        </template>

        <template v-else-if="isIcon('shield')">
            <path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10Z" />
        </template>

        <template v-else-if="isIcon('pickup')">
            <!-- 공항 픽업(도착): 좌하향 하강 비행기 -->
            <path d="M2 22l11-11" />
            <path d="M2 22l7-20l4 9l9 4z" />
        </template>

        <template v-else-if="isIcon('sending')">
            <!-- 공항 샌딩(출발): 우상향 상승 비행기 -->
            <path d="M22 2L11 13" />
            <path d="M22 2l-7 20l-4-9l-9-4z" />
        </template>

        <template v-else-if="isIcon('landing')">
            <!-- 공항 랜딩(도착): 좌하향 하강 비행기 -->
            <path d="M2 22l11-11" />
            <path d="M2 22l7-20l4 9l9 4z" />
        </template>

        <template v-else-if="isIcon('sedan')">
            <!-- 세단: 지붕 덮개 없이 낮춘 차체 + 뒷창문 바퀴 도형 -->
            <path d="M3 17.5v-3.5a3 3 0 0 1 .9-2.1l.6-.6a3 3 0 0 1 2.1-.9h9.8a3 3 0 0 1 2.1.9l.6.6a3 3 0 0 1 .9 2.1v3.5" />
            <path d="M9.5 10.3V8.6a1.4 1.4 0 0 1 1.4-1.4h1.2a1.4 1.4 0 0 1 1.4 1.4v1.7" />
            <circle cx="9" cy="13" r="1.5" />
            <circle cx="7" cy="17" r="1.8" />
            <circle cx="17" cy="17" r="1.8" />
        </template>

        <template v-else-if="isIcon('승용차') || isIcon('sports-car')">
            <!-- 승용차(택시형): 일반 승용차 + 지붕 택시 사인 -->
            <path d="M3 17.5v-3.5a3 3 0 0 1 .9-2.1l.6-.6a3 3 0 0 1 2.1-.9h9.8a3 3 0 0 1 2.1.9l.6.6a3 3 0 0 1 .9 2.1v3.5" />
            <path d="M9.5 10.3V8.6a1.4 1.4 0 0 1 1.4-1.4h1.2a1.4 1.4 0 0 1 1.4 1.4v1.7" />
            <rect x="10.5" y="4.9" width="3" height="2" rx="0.6" />
            <path d="M11.2 6.4h1.6" />
            <circle cx="7" cy="17" r="1.8" />
            <circle cx="17" cy="17" r="1.8" />
        </template>

        <template v-else-if="isIcon('suv')">
            <!-- SUV: 높은 루프 + 박스형 차체 -->
            <path d="M3.5 17V9.5A1.5 1.5 0 0 1 5 8h9a1.5 1.5 0 0 1 1.2.6L18.5 13H20a1.5 1.5 0 0 1 1.5 1.5V17" />
            <path d="M8 8V6a1.5 1.5 0 0 1 1.5-1.5h2A1.5 1.5 0 0 1 13 6v2" />
            <circle cx="8" cy="17" r="1.8" />
            <path d="M10 17h4" />
            <circle cx="16" cy="17" r="1.8" />
        </template>

        <template v-else-if="isIcon('van')">
            <!-- 벤(화물): 직립 박스형 차체 -->
            <path d="M3.5 17V8.5A1.5 1.5 0 0 1 5 7h9.5a1.5 1.5 0 0 1 1.1.5l3 3.5H19a1.5 1.5 0 0 1 1.5 1.5V17" />
            <path d="M7 17h10" />
            <circle cx="7.5" cy="17" r="1.8" />
            <circle cx="16.5" cy="17" r="1.8" />
        </template>

        <template v-else-if="isIcon('minivan')">
            <!-- 승합차(카니발): 긴 루프 + 긴 차체 + 측면 창문 -->
            <path d="M2.5 16.5V9a2 2 0 0 1 2-2h12.5a2 2 0 0 1 1.6.8l2.1 2.7a1.5 1.5 0 0 1 .3.9v5.1" />
            <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h4a1.5 1.5 0 0 1 1.5 1.5V7" />
            <path d="M8 11.5h8.5" />
            <circle cx="6.5" cy="16.5" r="1.8" />
            <circle cx="17.5" cy="16.5" r="1.8" />
        </template>

        <template v-else-if="isIcon('bus')">
            <!-- 버스: 긴 차체 + 창문 분할 -->
            <path d="M4 17V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v11" />
            <path d="M4 10.5h16" />
            <path d="M7.5 8.5v2" />
            <path d="M11 8.5v2" />
            <path d="M14.5 8.5v2" />
            <circle cx="7.5" cy="17" r="1.8" />
            <circle cx="16.5" cy="17" r="1.8" />
        </template>
    </svg>
</template>
