<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import BaseIcon from './BaseIcon.vue';

const visible = ref(false);
let ticking = false;

const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
        visible.value = window.scrollY > 320;
        ticking = false;
    });
};

const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <transition name="scroll-top-fade">
        <button
            v-if="visible"
            type="button"
            class="scroll-top-btn"
            aria-label="맨 위로"
            @click="scrollTop"
        >
            <BaseIcon name="arrow-up" :size="20" />
        </button>
    </transition>
</template>

<style scoped>
.scroll-top-btn {
    position: fixed;
    right: 16px;
    bottom: calc(84px + env(safe-area-inset-bottom));
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    cursor: pointer;
}

.scroll-top-btn svg {
    width: 20px;
    height: 20px;
}

.scroll-top-fade-enter-active,
.scroll-top-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.scroll-top-fade-enter-from,
.scroll-top-fade-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
