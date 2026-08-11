<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';
import BaseButton from '../Button/BaseButton.vue';
import BaseIcon from '../Icon/BaseIcon.vue';

const props = defineProps({
    ariaLabel: {
        type: String,
        default: '',
    },
    cancelLabel: {
        type: String,
        default: '취소',
    },
    closeOnBackdrop: {
        type: Boolean,
        default: true,
    },
    confirmLabel: {
        type: String,
        default: '확인',
    },
    description: {
        type: String,
        default: '',
    },
    open: {
        type: Boolean,
        default: false,
    },
    showCancel: {
        type: Boolean,
        default: true,
    },
    size: {
        type: String,
        default: 'sm',
    },
    title: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'confirm',
    },
});

const emit = defineEmits(['close', 'confirm']);

function handleBackdropClick(event) {
    if (props.closeOnBackdrop && event.target === event.currentTarget) {
        emit('close');
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

function handleConfirm() {
    emit('confirm');
}

watch(
    () => props.open,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
);

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="dialog"
            role="alertdialog"
            aria-modal="true"
            :aria-label="ariaLabel || title"
            @click.self="handleBackdropClick"
        >
            <div class="dialog__panel" :class="`dialog__panel--${size}`" role="document">
                <header class="dialog__header">
                    <h3 class="dialog__title">{{ title }}</h3>

                    <button
                        type="button"
                        class="dialog__close"
                        title="닫기"
                        aria-label="닫기"
                        @click="emit('close')"
                    >
                        <BaseIcon name="close" :size="16" />
                    </button>
                </header>

                <div class="dialog__body">
                    <slot>
                        <p v-if="description" class="dialog__description">{{ description }}</p>
                    </slot>
                </div>

                <footer class="dialog__footer">
                    <slot name="footer">
                        <BaseButton
                            v-if="showCancel"
                            variant="secondary"
                            :title="cancelLabel"
                            @click="emit('close')"
                        >
                            {{ cancelLabel }}
                        </BaseButton>
                        <BaseButton
                            :variant="variant === 'danger' ? 'danger' : 'primary'"
                            :title="confirmLabel"
                            @click="handleConfirm"
                        >
                            {{ confirmLabel }}
                        </BaseButton>
                    </slot>
                </footer>
            </div>
        </div>
    </Teleport>
</template>
