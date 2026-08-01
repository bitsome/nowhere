<script setup>
import { computed } from 'vue';

import './form.css';

const props = defineProps({
    as: {
        type: String,
        default: 'form',
    },
    noValidate: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['submit']);

const componentAttrs = computed(() => {
    if (props.as !== 'form') {
        return {};
    }

    return {
        novalidate: props.noValidate,
    };
});

function handleSubmit(event) {
    emit('submit', event);
}
</script>

<template>
    <component
        :is="as"
        v-bind="componentAttrs"
        class="form-framework"
        @submit="handleSubmit"
    >
        <slot />
    </component>
</template>
