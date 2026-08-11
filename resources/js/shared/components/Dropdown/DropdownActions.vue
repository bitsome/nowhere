<script setup>
import BaseIcon from '../Icon/BaseIcon.vue';
import Dropdown from './Dropdown.vue';
import DropdownDivider from './DropdownDivider.vue';
import DropdownHeader from './DropdownHeader.vue';
import DropdownItem from './DropdownItem.vue';
import DropdownMenu from './DropdownMenu.vue';

/**
 * 공용 드롭다운 액션 메뉴 (데이터 주도).
 *
 * items 구조:
 * - { header: true, title, description } : 메뉴 상단 헤더
 * - { divider: true }                    : 구분선
 * - { label, icon, href, action, danger, disabled } : 메뉴 항목
 */
const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    iconSize: {
        type: Number,
        default: 20,
    },
    items: {
        type: Array,
        default: () => [],
    },
    menuClass: {
        type: String,
        default: '',
    },
    swapIconOnOpen: {
        type: Boolean,
        default: false,
    },
    teleport: {
        type: Boolean,
        default: false,
    },
    triggerClass: {
        type: String,
        default: 'btn-secondary',
    },
    triggerIcon: {
        type: String,
        default: 'menu',
    },
    triggerLabel: {
        type: String,
        default: '메뉴',
    },
    width: {
        type: String,
        default: '240px',
    },
});

const handleItem = (item) => {
    if (item.href) {
        window.location.href = item.href;

        return;
    }

    item.action?.();
};
</script>

<template>
    <Dropdown
        :align="align"
        :menu-class="menuClass"
        :teleport="teleport"
        :width="width"
    >
        <template #trigger="{ isOpen }">
            <button
                type="button"
                :class="triggerClass"
                :title="isOpen ? `${triggerLabel} 닫기` : `${triggerLabel} 열기`"
                :aria-label="isOpen ? `${triggerLabel} 닫기` : `${triggerLabel} 열기`"
                :aria-expanded="String(isOpen)"
            >
                <BaseIcon :name="swapIconOnOpen && isOpen ? 'close' : triggerIcon" :size="iconSize" />
            </button>
        </template>

        <template #default="{ isOpen }">
            <DropdownMenu :is-open="isOpen">
                <template v-for="(item, index) in items" :key="index">
                    <DropdownHeader
                        v-if="item.header"
                        :title="item.title"
                        :description="item.description"
                    />

                    <DropdownDivider v-else-if="item.divider" />

                    <DropdownItem
                        v-else
                        :danger="item.danger"
                        :disabled="item.disabled"
                        :title="item.label"
                        :aria-label="item.label"
                        @click="handleItem(item)"
                    >
                        <span class="shared-dropdown__item-content">
                            <BaseIcon v-if="item.icon" :name="item.icon" :size="18" />
                            <span>{{ item.label }}</span>
                        </span>
                    </DropdownItem>
                </template>
            </DropdownMenu>
        </template>
    </Dropdown>
</template>
