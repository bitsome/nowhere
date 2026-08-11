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

const teleport = computed(() => dropdownContext?.teleport?.value ?? false);

const menuStyle = computed(() => dropdownContext?.menuPosition?.value ?? null);

const menuClass = computed(() => dropdownContext?.menuClass?.value ?? '');
</script>

<template>
    <Teleport v-if="teleport" to="body">
        <div
            v-if="menuVisible"
            :class="['shared-dropdown__menu', menuClass]"
            :style="menuStyle"
            @click.stop
        >
            <slot />
        </div>
    </Teleport>

    <div
        v-else-if="menuVisible"
        :class="['shared-dropdown__menu', menuClass]"
        @click.stop
    >
        <slot />
    </div>
</template>
