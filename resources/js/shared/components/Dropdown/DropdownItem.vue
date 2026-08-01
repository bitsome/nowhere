<script setup>
import { inject } from 'vue';

const props = defineProps({
    danger: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);

const dropdownContext = inject('shared-dropdown-context', null);

const handleClick = (event) => {
    if (props.disabled) {
        event.preventDefault();

        return;
    }

    emit('click', event);
    dropdownContext?.closeDropdown();
};
</script>

<template>
    <button
        type="button"
        class="shared-dropdown__item"
        :class="{
            'is-danger': danger,
            'is-disabled': disabled,
        }"
        :disabled="disabled"
        @click="handleClick"
    >
        <slot />
    </button>
</template>
