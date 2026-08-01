<script setup>
import Viewer from '@toast-ui/editor/viewer';
import '@toast-ui/editor/dist/i18n/ko-kr';
import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';
import '@toast-ui/editor/dist/toastui-editor-viewer.css';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

import './toast-editor.css';

const props = defineProps({
    content: {
        type: String,
        default: '',
    },
    language: {
        type: String,
        default: 'ko-KR',
    },
    usageStatistics: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['ready']);

const viewerElement = ref(null);
const viewerInstance = shallowRef(null);
const themeObserver = shallowRef(null);
const currentTheme = ref(resolveTheme());

const configSignature = computed(() => {
    return JSON.stringify({
        language: props.language,
        usageStatistics: props.usageStatistics,
    });
});

function resolveTheme() {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function destroyViewer() {
    if (!viewerInstance.value) {
        return;
    }

    viewerInstance.value.destroy();
    viewerInstance.value = null;

    if (viewerElement.value) {
        viewerElement.value.innerHTML = '';
    }
}

async function rebuildViewer(markdown = props.content) {
    destroyViewer();
    await nextTick();

    if (!viewerElement.value) {
        return;
    }

    currentTheme.value = resolveTheme();

    viewerInstance.value = new Viewer({
        el: viewerElement.value,
        initialValue: markdown || '',
        language: props.language,
        linkAttributes: {
            rel: 'noopener noreferrer',
            target: '_blank',
        },
        theme: currentTheme.value,
        usageStatistics: props.usageStatistics,
    });

    emit('ready', viewerInstance.value);
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

        currentTheme.value = nextTheme;
        rebuildViewer(props.content);
    });

    themeObserver.value.observe(document.documentElement, {
        attributeFilter: ['class'],
        attributes: true,
    });
}

watch(
    () => props.content,
    (nextValue) => {
        if (!viewerInstance.value) {
            return;
        }

        viewerInstance.value.setMarkdown(nextValue || '');
    },
);

watch(configSignature, () => {
    if (!viewerInstance.value) {
        return;
    }

    rebuildViewer(props.content);
});

onMounted(() => {
    rebuildViewer();
    observeThemeChanges();
});

onBeforeUnmount(() => {
    themeObserver.value?.disconnect();
    destroyViewer();
});
</script>

<template>
    <div class="toast-viewer-module">
        <div ref="viewerElement" class="toast-viewer-surface"></div>
    </div>
</template>
