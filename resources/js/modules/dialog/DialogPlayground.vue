<script setup>
import { ref } from 'vue';
import BaseButton from '../../shared/components/Button/BaseButton.vue';
import BaseDialog from '../../shared/components/Dialog/BaseDialog.vue';

const lastAction = ref('아직 다이얼로그를 열지 않았습니다.');
const confirmOpen = ref(false);
const dangerOpen = ref(false);
const closeOnBackdrop = ref(true);

function handleConfirm() {
    lastAction.value = '확인 버튼을 눌렀습니다.';
    confirmOpen.value = false;
}

function handleCancel() {
    lastAction.value = '취소 버튼을 눌렀습니다.';
    confirmOpen.value = false;
}

function handleDangerConfirm() {
    lastAction.value = '위험 작업을 확인했습니다.';
    dangerOpen.value = false;
}

function handleDangerCancel() {
    lastAction.value = '위험 작업을 취소했습니다.';
    dangerOpen.value = false;
}
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <div class="page-panel">
            <h3 class="text-sm font-semibold">확인 다이얼로그</h3>
            <p class="mt-1 text-sm text-gray-500">설명 문구와 취소/확인 버튼을 제공합니다.</p>

            <div class="mt-4 flex items-center gap-2">
                <BaseButton variant="primary" title="확인 다이얼로그 열기" @click="confirmOpen = true">
                    확인 다이얼로그 열기
                </BaseButton>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm text-gray-600" title="배경 클릭 시 닫기 여부">
                <input v-model="closeOnBackdrop" type="checkbox" class="h-4 w-4 accent-[#1f1f1f] dark:accent-[#d6d6dd]">
                배경 클릭으로 닫기
            </label>

            <p class="mt-4 text-sm text-gray-600">{{ lastAction }}</p>
        </div>

        <div class="page-panel">
            <h3 class="text-sm font-semibold">위험(Danger) 다이얼로그</h3>
            <p class="mt-1 text-sm text-gray-500">파괴적 작업 확인 시 확인 버튼이 강조됩니다.</p>

            <div class="mt-4 flex items-center gap-2">
                <BaseButton variant="danger" title="삭제 확인 다이얼로그 열기" @click="dangerOpen = true">
                    삭제 확인
                </BaseButton>
            </div>

            <p class="mt-4 text-sm text-gray-600">{{ lastAction }}</p>
        </div>

        <BaseDialog
            :open="confirmOpen"
            :close-on-backdrop="closeOnBackdrop"
            title="변경 사항 저장"
            description="입력한 내용을 저장할까요?"
            confirm-label="저장"
            @confirm="handleConfirm"
            @close="handleCancel"
        />

        <BaseDialog
            :open="dangerOpen"
            title="회원 삭제"
            description="삭제한 회원은 복구할 수 없습니다. 정말 삭제할까요?"
            variant="danger"
            confirm-label="삭제"
            @confirm="handleDangerConfirm"
            @close="handleDangerCancel"
        />
    </div>
</template>
