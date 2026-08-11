<script setup>
import { computed, ref, watch } from 'vue';

import { destroyData, getData } from '../../api/index.js';
import BaseButton from '../Button/BaseButton.vue';
import BaseCard from '../Card/BaseCard.vue';
import BaseIcon from '../Icon/BaseIcon.vue';
import BaseModal from '../Modal/BaseModal.vue';
import FormGroup from '../Form/FormGroup.vue';
import BaseInput from '../Form/BaseInput.vue';
import { uploadImagesToFileManager } from './plugins/ImageUpload.js';

const props = defineProps({
    deleteEnabled: {
        type: Boolean,
        default: true,
    },
    libraryUrl: {
        type: String,
        default: '',
    },
    open: {
        type: Boolean,
        default: false,
    },
    uploadUrl: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'insert']);

const files = ref([]);
const isLoading = ref(false);
const isUploading = ref(false);
const searchKeyword = ref('');
const selectedIds = ref([]);
const uploadInput = ref(null);

let searchTimer = null;

const selectedFiles = computed(() => {
    return files.value.filter((file) => selectedIds.value.includes(file.id));
});

const canInsert = computed(() => {
    return selectedFiles.value.length > 0;
});

const dropzoneClass = computed(() => [
    'nowhere-file-manager-modal__dropzone',
    isUploading.value ? 'is-disabled' : '',
]);

function resetState() {
    selectedIds.value = [];
    searchKeyword.value = '';
}

function closeModal() {
    emit('close');
}

function isSelected(fileId) {
    return selectedIds.value.includes(fileId);
}

function toggleSelection(fileId) {
    if (isSelected(fileId)) {
        selectedIds.value = selectedIds.value.filter((selectedId) => selectedId !== fileId);

        return;
    }

    selectedIds.value = [...selectedIds.value, fileId];
}

function clearSelection() {
    selectedIds.value = [];
}

async function fetchLibrary() {
    if (!props.libraryUrl) {
        files.value = [];

        return;
    }

    isLoading.value = true;

    try {
        const response = await getData(props.libraryUrl, {
            params: {
                search: searchKeyword.value,
            },
        });

        files.value = Array.isArray(response.files) ? response.files : [];
        selectedIds.value = selectedIds.value.filter((selectedId) => {
            return files.value.some((file) => file.id === selectedId);
        });
    } finally {
        isLoading.value = false;
    }
}

async function handleFiles(fileList) {
    if (!props.uploadUrl || !fileList?.length) {
        return;
    }

    isUploading.value = true;

    try {
        const imageFiles = Array.from(fileList).filter((file) => file.type.startsWith('image/'));

        if (!imageFiles.length) {
            return;
        }

        const uploadedFiles = await uploadImagesToFileManager(imageFiles, props.uploadUrl);

        files.value = [...uploadedFiles, ...files.value.filter((file) => {
            return !uploadedFiles.some((uploadedFile) => uploadedFile.id === file.id);
        })];

        selectedIds.value = [
            ...new Set([
                ...uploadedFiles.map((file) => file.id),
                ...selectedIds.value,
            ]),
        ];
    } finally {
        isUploading.value = false;

        if (uploadInput.value) {
            uploadInput.value.value = '';
        }
    }
}

function openUploadDialog() {
    uploadInput.value?.click();
}

function handleUploadChange(event) {
    handleFiles(event.target.files);
}

function handleDrop(event) {
    event.preventDefault();

    if (isUploading.value) {
        return;
    }

    handleFiles(event.dataTransfer?.files);
}

function handleDragOver(event) {
    event.preventDefault();
}

async function handleDelete(file) {
    if (!props.deleteEnabled || !file?.delete_url) {
        return;
    }

    if (!window.confirm('선택한 이미지를 삭제하시겠습니까?')) {
        return;
    }

    await destroyData(file.delete_url);

    files.value = files.value.filter((currentFile) => currentFile.id !== file.id);
    selectedIds.value = selectedIds.value.filter((selectedId) => selectedId !== file.id);
}

function handleInsert() {
    emit('insert', selectedFiles.value);
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            resetState();

            return;
        }

        fetchLibrary();
    },
);

watch(searchKeyword, () => {
    if (!props.open) {
        return;
    }

    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        fetchLibrary();
    }, 250);
});
</script>

<template>
    <BaseModal
        :open="open"
        size="xl"
        aria-label="이미지 파일관리"
        eyebrow="NoWhere File Manager"
        title="이미지 선택"
        description="업로드, 기존 이미지 선택, 검색, 삭제를 한 번에 처리하고 선택한 이미지를 Markdown으로 삽입합니다."
        close-label="이미지 파일관리 닫기"
        @close="closeModal"
    >
        <div class="nowhere-file-manager-modal__toolbar">
                    <FormGroup
                        label="이미지 검색"
                        for-id="file-manager-modal-search"
                        class="nowhere-file-manager-modal__search"
                    >
                        <BaseInput
                            id="file-manager-modal-search"
                            v-model="searchKeyword"
                            placeholder="파일명 또는 이름 검색"
                            title="이미지 검색"
                            aria-label="이미지 검색"
                        />
                    </FormGroup>

                    <div class="nowhere-file-manager-modal__toolbar-actions">
                        <BaseButton
                            variant="secondary"
                            class="nowhere-file-manager-modal__icon-button"
                            title="선택 초기화"
                            aria-label="선택 초기화"
                            :disabled="!selectedIds.length"
                            @click="clearSelection"
                        >
                            <BaseIcon name="refresh" :size="16" />
                        </BaseButton>

                        <BaseButton
                            class="nowhere-file-manager-modal__icon-button"
                            title="이미지 업로드"
                            aria-label="이미지 업로드"
                            :disabled="isUploading"
                            @click="openUploadDialog"
                        >
                            <BaseIcon :name="isUploading ? 'refresh' : 'upload'" :size="16" />
                        </BaseButton>
                    </div>
                </div>

                <input
                    ref="uploadInput"
                    type="file"
                    multiple
                    accept="image/*"
                    hidden
                    @change="handleUploadChange"
                >

                <div
                    :class="dropzoneClass"
                    title="이미지 드래그 앤 드롭"
                    aria-label="이미지 드래그 앤 드롭"
                    @drop="handleDrop"
                    @dragover="handleDragOver"
                >
                    <p class="nowhere-file-manager-modal__dropzone-title">다중 이미지 업로드</p>
                    <p class="nowhere-file-manager-modal__dropzone-description">
                        여러 장을 한 번에 올리거나, 기존 이미지 중 필요한 항목만 선택할 수 있습니다.
                    </p>
                </div>

                <div class="nowhere-file-manager-modal__meta">
                    <span class="meta-badge">전체 {{ files.length }}개</span>
                    <span class="meta-badge">선택 {{ selectedIds.length }}개</span>
                </div>

                <div class="nowhere-file-manager-modal__content">
                    <div v-if="isLoading" class="nowhere-file-manager-modal__empty">
                        이미지 목록을 불러오는 중입니다.
                    </div>

                    <div v-else-if="!files.length" class="nowhere-file-manager-modal__empty">
                        선택 가능한 이미지가 없습니다.
                    </div>

                    <div v-else class="nowhere-file-manager-modal__grid">
                        <BaseCard
                            v-for="file in files"
                            :key="file.id"
                            :surface="isSelected(file.id) ? 'muted' : 'default'"
                            :padded="false"
                            class="nowhere-file-manager-modal__file-card"
                        >
                            <button
                                type="button"
                                class="nowhere-file-manager-modal__file-select"
                                :title="`${file.file_name} 선택`"
                                :aria-label="`${file.file_name} 선택`"
                                @click="toggleSelection(file.id)"
                            >
                                <img
                                    :src="file.url"
                                    :alt="file.name || file.file_name"
                                    class="nowhere-file-manager-modal__image"
                                >
                            </button>

                            <div class="nowhere-file-manager-modal__file-body">
                                <div class="nowhere-file-manager-modal__file-text">
                                    <p class="nowhere-file-manager-modal__file-name">{{ file.file_name }}</p>
                                    <p class="nowhere-file-manager-modal__file-meta">
                                        {{ file.collection_name }} · {{ file.created_at || '업로드 시간 없음' }}
                                    </p>
                                </div>

                                <div class="nowhere-file-manager-modal__file-actions">
                                    <BaseButton
                                        variant="secondary"
                                        size="sm"
                                        class="nowhere-file-manager-modal__icon-button nowhere-file-manager-modal__icon-button--sm"
                                        :title="isSelected(file.id) ? '선택 해제' : '선택'"
                                        :aria-label="isSelected(file.id) ? '선택 해제' : '선택'"
                                        @click="toggleSelection(file.id)"
                                    >
                                        <BaseIcon :name="isSelected(file.id) ? 'close' : 'check'" :size="14" />
                                    </BaseButton>

                                    <BaseButton
                                        v-if="deleteEnabled"
                                        variant="ghost"
                                        size="sm"
                                        class="nowhere-file-manager-modal__icon-button nowhere-file-manager-modal__icon-button--sm"
                                        title="이미지 삭제"
                                        aria-label="이미지 삭제"
                                        @click="handleDelete(file)"
                                    >
                                        <BaseIcon name="trash" :size="14" />
                                    </BaseButton>
                                </div>
                            </div>
                        </BaseCard>
                    </div>
                </div>

                <template #footer>
                    <p class="nowhere-file-manager-modal__footer-text">
                        선택한 이미지는 Markdown 여러 줄로 자동 삽입됩니다.
                    </p>

                    <div class="nowhere-file-manager-modal__footer-actions">
                        <BaseButton
                            variant="secondary"
                            class="nowhere-file-manager-modal__icon-button"
                            title="모달 닫기"
                            aria-label="모달 닫기"
                            @click="closeModal"
                        >
                            <BaseIcon name="close" :size="18" />
                        </BaseButton>

                        <BaseButton
                            title="선택 이미지 삽입"
                            aria-label="선택 이미지 삽입"
                            :disabled="!canInsert"
                            @click="handleInsert"
                        >
                            <BaseIcon name="check" :size="16" />
                            <span>삽입</span>
                        </BaseButton>
                    </div>
                </template>
    </BaseModal>
</template>
