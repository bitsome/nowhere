<script setup>
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';

import './dropdown.css';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '240px',
    },
});

const emit = defineEmits(['close', 'open', 'toggle']);

const rootElement = ref(null);
const isOpen = ref(false);

const dropdownClasses = computed(() => [
    'shared-dropdown',
    props.align === 'left' ? 'is-left' : 'is-right',
    isOpen.value ? 'is-open' : '',
]);

const dropdownStyle = computed(() => ({
    '--dropdown-width': props.width,
}));

const openDropdown = () => {
    if (isOpen.value) {
        return;
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
});

onMounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div
        ref="rootElement"
        :class="dropdownClasses"
        :style="dropdownStyle"
    >
        <div class="shared-dropdown__trigger" @click.stop="toggleDropdown">
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
