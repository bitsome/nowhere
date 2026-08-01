<script setup>
const props = defineProps({
    count: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <span
        v-if="count > 0"
        class="notification-badge"
        aria-hidden="true"
    ></span>
</template>
