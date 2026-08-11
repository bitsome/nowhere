<script setup>
import AuthBackground from './components/Background/AuthBackground.vue';
import AuthCard from './components/Card/AuthCard.vue';

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
    <div class="relative min-h-screen overflow-hidden bg-[#f3f3f3] px-4 py-6 text-[#1f1f1f] dark:bg-[#171717] dark:text-[#d6d6dd] lg:px-8">
        <AuthBackground />

        <div class="relative mx-auto flex min-h-[calc(100vh-3rem)] max-w-6xl items-center justify-center">
            <AuthCard :description="description" :title="title">
                <slot />
            </AuthCard>
        </div>
    </div>
</template>
