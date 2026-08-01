<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
});

const dropdownContext = inject('shared-dropdown-context', null);

const menuVisible = computed(() => {
    if (dropdownContext?.isOpen) {
        return dropdownContext.isOpen.value;
    }

    return props.isOpen;
});
</script>

<template>
    <div
        v-if="menuVisible"
        class="shared-dropdown__menu"
        @click.stop
    >
        <slot />
    </div>
</template>
