<script setup>
/**
 * 공용 확인(confirm) 다이얼로그 — 상태 변경 등 중요한 작업 전 사용한다.
 *
 * 사용 예:
 *   const confirmOpen = ref(false);
 *   <ConfirmDialog
 *       v-model:open="confirmOpen"
 *       title="운행 상태 변경"
 *       message="운행 상태를 '완료'로 변경할까요?"
 *       confirm-text="변경"
 *       type="primary"
 *       :loading="acting"
 *       @confirm="doSomething"
 *   />
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '확인' },
    message: { type: String, default: '' },
    confirmText: { type: String, default: '확인' },
    cancelText: { type: String, default: '취소' },
    // primary | success | info | warning | error
    type: { type: String, default: 'primary' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel', 'update:open']);
</script>

<template>
    <n-modal
        :show="props.open"
        preset="card"
        :title="props.title"
        :mask-closable="!props.loading"
        class="confirm-dialog"
        :on-update:show="(v) => emit('update:open', v)"
    >
        <p class="confirm-dialog__message">{{ props.message }}</p>
        <template #footer>
            <div class="confirm-dialog__footer">
                <n-button :disabled="props.loading" @click="emit('cancel')">
                    {{ props.cancelText }}
                </n-button>
                <n-button :type="props.type" :loading="props.loading" @click="emit('confirm')">
                    {{ props.confirmText }}
                </n-button>
            </div>
        </template>
    </n-modal>
</template>

<style scoped>
.confirm-dialog__message {
    margin: 0;
    color: var(--text);
    font-size: 11px;
    line-height: 1.6;
    white-space: pre-wrap;
}

.confirm-dialog__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
