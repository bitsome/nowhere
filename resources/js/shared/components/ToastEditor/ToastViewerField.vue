<script setup>
import ToastViewer from './ToastViewer.vue';

defineProps({
    content: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="toast-viewer-field">
        <ToastViewer :content="content" />
    </div>
</template>
