<script setup>
import { computed } from 'vue';

import './form.css';

const props = defineProps({
    autocomplete: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: '',
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    name: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
});

const emit = defineEmits(['blur', 'focus', 'input', 'update:modelValue']);

const classes = computed(() => [
    'form-control',
    'form-framework__control',
]);

function handleInput(event) {
    emit('update:modelValue', event.target.value);
    emit('input', event);
}
</script>

<template>
    <input
        :id="id || undefined"
        :aria-label="ariaLabel || title || placeholder || undefined"
        :autocomplete="autocomplete || undefined"
        :class="classes"
        :disabled="disabled"
        :name="name || undefined"
        :placeholder="placeholder"
        :readonly="readonly"
        :required="required"
        :title="title || undefined"
        :type="type"
        :value="modelValue"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
        @input="handleInput"
    >
</template>
