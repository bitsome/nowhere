<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';

import BaseButton from '../Button/BaseButton.vue';
import BaseIcon from '../Icon/BaseIcon.vue';

const props = defineProps({
    ariaLabel: {
        type: String,
        default: '모달',
    },
    closeLabel: {
        type: String,
        default: '닫기',
    },
    closeOnBackdrop: {
        type: Boolean,
        default: true,
    },
    description: {
        type: String,
        default: '',
    },
    eyebrow: {
        type: String,
        default: '',
    },
    open: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'md',
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

function closeModal() {
    emit('close');
}

function handleBackdropClick(event) {
    if (props.closeOnBackdrop && event.target === event.currentTarget) {
        closeModal();
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        closeModal();
    }
}

watch(
    () => props.open,
    (isOpen) => {
        document.body.classList.toggle('overflow-hidden', isOpen);
    },
);

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="modal" @click.self="handleBackdropClick">
            <div class="modal__backdrop" @click.self="handleBackdropClick"></div>

            <section
                class="modal__panel"
                :class="[`modal__panel--${size}`]"
                role="dialog"
                aria-modal="true"
                :aria-label="ariaLabel"
            >
                <header class="modal__header">
                    <div>
                        <p v-if="eyebrow" class="modal__eyebrow">{{ eyebrow }}</p>
                        <h3 v-if="title" class="modal__title">{{ title }}</h3>
                        <p v-if="description" class="modal__description">{{ description }}</p>
                    </div>

                    <slot name="header-actions">
                        <BaseButton
                            variant="ghost"
                            class="modal__close-button"
                            :title="closeLabel"
                            :aria-label="closeLabel"
                            @click="closeModal"
                        >
                            <BaseIcon name="close" :size="18" />
                        </BaseButton>
                    </slot>
                </header>

                <div class="modal__body">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="modal__footer">
                    <slot name="footer" />
                </footer>
            </section>
        </div>
    </Teleport>
</template>
