<script setup>
import './form.css';

defineProps({
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
        type: String,
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
    rows: {
        type: Number,
        default: 4,
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['blur', 'focus', 'input', 'update:modelValue']);

function handleInput(event) {
    emit('update:modelValue', event.target.value);
    emit('input', event);
}
</script>

<template>
    <textarea
        :id="id || undefined"
        :aria-label="ariaLabel || title || placeholder || undefined"
        class="form-framework__textarea"
        :disabled="disabled"
        :name="name || undefined"
        :placeholder="placeholder"
        :readonly="readonly"
        :required="required"
        :rows="rows"
        :title="title || undefined"
        :value="modelValue"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
        @input="handleInput"
    />
</template>
