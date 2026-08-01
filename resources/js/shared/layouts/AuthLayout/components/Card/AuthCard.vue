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
    <section class="w-full max-w-xl rounded-[10px] border border-[#d6d6d6] bg-[#efefef] p-4 dark:border-[#2a2a2a] dark:bg-[#181818]">
        <div v-if="title || description" class="mb-6">
            <h1 v-if="title" class="text-xl font-semibold text-[#202020] dark:text-[#d6d6dd]">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 text-sm leading-6 text-[#6a6a6a] dark:text-[#9ea1a8]">
                {{ description }}
            </p>
        </div>

        <slot />
    </section>
</template>
