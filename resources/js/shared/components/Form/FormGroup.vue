<script setup>
import { computed } from 'vue';

import './form.css';

const props = defineProps({
    description: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    forId: {
        type: String,
        default: '',
    },
    inline: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
});

const classes = computed(() => [
    'form-framework__group',
    props.inline ? 'form-framework__group--inline' : '',
]);
</script>

<template>
    <div :class="classes">
        <div v-if="label || description || $slots.label" class="form-framework__head">
            <slot name="label">
                <label
                    v-if="label"
                    class="form-framework__label"
                    :for="forId || undefined"
                >
                    <span>{{ label }}</span>
                    <span v-if="required" class="form-framework__required">필수</span>
                </label>
            </slot>

            <p v-if="description" class="form-framework__description">
                {{ description }}
            </p>
        </div>

        <div class="form-framework__body">
            <slot />

            <p v-if="error" class="form-framework__error">
                {{ error }}
            </p>
        </div>
    </div>
</template>
