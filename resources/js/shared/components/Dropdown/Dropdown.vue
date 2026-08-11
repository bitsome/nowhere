<script setup>
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';

import './dropdown.css';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    menuClass: {
        type: String,
        default: '',
    },
    teleport: {
        type: Boolean,
        default: false,
    },
    width: {
        type: String,
        default: '240px',
    },
});

const emit = defineEmits(['close', 'open', 'toggle']);

const rootElement = ref(null);
const triggerElement = ref(null);
const isOpen = ref(false);
const menuPosition = ref(null);

const dropdownClasses = computed(() => [
    'shared-dropdown',
    props.align === 'left' ? 'is-left' : 'is-right',
    isOpen.value ? 'is-open' : '',
]);

const dropdownStyle = computed(() => ({
    '--dropdown-width': props.width,
}));

const resolveTeleportPosition = () => {
    const rect = triggerElement.value?.getBoundingClientRect();

    if (!rect) {
        return null;
    }

    const position = {
        position: 'fixed',
        minWidth: '0',
        top: `${rect.bottom + 8}px`,
    };

    if (props.align === 'left') {
        position.left = `${rect.left}px`;
    } else {
        position.right = `${Math.max(0, window.innerWidth - rect.right)}px`;
    }

    return position;
};

const openDropdown = () => {
    if (isOpen.value) {
        return;
    }

    if (props.teleport) {
        menuPosition.value = resolveTeleportPosition();
    }

    if (typeof document !== 'undefined') {
        document.dispatchEvent(new CustomEvent('shared-dropdown:close'));
    }

    isOpen.value = true;
    emit('open');
    emit('toggle', true);
};

const closeDropdown = () => {
    if (!isOpen.value) {
        return;
    }

    isOpen.value = false;
    emit('close');
    emit('toggle', false);
};

const toggleDropdown = () => {
    if (isOpen.value) {
        closeDropdown();

        return;
    }

    openDropdown();
};

const handleDocumentClick = (event) => {
    if (!rootElement.value?.contains(event.target)) {
        closeDropdown();
    }
};

provide('shared-dropdown-context', {
    closeDropdown,
    isOpen,
    menuClass: computed(() => props.menuClass),
    menuPosition,
    teleport: computed(() => props.teleport),
});

onMounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('shared-dropdown:close', closeDropdown);
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('shared-dropdown:close', closeDropdown);
});
</script>

<template>
    <div
        ref="rootElement"
        :class="dropdownClasses"
        :style="dropdownStyle"
    >
        <div
            ref="triggerElement"
            class="shared-dropdown__trigger"
            @click.stop="toggleDropdown"
        >
            <slot
                name="trigger"
                :is-open="isOpen"
                :open="openDropdown"
                :close="closeDropdown"
                :toggle="toggleDropdown"
            />
        </div>

        <slot
            :is-open="isOpen"
            :open="openDropdown"
            :close="closeDropdown"
            :toggle="toggleDropdown"
        />
    </div>
</template>
