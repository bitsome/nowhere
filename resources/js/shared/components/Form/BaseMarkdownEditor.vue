<script setup>
import './form.css';

import ToastEditorField from '../ToastEditor/ToastEditorField.vue';

defineProps({
    allowImages: {
        type: Boolean,
        default: false,
    },
    height: {
        type: String,
        default: '520px',
    },
    inputId: {
        type: String,
        default: '',
    },
    inputName: {
        type: String,
        default: '',
    },
    libraryUrl: {
        type: String,
        default: '',
    },
    modelValue: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: '문서 내용을 입력하세요.',
    },
    uploadUrl: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="form-framework__markdown-editor" data-base-markdown-editor>
        <ToastEditorField
            :allow-images="allowImages"
            :height="height"
            :input-id="inputId"
            :input-name="inputName"
            :library-url="libraryUrl"
            :model-value="modelValue"
            :placeholder="placeholder"
            :upload-url="uploadUrl"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </div>
</template>
