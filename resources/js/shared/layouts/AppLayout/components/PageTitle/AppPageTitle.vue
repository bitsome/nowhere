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
    <div v-if="title || description">
        <h1 v-if="title" class="text-[28px] font-semibold text-[#202020] dark:text-[#d6d6dd]">
            {{ title }}
        </h1>
        <p v-if="description" class="mt-2 text-sm leading-6 text-[#6a6a6a] dark:text-[#9ea1a8]">
            {{ description }}
        </p>
    </div>
</template>
