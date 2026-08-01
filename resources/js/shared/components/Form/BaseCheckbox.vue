<script setup>
import { computed } from 'vue';

import './form.css';

const props = defineProps({
    ariaLabel: {
        type: String,
        default: '',
    },
    checkedValue: {
        type: [String, Number, Boolean],
        default: true,
    },
    description: {
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
    label: {
        type: String,
        default: '',
    },
    modelValue: {
        type: [String, Number, Boolean],
        default: false,
    },
    name: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
    uncheckedValue: {
        type: [String, Number, Boolean],
        default: false,
    },
});

const emit = defineEmits(['change', 'update:modelValue']);

const isChecked = computed(() => props.modelValue === props.checkedValue);

function handleChange(event) {
    emit('update:modelValue', event.target.checked ? props.checkedValue : props.uncheckedValue);
    emit('change', event);
}
</script>

<template>
    <label
        class="form-framework__checkbox"
        :for="id || undefined"
        :title="title || undefined"
    >
        <input
            :id="id || undefined"
            :aria-label="ariaLabel || title || label || undefined"
            class="form-framework__checkbox-input"
            type="checkbox"
            :checked="isChecked"
            :disabled="disabled"
            :name="name || undefined"
            @change="handleChange"
        >

        <span class="form-framework__checkbox-body">
            <span v-if="label" class="form-framework__checkbox-label">{{ label }}</span>
            <span v-if="description" class="form-framework__checkbox-description">{{ description }}</span>
        </span>
    </label>
</template>
