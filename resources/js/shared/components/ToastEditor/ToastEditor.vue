<script setup>
import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/i18n/ko-kr';
import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';
import '@toast-ui/editor/dist/toastui-editor.css';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

import { createFileManagerToolbarButton } from './plugins/Toolbar.js';
import './toast-editor.css';

const props = defineProps({
    autofocus: {
        type: Boolean,
        default: false,
    },
    height: {
        type: String,
        default: '420px',
    },
    hideModeSwitch: {
        type: Boolean,
        default: false,
    },
    initialEditType: {
        type: String,
        default: 'markdown',
    },
    inputId: {
        type: String,
        default: '',
    },
    inputName: {
        type: String,
        default: '',
    },
    language: {
        type: String,
        default: 'ko-KR',
    },
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '내용을 입력하세요.',
    },
    previewStyle: {
        type: String,
        default: 'vertical',
    },
    toolbarItems: {
        type: Array,
        default: () => [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'image', 'link'],
            ['code', 'codeblock'],
        ],
    },
    useFileManagerImageButton: {
        type: Boolean,
        default: false,
    },
    uploadHandler: {
        type: Function,
        default: null,
    },
    usageStatistics: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'blur',
    'change',
    'focus',
    'image-upload',
    'open-file-manager',
    'ready',
    'update:modelValue',
]);

const editorElement = ref(null);
const editorInstance = shallowRef(null);
const themeObserver = shallowRef(null);
const currentTheme = ref(resolveTheme());
const selectionSnapshot = ref(null);

const configSignature = computed(() => {
    return JSON.stringify({
        height: props.height,
        hideModeSwitch: props.hideModeSwitch,
        initialEditType: props.initialEditType,
        language: props.language,
        placeholder: props.placeholder,
        previewStyle: props.previewStyle,
        toolbarItems: props.toolbarItems,
        useFileManagerImageButton: props.useFileManagerImageButton,
        usageStatistics: props.usageStatistics,
    });
});

function resolveTheme() {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function emitCurrentMarkdown() {
    if (!editorInstance.value) {
        return;
    }

    const markdown = editorInstance.value.getMarkdown();

    emit('update:modelValue', markdown);
    emit('change', markdown);
}

function isFiniteSelectionOffset(value) {
    return typeof value === 'number' && Number.isFinite(value) && value >= 0;
}

function isValidSelectionPosition(position) {
    if (typeof position === 'number') {
        return isFiniteSelectionOffset(position);
    }

    if (Array.isArray(position) && position.length === 2) {
        return position.every(isFiniteSelectionOffset);
    }

    return false;
}

function isValidSelectionSnapshot(selection) {
    return Array.isArray(selection)
        && selection.length === 2
        && selection.every(isValidSelectionPosition);
}

function cloneSelectionSnapshot(selection) {
    if (!isValidSelectionSnapshot(selection)) {
        return null;
    }

    return selection.map((position) => (Array.isArray(position) ? [...position] : position));
}

function saveSelectionSnapshot() {
    if (!editorInstance.value || typeof editorInstance.value.getSelection !== 'function') {
        return;
    }

    const nextSelection = editorInstance.value.getSelection();

    if (!isValidSelectionSnapshot(nextSelection)) {
        return;
    }

    selectionSnapshot.value = cloneSelectionSnapshot(nextSelection);
}

async function handleImageUpload(blob, callback) {
    if (props.uploadHandler) {
        const uploadResult = await props.uploadHandler(blob);

        if (typeof uploadResult === 'string' && uploadResult !== '') {
            callback(uploadResult, blob.name || 'image');

            return false;
        }

        if (uploadResult && typeof uploadResult.url === 'string' && uploadResult.url !== '') {
            callback(uploadResult.url, uploadResult.altText || blob.name || 'image');

            return false;
        }
    }

    emit('image-upload', {
        blob,
        callback,
    });

    return false;
}

function destroyEditor() {
    if (!editorInstance.value) {
        return;
    }

    editorInstance.value.destroy();
    editorInstance.value = null;

    if (editorElement.value) {
        editorElement.value.innerHTML = '';
    }
}

function insertMarkdown(markdown) {
    if (!editorInstance.value || !markdown) {
        return;
    }

    editorInstance.value.focus();

    let restoredSelection = false;

    if (selectionSnapshot.value && typeof editorInstance.value.setSelection === 'function') {
        const nextSelection = cloneSelectionSnapshot(selectionSnapshot.value);

        if (nextSelection) {
            try {
                editorInstance.value.setSelection(nextSelection);
                restoredSelection = true;
            } catch {
                restoredSelection = false;
            }
        }
    }

    if (typeof editorInstance.value.insertText === 'function' && restoredSelection) {
        editorInstance.value.insertText(markdown);
    } else {
        editorInstance.value.setMarkdown(`${editorInstance.value.getMarkdown()}${markdown}`, false);
    }

    emitCurrentMarkdown();
}

function injectFileManagerToolbarItem(editor) {
    if (!props.useFileManagerImageButton || typeof document === 'undefined') {
        return;
    }

    const toolbarButton = createFileManagerToolbarButton({
        onClick: () => {
            saveSelectionSnapshot();
            emit('open-file-manager');
        },
    });

    editor.insertToolbarItem(
        {
            groupIndex: 3,
            itemIndex: 1,
        },
        {
            el: toolbarButton,
            name: 'nowhereFileManagerImage',
            tooltip: '이미지 파일관리',
        },
    );

    toolbarButton.parentElement?.classList.add('nowhere-editor-toolbar-item-wrapper');
}

async function rebuildEditor(markdown = props.modelValue) {
    destroyEditor();
    await nextTick();

    if (!editorElement.value) {
        return;
    }

    currentTheme.value = resolveTheme();

    const editor = new Editor({
        el: editorElement.value,
        height: props.height,
        hideModeSwitch: props.hideModeSwitch,
        hooks: {
            addImageBlobHook: (blob, callback) => handleImageUpload(blob, callback),
        },
        initialEditType: props.initialEditType,
        initialValue: markdown || '',
        language: props.language,
        placeholder: props.placeholder,
        previewStyle: props.previewStyle,
        theme: currentTheme.value,
        toolbarItems: props.toolbarItems,
        usageStatistics: props.usageStatistics,
    });

    injectFileManagerToolbarItem(editor);

    editor.on('change', () => {
        emitCurrentMarkdown();
    });

    editor.on('focus', () => {
        emit('focus');
    });

    editor.on('blur', () => {
        saveSelectionSnapshot();
        emit('blur');
    });

    editorInstance.value = editor;

    if (props.autofocus) {
        editor.focus();
    }

    emitCurrentMarkdown();
    emit('ready', editor);
}

function observeThemeChanges() {
    if (typeof MutationObserver === 'undefined' || typeof document === 'undefined') {
        return;
    }

    themeObserver.value = new MutationObserver(() => {
        const nextTheme = resolveTheme();

        if (nextTheme === currentTheme.value) {
            return;
        }

        const markdown = editorInstance.value?.getMarkdown() || props.modelValue;

        currentTheme.value = nextTheme;
        rebuildEditor(markdown);
    });

    themeObserver.value.observe(document.documentElement, {
        attributeFilter: ['class'],
        attributes: true,
    });
}

watch(
    () => props.modelValue,
    (nextValue) => {
        if (!editorInstance.value) {
            return;
        }

        const currentMarkdown = editorInstance.value.getMarkdown();

        if ((nextValue || '') === currentMarkdown) {
            return;
        }

        editorInstance.value.setMarkdown(nextValue || '', false);
    },
);

watch(configSignature, () => {
    if (!editorInstance.value) {
        return;
    }

    rebuildEditor(editorInstance.value.getMarkdown());
});

onMounted(() => {
    rebuildEditor();
    observeThemeChanges();
});

onBeforeUnmount(() => {
    themeObserver.value?.disconnect();
    destroyEditor();
});

defineExpose({
    insertMarkdown,
    saveSelectionSnapshot,
});
</script>

<template>
    <div class="toast-editor-module">
        <div ref="editorElement" class="toast-editor-surface"></div>

        <input
            v-if="inputName"
            :id="inputId || undefined"
            :name="inputName"
            :value="modelValue"
            type="hidden"
            hidden
            readonly
        >
    </div>
</template>
