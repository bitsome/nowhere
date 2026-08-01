<script setup>
import { computed } from 'vue';

import './form.css';

const props = defineProps({
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
        type: [String, Number, Boolean],
        default: '',
    },
    name: {
        type: String,
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['blur', 'change', 'focus', 'update:modelValue']);

const normalizedOptions = computed(() => {
    return props.options.map((option) => {
        if (typeof option === 'object' && option !== null) {
            return {
                disabled: Boolean(option.disabled),
                label: option.label ?? option.value ?? '',
                value: option.value ?? '',
            };
        }

        return {
            disabled: false,
            label: option,
            value: option,
        };
    });
});

function handleChange(event) {
    emit('update:modelValue', event.target.value);
    emit('change', event);
}
</script>

<template>
    <select
        :id="id || undefined"
        :aria-label="ariaLabel || title || placeholder || undefined"
        class="form-select form-framework__control"
        :disabled="disabled"
        :name="name || undefined"
        :required="required"
        :title="title || undefined"
        :value="modelValue"
        @blur="$emit('blur', $event)"
        @change="handleChange"
        @focus="$emit('focus', $event)"
    >
        <option v-if="placeholder" value="" disabled>
            {{ placeholder }}
        </option>
        <option
            v-for="option in normalizedOptions"
            :key="`${option.value}-${option.label}`"
            :value="option.value"
            :disabled="option.disabled"
        >
            {{ option.label }}
        </option>
    </select>
</template>
