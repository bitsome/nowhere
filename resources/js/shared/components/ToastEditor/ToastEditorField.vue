<script setup>
import { computed, ref, watch } from 'vue';

import FileManagerModal from './FileManagerModal.vue';
import ToastEditor from './ToastEditor.vue';
import { buildImageMarkdown } from './plugins/ImageUpload.js';

const props = defineProps({
    allowImages: {
        type: Boolean,
        default: false,
    },
    height: {
        type: String,
        default: '520px',
    },
    initialValue: {
        type: String,
        default: '',
    },
    modelValue: {
        type: String,
        default: null,
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
    uploadUrl: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '문서 내용을 입력하세요.',
    },
});

const emit = defineEmits(['update:modelValue']);

const markdown = ref(props.modelValue ?? props.initialValue ?? '');
const editorRef = ref(null);
const isFileManagerOpen = ref(false);

const toolbarItems = computed(() => {
    if (props.allowImages) {
        return [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'link'],
            ['code', 'codeblock'],
        ];
    }

    return [
        ['heading', 'bold', 'italic', 'strike'],
        ['hr', 'quote'],
        ['ul', 'ol', 'task', 'indent', 'outdent'],
        ['table', 'link'],
        ['code', 'codeblock'],
    ];
});

function openFileManager() {
    isFileManagerOpen.value = true;
}

function closeFileManager() {
    isFileManagerOpen.value = false;
}

function handleInsertImages(files) {
    const markdownText = buildImageMarkdown(files);

    if (!markdownText) {
        closeFileManager();

        return;
    }

    editorRef.value?.insertMarkdown(`\n${markdownText}\n`);
    closeFileManager();
}

watch(
    () => props.modelValue,
    (nextValue) => {
        if (nextValue === null || nextValue === markdown.value) {
            return;
        }

        markdown.value = nextValue;
    },
);

watch(markdown, (nextValue) => {
    emit('update:modelValue', nextValue);
});
</script>

<template>
    <div class="toast-editor-field">
        <ToastEditor
            ref="editorRef"
            v-model="markdown"
            :height="height"
            :input-id="inputId"
            :input-name="inputName"
            :placeholder="placeholder"
            :toolbar-items="toolbarItems"
            :use-file-manager-image-button="allowImages"
            hide-mode-switch
            @open-file-manager="openFileManager"
        />

        <FileManagerModal
            :open="isFileManagerOpen"
            :library-url="libraryUrl"
            :upload-url="uploadUrl"
            @close="closeFileManager"
            @insert="handleInsertImages"
        />
    </div>
</template>
