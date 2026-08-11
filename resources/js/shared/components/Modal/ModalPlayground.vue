<script setup>
import { ref } from 'vue';

import BaseButton from '../Button/BaseButton.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseIcon from '../Icon/BaseIcon.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseModal from './BaseModal.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import FormGroup from '../Form/FormGroup.vue';

/**
 * 공용 모달(Modal) 플레이그라운드.
 *
 * 크기 데모 + 실사용 샘플(파일관리 / 폼 입력 / 삭제 확인 / 이미지 미리보기)을 제공한다.
 */
const openSizeDemo = ref(false);
const openSampleModal = ref(false);
const activeSize = ref('md');
const activeSample = ref('');
const sampleSearch = ref('');
const closeOnBackdrop = ref(true);

const formName = ref('');
const formEmail = ref('');
const formCategory = ref('');
const formMemo = ref('');
const agreeTerms = ref(false);

const sizes = [
    { key: 'sm', label: 'sm' },
    { key: 'md', label: 'md' },
    { key: 'lg', label: 'lg' },
    { key: 'xl', label: 'xl' },
];

const samples = [
    {
        key: 'file',
        title: '파일관리 (xl)',
        description: 'Toast Editor 이미지 파일관리와 동일한 구조 — 검색 툴바 + 목록 + 푸터',
    },
    {
        key: 'form',
        title: '폼 입력 (md)',
        description: '입력 필드를 모달 본문에 배치하고 저장/취소 푸터로 구성',
    },
    {
        key: 'confirm',
        title: '삭제 확인 (sm)',
        description: '파괴적 작업 전 확인 — 설명 문구 + 위험 버튼 푸터',
    },
    {
        key: 'preview',
        title: '이미지 미리보기 (lg)',
        description: '선택한 이미지의 크게 보기 + 메타 정보 + 다운로드',
    },
];

function openSize(size) {
    activeSize.value = size;
    openSizeDemo.value = true;
}

function openSample(key) {
    activeSample.value = key;
    openSampleModal.value = true;
}

function closeSample() {
    openSampleModal.value = false;
}
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">크기 데모</p>
            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                BaseModal — size: sm / md / lg / xl · ESC / 배경 클릭(옵션)으로 닫힘
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <BaseButton
                    v-for="size in sizes"
                    :key="size.key"
                    variant="secondary"
                    size="sm"
                    @click="openSize(size.key)"
                >
                    {{ size.key }} 모달 열기
                </BaseButton>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400" title="배경 클릭 시 닫기 여부">
                <input v-model="closeOnBackdrop" type="checkbox" class="h-4 w-4 accent-[#1f1f1f] dark:accent-[#d6d6dd]">
                배경 클릭으로 닫기 (해제하면 배경을 눌러도 닫히지 않습니다)
            </label>
        </div>

        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">실사용 샘플</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div
                    v-for="sample in samples"
                    :key="sample.key"
                    class="flex flex-col rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]"
                >
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ sample.title }}</p>
                    <p class="mt-1 flex-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ sample.description }}</p>
                    <div class="mt-3">
                        <BaseButton variant="secondary" size="sm" @click="openSample(sample.key)">샘플 열기</BaseButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- 크기 데모 모달 -->
        <BaseModal
            :open="openSizeDemo"
            :size="activeSize"
            :close-on-backdrop="closeOnBackdrop"
            aria-label="크기 데모 모달"
            eyebrow="NoWhere Component"
            :title="`${activeSize} 모달`"
            description="배경 클릭 또는 ESC 키로 닫을 수 있습니다. 하단 푸터는 공용 BaseButton으로 구성합니다."
            @close="openSizeDemo = false"
        >
            <div class="space-y-3">
                <FormGroup label="검색 예시" for-id="modal-playground-search">
                    <BaseInput
                        id="modal-playground-search"
                        v-model="sampleSearch"
                        placeholder="내용을 입력하세요"
                        title="검색 예시"
                        aria-label="검색 예시"
                    />
                </FormGroup>

                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                    modal__body 영역입니다. 패널 폭은 크기에 따라 달라집니다.
                </p>
            </div>

            <template #footer>
                <BaseButton variant="secondary" @click="openSizeDemo = false">취소</BaseButton>
                <BaseButton variant="primary" @click="openSizeDemo = false">확인</BaseButton>
            </template>
        </BaseModal>

        <!-- 샘플: 파일관리 (xl) -->
        <BaseModal
            v-if="activeSample === 'file'"
            :open="openSampleModal"
            size="xl"
            aria-label="이미지 파일관리 샘플"
            eyebrow="NoWhere File Manager"
            title="이미지 선택"
            description="업로드, 기존 이미지 선택, 검색을 한 번에 처리합니다. (실사용처: Toast Editor 파일관리)"
            @close="closeSample"
        >
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="min-w-[200px] flex-1">
                        <BaseInput v-model="sampleSearch" placeholder="파일명 또는 이름 검색" title="이미지 검색" aria-label="이미지 검색" />
                    </div>
                    <BaseButton variant="secondary" size="sm">
                        <BaseIcon name="upload" :size="16" />
                        업로드
                    </BaseButton>
                </div>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div v-for="n in 4" :key="n" class="rounded-[10px] border border-[#dddddd] bg-[#f3f3f3] p-2 dark:border-[#2a2a2a] dark:bg-[#202020]">
                        <div class="flex h-16 items-center justify-center rounded-[8px] bg-[#e7e7e7] text-gray-400 dark:bg-[#181818]">
                            <BaseIcon name="dashboard" :size="22" />
                        </div>
                        <p class="mt-2 truncate text-xs text-gray-600 dark:text-gray-400">sample-{{ n }}.png</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500">128.5 KB</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <BaseButton variant="secondary" @click="closeSample">취소</BaseButton>
                <BaseButton variant="primary" @click="closeSample">선택 완료</BaseButton>
            </template>
        </BaseModal>

        <!-- 샘플: 폼 입력 (md) -->
        <BaseModal
            v-else-if="activeSample === 'form'"
            :open="openSampleModal"
            size="md"
            aria-label="폼 입력 샘플"
            eyebrow="NoWhere Form"
            title="회원 등록"
            description="입력한 내용을 저장합니다."
            @close="closeSample"
        >
            <div class="space-y-4">
                <FormGroup label="이름" for-id="modal-sample-name" required>
                    <BaseInput id="modal-sample-name" v-model="formName" placeholder="이름을 입력하세요" />
                </FormGroup>

                <FormGroup label="이메일" for-id="modal-sample-email">
                    <BaseInput id="modal-sample-email" v-model="formEmail" type="email" placeholder="email@example.com" />
                </FormGroup>

                <FormGroup label="권한" for-id="modal-sample-category">
                    <BaseSelect id="modal-sample-category" v-model="formCategory" :options="[
                        { label: '관리자', value: 'admin' },
                        { label: '편집자', value: 'editor' },
                        { label: '뷰어', value: 'viewer' },
                    ]" />
                </FormGroup>

                <FormGroup label="메모" for-id="modal-sample-memo">
                    <BaseTextarea id="modal-sample-memo" v-model="formMemo" rows="3" placeholder="메모를 입력하세요" />
                </FormGroup>

                <BaseCheckbox v-model="agreeTerms" label="이용약관에 동의합니다" />
            </div>

            <template #footer>
                <BaseButton variant="secondary" @click="closeSample">취소</BaseButton>
                <BaseButton variant="primary" :disabled="!agreeTerms" @click="closeSample">저장</BaseButton>
            </template>
        </BaseModal>

        <!-- 샘플: 삭제 확인 (sm) -->
        <BaseModal
            v-else-if="activeSample === 'confirm'"
            :open="openSampleModal"
            size="sm"
            aria-label="삭제 확인 샘플"
            eyebrow="NoWhere Confirm"
            title="파일 삭제"
            description="삭제한 파일은 복구할 수 없습니다. 정말 삭제하시겠습니까?"
            @close="closeSample"
        >
            <template #footer>
                <BaseButton variant="secondary" @click="closeSample">취소</BaseButton>
                <BaseButton variant="danger" @click="closeSample">삭제</BaseButton>
            </template>
        </BaseModal>

        <!-- 샘플: 이미지 미리보기 (lg) -->
        <BaseModal
            v-else-if="activeSample === 'preview'"
            :open="openSampleModal"
            size="lg"
            aria-label="이미지 미리보기 샘플"
            eyebrow="NoWhere Gallery"
            title="이미지 미리보기"
            description="선택한 이미지의 미리보기와 메타 정보를 확인합니다."
            @close="closeSample"
        >
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px]">
                <div class="flex h-56 items-center justify-center rounded-[10px] border border-[#dddddd] bg-[#f3f3f3] dark:border-[#2a2a2a] dark:bg-[#202020]">
                    <BaseIcon name="view" :size="48" />
                </div>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">파일명</dt>
                        <dd class="truncate text-gray-900 dark:text-gray-100">hero-banner.png</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">크기</dt>
                        <dd class="text-gray-900 dark:text-gray-100">2.4 MB</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">유형</dt>
                        <dd class="text-gray-900 dark:text-gray-100">image/png</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">등록일</dt>
                        <dd class="text-gray-900 dark:text-gray-100">2026. 07. 30</dd>
                    </div>
                </dl>
            </div>

            <template #footer>
                <BaseButton variant="secondary" @click="closeSample">닫기</BaseButton>
                <BaseButton variant="primary" @click="closeSample">
                    <BaseIcon name="download" :size="16" />
                    다운로드
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>
