<script setup>
defineProps({
    description: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="shared-dropdown__header">
        <slot>
            <p v-if="title" class="shared-dropdown__header-title">
                {{ title }}
            </p>
            <p v-if="description" class="shared-dropdown__header-description">
                {{ description }}
            </p>
        </slot>
    </div>
</template>
