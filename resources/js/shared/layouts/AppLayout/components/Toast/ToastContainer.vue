<script setup>
import SharedToastContainer from '../../../../components/Toast/ToastContainer.vue';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['close']);
</script>

<template>
    <SharedToastContainer :items="items" @close="$emit('close', $event)" />
</template>
