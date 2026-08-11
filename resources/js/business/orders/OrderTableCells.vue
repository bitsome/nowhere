<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import BaseIcon from '../../shared/components/Icon/BaseIcon.vue';

const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    order: {
        type: Object,
        required: true,
    },
});

const VEHICLE_ICON_RULES = [
    { icon: 'bus', keywords: ['버스', 'bus'] },
    { icon: 'suv', keywords: ['싼타페', '모하비', '투싼', '스포티지', '쏘렌토', 'suv'] },
    { icon: 'van', keywords: ['스타렉스', '카고', '벤', 'van'] },
    { icon: 'sedan', keywords: ['소나타', '그랜저', '아반떼', '세단', 'sedan'] },
    { icon: 'minivan', keywords: ['카니발', '스타리아', '승합', 'minivan'] },
];

const vehicleIcon = () => {
    const vehicle = String(props.order.vehicle || '').toLowerCase();
    const matched = VEHICLE_ICON_RULES.find((rule) => rule.keywords.some((keyword) => vehicle.includes(keyword)));

    return matched ? matched.icon : 'minivan';
};

const goToOrder = () => {
    if (props.order.showUrl) {
        window.location.href = props.order.showUrl;
    }
};

const statusClass = (label) => ({
    'status-badge--completed': label === '완료',
    'status-badge--settled': label === '정산',
    'status-badge--active': label === '운행중',
});

const actionsOpen = ref(false);

const toggleActions = () => {
    actionsOpen.value = !actionsOpen.value;
};

const closeActions = () => {
    actionsOpen.value = false;
};

onMounted(() => document.addEventListener('click', closeActions));
onBeforeUnmount(() => document.removeEventListener('click', closeActions));
</script>

<template>
    <td
        v-for="column in columns"
        :key="column.key"
        class="datatable-cell"
        :class="{
            'text-center': column.align === 'center',
            'text-right': column.align === 'right',
        }"
    >
        <template v-if="column.key === 'service'">
            <div class="flex items-center gap-2">
                <BaseIcon
                    v-if="order.serviceIcon"
                    :name="order.serviceIcon"
                    :size="16"
                    class="flex-shrink-0 text-gray-400 dark:text-gray-500"
                />
                <p class="min-w-0 text-sm text-gray-600 dark:text-gray-300">{{ order.serviceLabel }}</p>
            </div>
        </template>

        <template v-else-if="column.key === 'route'">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ order.route }}</p>
            <p v-if="order.flightNumber" class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">항공편 {{ order.flightNumber }}</p>
        </template>

        <template v-else-if="column.key === 'pickupDateTime'">
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ order.pickupDateTime }}</p>
        </template>

        <template v-else-if="column.key === 'vehicle'">
            <div class="flex items-center gap-2">
                <BaseIcon
                    :name="vehicleIcon()"
                    :size="16"
                    class="flex-shrink-0 text-gray-400 dark:text-gray-500"
                />
                <p class="min-w-0 truncate text-sm text-gray-600 dark:text-gray-300">{{ order.vehicle }}</p>
            </div>
        </template>

        <template v-else-if="column.key === 'passenger'">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ order.passengerCount ? `${order.passengerCount}명` : '-' }}
            </p>
        </template>

        <template v-else-if="column.key === 'amount'">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ order.amount }}</p>
        </template>

        <template v-else-if="column.key === 'status'">
            <span class="status-badge" :class="statusClass(order.statusLabel)">{{ order.statusLabel }}</span>
        </template>

        <template v-else-if="column.key === 'actions'">
            <div class="order-table-actions">
                <button
                    type="button"
                    class="icon-button order-table-actions__toggle"
                    :class="{ 'is-open': actionsOpen }"
                    title="오더 액션"
                    aria-label="오더 액션"
                    :aria-expanded="String(actionsOpen)"
                    @click.stop="toggleActions"
                >
                    <BaseIcon name="ellipsis-vertical" :size="16" />
                </button>
                <div
                    v-if="actionsOpen"
                    class="menu-panel order-table-actions__menu"
                    data-order-row-actions
                >
                    <a
                        href="#"
                        class="menu-item"
                        title="상세보기"
                        @click.prevent="goToOrder"
                    >
                        <BaseIcon name="view" :size="16" />
                        상세보기
                    </a>
                </div>
            </div>
        </template>
    </td>
</template>
