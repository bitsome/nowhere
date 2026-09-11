<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import { useDialog, useMessage } from 'naive-ui';
import { useChatsStore } from '../../stores/chats';
import { apiChatImageArchive, apiChatImageDelete, apiChatImageUpload } from '../../api/chats';
import { getApiErrorMessage } from '../../api/client';
import { resizeImage } from '../../utils/imageResize';
import Sortable from 'sortablejs';
import BaseIcon from '../common/BaseIcon.vue';
import ImageGallery from '../common/ImageGallery.vue';

/**
 * 채팅 이미지 첨부 바텀시트 — 사진 다중 선택·순서 변경·미리보기·삭제·캡션·전송을 모달 안에서 관리한다.
 * (관리자 페이지 File Manager의 다중 선택 방식과 동일하게 여러 장을 한 번에 선택)
 * 메시지당 이미지는 1장이므로, 선택한 사진은 순서대로 각각 하나의 채팅 메시지로 전송된다.
 * 한 번에 보낼 수 있는 최대 장수는 MAX_IMAGES장으로 제한한다.
 */
const MAX_IMAGES = 9;

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['update:show', 'sent']);

const store = useChatsStore();
const naiveMessage = useMessage();
const dialog = useDialog();

const fileInput = ref(null);
// 선택한 이미지 목록 — { file(리사이즈됨), url(미리보기), image_path(보관함 재사용 시) }, 배열 순서 = 전송 순서
const images = ref([]);
const caption = ref('');
const sending = ref(false);
const cancelled = ref(false);
const preparing = ref(false);
const sentCount = ref(0);
const uploadingIndex = ref(-1); // 선택 즉시 업로드 중인 이미지 인덱스 (없으면 -1)
// 보관함 — 내가 채팅으로 보낸 이미지 (사용자별, 24장씩 페이지네이션)
const mode = ref('pick'); // pick | archive
const archiveItems = ref([]);
const archiveLoading = ref(false);
const archiveLoadingMore = ref(false);
const archivePage = ref(1);
const archiveLastPage = ref(1);
const archiveTotal = ref(0);
// 보관함 미리보기 — 공용 ImageGallery(스와이프 슬라이더) 사용
const previewShow = ref(false);
const previewIndex = ref(0);
const archivePreviewImages = computed(() => archiveItems.value.map((item) => item.url));

// 업로드된 이미지 중 보낼 것들 — 선택해서 전송한다
const selectedImages = computed(() => images.value.filter((img) => img.selected));

const toggleSelected = (index) => {
    if (sending.value || preparing.value || uploadingIndex.value >= 0) {
        return;
    }
    const img = images.value[index];

    if (img) {
        img.selected = !img.selected;
    }
};

const close = () => {
    if (sending.value) {
        return;
    }
    reset();
    emit('update:show', false);
};

// 모달을 열 때마다 이전 상태를 정리한다 (매번 새 사진 선택부터 시작)
watch(
    () => props.show,
    (open) => {
        if (open) {
            reset();
            loadArchive();
        }
    },
);

const revokeAll = () => {
    // 보관함 이미지는 파일(file)이 없으므로 blob URL만 정리한다
    images.value.forEach((img) => {
        if (img.file) {
            URL.revokeObjectURL(img.url);
        }
    });
};

const reset = () => {
    revokeAll();
    images.value = [];
    caption.value = '';
    sending.value = false;
    cancelled.value = false;
    preparing.value = false;
    sentCount.value = 0;
    uploadingIndex.value = -1;
    mode.value = 'pick';
    archiveSelected.value = [];
    previewShow.value = false;
};

// 내가 보낸 이미지 보관함 불러오기 (사용자별) — 첫 페이지부터 다시
const loadArchive = async () => {
    if (archiveLoading.value) {
        return;
    }
    archiveLoading.value = true;

    try {
        const { data } = await apiChatImageArchive(1);

        archiveItems.value = data.data ?? [];
        archivePage.value = 1;
        archiveLastPage.value = data.meta?.last_page ?? 1;
        archiveTotal.value = data.meta?.total ?? archiveItems.value.length;
    } catch {
        archiveItems.value = [];
    } finally {
        archiveLoading.value = false;
    }
};

// 더보기 — 다음 페이지를 이어서 불러온다
const loadMoreArchive = async () => {
    if (archiveLoadingMore.value || archivePage.value >= archiveLastPage.value) {
        return;
    }
    archiveLoadingMore.value = true;

    try {
        const next = archivePage.value + 1;
        const { data } = await apiChatImageArchive(next);
        const more = data.data ?? [];

        archiveItems.value = [...archiveItems.value, ...more];
        archivePage.value = next;
        archiveLastPage.value = data.meta?.last_page ?? archiveLastPage.value;
        archiveTotal.value = data.meta?.total ?? archiveTotal.value;
    } catch (e) {
        naiveMessage.error(getApiErrorMessage(e, '보관함을 불러오지 못했습니다.'));
    } finally {
        archiveLoadingMore.value = false;
    }
};

const openArchive = () => {
    if (sending.value || preparing.value) {
        return;
    }
    mode.value = 'archive';
    loadArchive();
};

// 보관함 다중 선택 — 체크 버튼으로 여러 장 고르기
const archiveSelected = ref([]);
const deletingIds = ref([]);

// 보관함 항목은 지문(image_path)으로 식별한다 — 같은 말풍선(메시지 id)의 여러 장을 구분하기 위함
const isArchiveSelected = (imagePath) => archiveSelected.value.includes(imagePath);

const toggleArchiveSelect = (item) => {
    if (archiveSelected.value.includes(item.image_path)) {
        archiveSelected.value = archiveSelected.value.filter((selectedPath) => selectedPath !== item.image_path);

        return;
    }
    archiveSelected.value = [...archiveSelected.value, item.image_path];
};

// 전체 선택/해제
const archiveAllSelected = computed(() => archiveItems.value.length > 0 && archiveSelected.value.length === archiveItems.value.length);

const toggleSelectAll = () => {
    if (archiveAllSelected.value) {
        archiveSelected.value = [];

        return;
    }
    archiveSelected.value = archiveItems.value.map((item) => item.image_path);
};

// 보관함 이미지 미리보기 — 전체 화면 스와이프 갤러리로 연다
const openPreview = (item) => {
    if (sending.value || preparing.value || deletingIds.value.includes(item.id)) {
        return;
    }
    previewIndex.value = Math.max(0, archiveItems.value.findIndex((archiveItem) => archiveItem.id === item.id));
    previewShow.value = true;
};

// 보관함 이미지 삭제 — 내가 업로드한 이미지를 파일까지 정리한다 (확인 후 삭제)
const removeArchiveItem = (item) => {
    if (deletingIds.value.includes(item.id)) {
        return;
    }

    dialog.warning({
        title: '이미지 삭제',
        content: '이 이미지를 보관함에서 삭제할까요? 파일도 함께 삭제됩니다.',
        positiveText: '삭제',
        negativeText: '취소',
        onPositiveClick: async () => {
            deletingIds.value = [...deletingIds.value, item.id];

            try {
                await apiChatImageDelete(item.id, item.image_path);
                archiveSelected.value = archiveSelected.value.filter((selectedPath) => selectedPath !== item.image_path);
                archiveItems.value = archiveItems.value.filter((archiveItem) => archiveItem.image_path !== item.image_path);
            } catch (e) {
                naiveMessage.error(getApiErrorMessage(e, '이미지 삭제에 실패했습니다.'));
            } finally {
                deletingIds.value = deletingIds.value.filter((id) => id !== item.id);
            }
        },
    });
};

// 선택한 보관함 이미지를 일괄 삭제 — 파일까지 함께 정리한다 (확인 후 삭제)
const deleteSelectedFromArchive = () => {
    const selectedItems = archiveItems.value.filter((item) => archiveSelected.value.includes(item.image_path));

    if (!selectedItems.length || deletingIds.value.length) {
        return;
    }

    dialog.warning({
        title: '선택 이미지 삭제',
        content: `선택한 ${selectedItems.length}장을 보관함에서 삭제할까요? 파일도 함께 삭제됩니다.`,
        positiveText: '삭제',
        negativeText: '취소',
        onPositiveClick: async () => {
            deletingIds.value = [...deletingIds.value, ...selectedItems.map((item) => item.id)];

            try {
                for (const item of selectedItems) {
                    await apiChatImageDelete(item.id, item.image_path);
                }
                const deletedPaths = selectedItems.map((item) => item.image_path);

                archiveItems.value = archiveItems.value.filter((item) => !deletedPaths.includes(item.image_path));
                archiveSelected.value = archiveSelected.value.filter((selectedPath) => !deletedPaths.includes(selectedPath));
            } catch (e) {
                naiveMessage.error(getApiErrorMessage(e, '이미지 삭제에 실패했습니다.'));
            } finally {
                deletingIds.value = deletingIds.value.filter((id) => !selectedItems.some((item) => item.id === id));
            }
        },
    });
};

// 선택한 보관함 이미지를 첨부 목록에 추가 — 파일 재업로드 없이 지문(image_path)으로 바로 전송한다
const addSelectedFromArchive = () => {
    const selectedItems = archiveItems.value.filter((item) => archiveSelected.value.includes(item.image_path));

    if (!selectedItems.length) {
        return;
    }

    const available = MAX_IMAGES - images.value.length;

    if (available <= 0) {
        naiveMessage.warning(`사진은 최대 ${MAX_IMAGES}장까지 선택할 수 있습니다.`);

        return;
    }

    const accepted = selectedItems.slice(0, available);

    if (accepted.length < selectedItems.length) {
        naiveMessage.warning(`사진은 최대 ${MAX_IMAGES}장까지 선택할 수 있습니다. (${selectedItems.length - accepted.length}장 제외)`);
    }

    for (const item of accepted) {
        images.value.push({
            file: null,
            url: item.url,
            image_path: item.image_path,
            selected: true,
            uploadError: false,
        });
    }
    archiveSelected.value = [];
    mode.value = 'pick';
};

// 사진 선택 — 선택 즉시 각 이미지를 업로드(이미지 위 로딩)해 보관한다.
// 업로드가 끝난 이미지 중에서 보낼 것을 골라 전송한다. (최대 MAX_IMAGES장)
const pickImage = async (event) => {
    if (preparing.value || sending.value) {
        return;
    }

    const rawList = Array.from(event.target.files ?? []).filter((f) => f.type.startsWith('image/'));

    if (!rawList.length) {
        event.target.value = '';

        return;
    }

    const available = MAX_IMAGES - images.value.length;

    if (available <= 0) {
        naiveMessage.warning(`사진은 최대 ${MAX_IMAGES}장까지 선택할 수 있습니다.`);
        event.target.value = '';

        return;
    }

    const accepted = rawList.slice(0, available);

    if (accepted.length < rawList.length) {
        naiveMessage.warning(`사진은 최대 ${MAX_IMAGES}장까지 선택할 수 있습니다. (${rawList.length - accepted.length}장 제외)`);
    }

    preparing.value = true;

    try {
        // 1단계 — 먼저 모든 선택 이미지의 썸네일을 표시한다
        const added = [];

        for (const raw of accepted) {
            const resized = await resizeImage(raw, 1080);
            const file = resized ?? raw;
            const item = { uid: ++imageUid, file, url: URL.createObjectURL(file), image_path: null, selected: true, uploadError: false };

            images.value.push(item);
            added.push(item);
        }
        // 썸네일이 먼저 그려지도록 렌더링 틱을 기다린다
        await nextTick();

        // 2단계 — 그다음 순차적으로 업로드한다 (이미지 위 로딩 표시, 실패 시 1회 자동 재시도)
        for (const item of added) {
            const itemIndex = images.value.indexOf(item);

            if (itemIndex === -1) {
                continue; // 업로드 전에 사용자가 삭제한 항목은 건너뜀
            }
            uploadingIndex.value = itemIndex;

            let uploaded = false;

            for (let attempt = 0; attempt < 2 && !uploaded; attempt += 1) {
                try {
                    const { data } = await apiChatImageUpload(item.file);

                    item.image_path = data.data.image_path;
                    item.uploadError = false;
                    uploaded = true;
                } catch {
                    if (attempt === 0) {
                        // 일시적 네트워크 오류 대응 — 잠시 후 1회 더 시도한다
                        await new Promise((resolve) => setTimeout(resolve, 700));
                    }
                }
            }

            if (!uploaded) {
                item.uploadError = true;
                naiveMessage.warning(`${itemIndex + 1}번째 이미지 업로드에 실패했습니다. 다시 시도해 주세요.`);
            }
            uploadingIndex.value = -1;
        }
    } finally {
        preparing.value = false;
        event.target.value = '';
    }
};

// 업로드에 실패한 이미지를 개별적으로 다시 업로드한다
const retryUpload = async (index) => {
    const item = images.value[index];

    if (!item || !item.file || !item.uploadError || sending.value || preparing.value) {
        return;
    }
    uploadingIndex.value = index;

    try {
        const { data } = await apiChatImageUpload(item.file);

        item.image_path = data.data.image_path;
        item.uploadError = false;
    } catch {
        naiveMessage.error('이미지 업로드에 실패했습니다.');
    } finally {
        uploadingIndex.value = -1;
    }
};

// 업로드 실패한 이미지 수 — 전송 차단과 상태 표시에 사용
const failedCount = computed(() => images.value.filter((img) => img.uploadError).length);

// 전송 가능 상태 — 준비/업로드 중이 아니고, 보낼 이미지가 있으며, 실패한 이미지가 없어야 한다
const canSend = computed(
    () =>
        !preparing.value &&
        uploadingIndex.value < 0 &&
        selectedImages.value.length > 0 &&
        !selectedImages.value.some((img) => img.uploadError),
);

// ── 드래그 앤 드롭 순서 변경 — SortableJS (드래그 중인 이미지가 따라오고, 자리를 비워 밀려나는 미리보기) ──
let sortable = null;
let imageUid = 0; // 썸네일 안정 키 — 인덱스가 아닌 고유 키로 Vue 재조정

// ref 콜백 — 그리드가 DOM에 마운트되는 즉시 호출되므로 타이밍 문제 없이 Sortable을 만든다.
// 그리드가 사라지면(null) 정리한다. (mode 전환·모달 닫기·이미지 0장 모두 처리)
const onGridEl = (el) => {
    if (el && !sortable) {
        sortable = Sortable.create(el, {
            animation: 160,
            ghostClass: 'img-sheet__ghost',
            chosenClass: 'img-sheet__chosen',
            dragClass: 'img-sheet__drag',
            // 모든 입력(마우스·터치)에 포인터 기반 드래그를 강제한다.
            // 네이티브 HTML5 드래그는 브라우저·웹뷰마다 동작이 달라 드래그가 안 될 수 있어 사용하지 않는다.
            forceFallback: true,
            fallbackClass: 'img-sheet__fallback',
            fallbackOnBody: true,
            fallbackTolerance: 0,
            // 삭제·선택·다시 시도 버튼은 드래그 시작이 아니라 클릭만 처리한다
            filter: '.img-sheet__thumb-remove, .img-sheet__thumb-check, .img-sheet__thumb-retry',
            preventOnFilter: false,
            onEnd: (evt) => {
                if (evt.oldIndex === evt.newIndex || evt.newIndex == null) {
                    return;
                }
                const arr = [...images.value];
                const [moved] = arr.splice(evt.oldIndex, 1);

                arr.splice(evt.newIndex, 0, moved);
                images.value = arr;
            },
        });
    } else if (!el && sortable) {
        sortable.destroy();
        sortable = null;
    }
};

// 업로드/전송 중에는 순서 변경을 막는다 — 상태가 바뀔 때마다 항상 현재 값과 동기화한다
watchEffect(() => {
    const disabled = sending.value || preparing.value || uploadingIndex.value >= 0;

    if (sortable) {
        sortable.option('disabled', disabled);
    }
});

onBeforeUnmount(() => {
    if (sortable) {
        sortable.destroy();
        sortable = null;
    }
});

const removeImage = (index) => {
    const img = images.value[index];

    if (img) {
        if (img.file) {
            URL.revokeObjectURL(img.url);
        }
        images.value.splice(index, 1);
    }
};

// 전송 취소 — 진행 중인 전송을 중단하고 이미 보낸 항목은 목록에서 정리한다
const cancelSend = () => {
    if (!sending.value) {
        return;
    }
    cancelled.value = true;
};

// 보낼 이미지(선택된 것)를 한 개 말풍선으로 묶어 전송한다.
// 모든 이미지는 선택 시 이미 업로드되어 있어 지문만 모아 바로 전송한다.
const send = async () => {
    const toSend = images.value.filter((img) => img.selected);

    if (!toSend.length || sending.value) {
        if (images.value.length && !toSend.length) {
            naiveMessage.warning('보낼 이미지를 선택해 주세요.');
        }

        return;
    }
    // 업로드 실패한 이미지는 지문이 없어 전송할 수 없다 — 먼저 다시 업로드하게 한다
    if (toSend.some((img) => img.uploadError)) {
        naiveMessage.warning('업로드 실패한 이미지를 다시 시도해 주세요.');

        return;
    }
    sending.value = true;
    cancelled.value = false;
    sentCount.value = 0;
    const body = caption.value.trim();
    const paths = toSend.map((item) => item.image_path);
    let completed = false;

    try {
        await store.send(body, paths);
        completed = true;
        close();
        emit('sent');
    } catch (e) {
        naiveMessage.error(getApiErrorMessage(e, '이미지 전송에 실패했습니다.'));
    } finally {
        sending.value = false;
        cancelled.value = false;
        if (!completed) {
            sentCount.value = 0;
        }
    }
};
</script>

<template>
    <n-modal
        :show="show"
        @update:show="(v) => !v && close()"
        :mask-closable="false"
        :close-on-esc="true"
        :auto-focus="false"
        display-directive="show"
    >
        <div class="img-sheet">
            <!-- 헤더 -->
            <div class="img-sheet__head">
                <b>이미지 첨부</b>
                <button type="button" class="img-sheet__close" aria-label="닫기" @click="close">
                    <BaseIcon name="close" :size="14" />
                </button>
            </div>

            <div class="img-sheet__body">
                <!-- 탭 — 새 사진 선택 / 보관함 -->
                <div class="img-sheet__tabs">
                    <button
                        type="button"
                        :class="{ active: mode === 'pick' }"
                        :disabled="sending || preparing"
                        @click="mode = 'pick'"
                    >
                        사진
                    </button>
                    <button
                        type="button"
                        :class="{ active: mode === 'archive' }"
                        :disabled="sending || preparing"
                        @click="openArchive"
                    >
                        보관함
                    </button>
                </div>

                <template v-if="mode === 'pick'">
                <!-- 썸네일 그리드 — 업로드된 이미지 중 보낼 것 선택/순서 변경(드래그)/개별 삭제 -->
                <div v-if="images.length" :ref="onGridEl" class="img-sheet__grid">
                    <div
                        v-for="(img, i) in images"
                        :key="img.uid"
                        class="img-sheet__thumb"
                        :class="{
                            'is-pending': (sending || uploadingIndex >= 0) && i > (sending ? sentCount : uploadingIndex),
                            'is-unselected': !img.selected,
                            'is-upload-error': img.uploadError,
                        }"
                    >
                        <img :src="img.url" :alt="`첨부 이미지 ${i + 1}`" draggable="false" />
                        <!-- 전송 순서 번호 -->
                        <span class="img-sheet__thumb-num">{{ i + 1 }}</span>

                        <!-- 업로드/전송 진행 상태 — 현재 이미지 위 로딩바/완료 체크 -->
                        <div v-if="sending || uploadingIndex >= 0" class="img-sheet__thumb-status">
                            <span v-if="i < (sending ? sentCount : uploadingIndex)" class="img-sheet__thumb-done">
                                <BaseIcon name="check-done" :size="12" />
                            </span>
                            <span v-else-if="i === (sending ? sentCount : uploadingIndex)" class="img-sheet__thumb-loading">
                                <span class="img-sheet__thumb-spin"></span>
                                <span class="img-sheet__thumb-bar"><span class="img-sheet__thumb-bar-fill"></span></span>
                            </span>
                        </div>

                        <!-- 업로드 실패 — 중앙에 다시 시도 버튼 -->
                        <button
                            v-if="img.uploadError"
                            type="button"
                            class="img-sheet__thumb-retry"
                            :aria-label="`첨부 이미지 ${i + 1} 다시 업로드`"
                            :disabled="sending || preparing || uploadingIndex >= 0"
                            @click.stop="retryUpload(i)"
                        >
                            <BaseIcon name="refresh" :size="14" />
                        </button>

                        <!-- 보낼 이미지 선택 체크 — 좌하단 -->
                        <button
                            type="button"
                            class="img-sheet__thumb-check"
                            :class="{ 'is-checked': img.selected }"
                            :aria-label="img.selected ? '선택 해제' : '선택'"
                            :disabled="sending || preparing || uploadingIndex >= 0"
                            @click.stop="toggleSelected(i)"
                        >
                            <BaseIcon v-if="img.selected" name="check" :size="11" />
                        </button>

                        <!-- 개별 삭제 -->
                        <button
                            type="button"
                            class="img-sheet__thumb-remove"
                            :aria-label="`첨부 이미지 ${i + 1} 삭제`"
                            :disabled="sending || uploadingIndex >= 0"
                            @click="removeImage(i)"
                        >
                            <BaseIcon name="close" :size="12" />
                        </button>
                    </div>
                </div>

                <!-- 빈 상태 -->
                <div v-else class="img-sheet__empty">
                    <BaseIcon name="image" :size="28" />
                    <p>사진을 선택하면 바로 업로드됩니다. 보낼 사진을 골라 주세요.</p>
                </div>

                <!-- 드래그 힌트 — 2장 이상일 때만 안내 -->
                <p v-if="images.length > 1 && !sending && !preparing && uploadingIndex < 0" class="img-sheet__hint">
                    <BaseIcon name="reorder" :size="12" />
                    길게 눌러 순서를 바꿀 수 있어요
                </p>

                <!-- 선택 수 / 업로드·전송 진행 -->
                <p v-if="images.length" class="img-sheet__count">
                    {{ sending ? '전송 중...' : (preparing || uploadingIndex >= 0 ? `업로드 중 (${Math.min(uploadingIndex + 1, images.length)}/${images.length})` : `선택 ${selectedImages.length}장 · 업로드 ${images.length}장${failedCount ? ` · 실패 ${failedCount}장` : ''}`) }}
                </p>

                <!-- 캡션 (선택) -->
                <input
                    v-model="caption"
                    type="text"
                    class="img-sheet__caption"
                    :placeholder="images.length > 1 ? '모든 사진과 함께 보낼 메시지 (선택)' : '함께 보낼 메시지 (선택)'"
                    :disabled="!images.length || sending || preparing"
                    maxlength="2000"
                />

                <!-- 액션 — 사진 선택/추가, 모두 삭제, 전송/취소 -->
                <div class="img-sheet__actions">
                    <label
                        class="img-sheet__pick"
                        :class="{ 'is-disabled': sending || preparing }"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            multiple
                            hidden
                            :disabled="sending || preparing"
                            @change="pickImage"
                        />
                        <BaseIcon name="image" :size="16" />
                        {{ preparing ? '준비 중...' : (images.length ? '사진 추가' : '사진 선택') }}
                    </label>
                    <button
                        v-if="images.length"
                        type="button"
                        class="img-sheet__remove"
                        :disabled="sending || preparing"
                        @click="reset"
                    >
                        <BaseIcon name="trash" :size="16" />
                        모두 삭제
                    </button>
                    <button
                        type="button"
                        class="img-sheet__send"
                        :class="{ 'is-cancel': sending }"
                        :disabled="!(sending || canSend)"
                        @click="sending ? cancelSend() : send()"
                    >
                        <BaseIcon :name="sending ? 'close' : 'send'" :size="16" />
                        {{ sending ? '취소' : (selectedImages.length > 1 ? `전송 ${selectedImages.length}장` : '전송') }}
                    </button>
                </div>
                </template>

                <!-- 보관함 — 내가 보낸 이미지에서 여러 장 선택 -->
                <template v-else>
                    <p v-if="archiveLoading" class="img-sheet__archive-empty">보관함을 불러오는 중...</p>
                    <p v-else-if="!archiveItems.length" class="img-sheet__archive-empty">보관함에 사진이 없습니다.</p>
                    <template v-else>
                        <!-- 전체 선택/해제 + 선택 수 -->
                        <div class="img-sheet__archive-toolbar">
                            <button
                                type="button"
                                class="img-sheet__archive-select-all"
                                :disabled="sending || preparing"
                                @click="toggleSelectAll"
                            >
                                <BaseIcon :name="archiveAllSelected ? 'check-done' : 'check'" :size="14" />
                                {{ archiveAllSelected ? '전체 해제' : '전체 선택' }}
                            </button>
                            <span v-if="archiveSelected.length" class="img-sheet__archive-count">{{ archiveSelected.length }}장 선택</span>
                        </div>

                        <div class="img-sheet__archive-grid">
                            <div
                                v-for="item in archiveItems"
                                :key="item.image_path"
                                class="img-sheet__archive-item"
                                :class="{ 'is-selected': isArchiveSelected(item.image_path) }"
                                :title="'보관함 이미지 미리보기'"
                                @click="openPreview(item)"
                            >
                                <img :src="item.url" :alt="`보관함 이미지 ${item.id}`" loading="lazy" />
                                <!-- 선택 체크 버튼 — 좌상단 -->
                                <button
                                    type="button"
                                    class="img-sheet__archive-check"
                                    :class="{ 'is-checked': isArchiveSelected(item.image_path) }"
                                    :aria-label="isArchiveSelected(item.image_path) ? '선택 해제' : '선택'"
                                    :disabled="sending || preparing"
                                    @click.stop="toggleArchiveSelect(item)"
                                >
                                    <BaseIcon v-if="isArchiveSelected(item.image_path)" name="check" :size="12" />
                                </button>
                                <!-- 삭제 — 업로드된 이미지를 파일까지 정리 -->
                                <button
                                    type="button"
                                    class="img-sheet__archive-delete"
                                    :aria-label="`보관함 이미지 ${item.id} 삭제`"
                                    :disabled="deletingIds.includes(item.id) || sending || preparing"
                                    @click.stop="removeArchiveItem(item)"
                                >
                                    <BaseIcon name="close" :size="11" />
                                </button>
                                <!-- 미리보기 힌트 — 우하단 -->
                                <span class="img-sheet__archive-view">
                                    <BaseIcon name="eye" :size="12" />
                                </span>
                            </div>
                        </div>

                        <!-- 더보기 — 다음 페이지 이어서 불러오기 -->
                        <button
                            v-if="archivePage < archiveLastPage"
                            type="button"
                            class="img-sheet__archive-more"
                            :disabled="archiveLoadingMore || sending || preparing"
                            @click="loadMoreArchive"
                        >
                            {{ archiveLoadingMore ? '불러오는 중...' : '더보기' }}
                        </button>

                        <!-- 선택 액션 — 새 사진과 함께 추가 / 선택 삭제 -->
                        <div class="img-sheet__archive-actions">
                            <button
                                type="button"
                                class="img-sheet__archive-add"
                                :disabled="!archiveSelected.length || sending || preparing"
                                @click="addSelectedFromArchive"
                            >
                                <BaseIcon name="check-done" :size="16" />
                                선택한 {{ archiveSelected.length }}장 추가
                            </button>
                            <button
                                type="button"
                                class="img-sheet__archive-delete-selected"
                                :disabled="!archiveSelected.length || sending || preparing || deletingIds.length"
                                @click="deleteSelectedFromArchive"
                            >
                                <BaseIcon name="trash" :size="16" />
                                선택 삭제
                            </button>
                        </div>
                    </template>
                </template>
            </div>
        </div>
    </n-modal>

    <!-- 보관함 이미지 전체 화면 미리보기 — 공용 ImageGallery (스와이프 슬라이더) -->
    <ImageGallery
        :show="previewShow"
        :images="archivePreviewImages"
        :start-index="previewIndex"
        @update:show="previewShow = $event"
    />
</template>

<style scoped>
/* 바텀시트 — ChatRequestSheet와 동일한 배치 규칙 (스크롤 컨테이너 행 flex에서 하단 고정) */
.img-sheet {
    position: relative;
    width: 100%;
    max-width: 480px;
    /* 시트 높이도 실제 보이는 높이(dvh) 기준 — 82vh는 주소창이 있는 모바일에서 화면 밖으로 넘친다 */
    max-height: 82vh;
    max-height: 82dvh;
    margin: 0 auto;
    align-self: flex-end;
    display: flex;
    flex-direction: column;
    background: var(--surface);
    border-radius: 20px 20px 0 0;
    overflow: hidden;
}

.img-sheet__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}
.img-sheet__head b { flex: 1; text-align: center; font-size: 11px; }
.img-sheet__close { border: 0; background: none; font-size: 11px; color: var(--text-muted); cursor: pointer; width: 28px; }

/* 시트 안쪽 스크롤이 끝에서 뒤 화면으로 이어지지 않게 (모바일 스크롤 체이닝·당겨서 새로고침 방지) */
.img-sheet__body {
    padding: 16px 18px calc(20px + env(safe-area-inset-bottom));
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow-y: auto;
    overscroll-behavior: contain;
}

/* 탭 — 사진 / 보관함 */
.img-sheet__tabs {
    display: flex;
    gap: 8px;
}
.img-sheet__tabs button {
    flex: 1;
    padding: 9px 0;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}
.img-sheet__tabs button.active {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
}
.img-sheet__tabs button:disabled { opacity: 0.6; cursor: not-allowed; }

/* 보관함 — 내가 보낸 이미지 그리드 (3열) */
.img-sheet__archive-empty {
    margin: 20px 0;
    font-size: 11px;
    color: var(--text-muted);
    text-align: center;
}
/* 전체 선택/해제 도구 모음 */
.img-sheet__archive-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.img-sheet__archive-select-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.img-sheet__archive-select-all:hover { border-color: var(--brand); color: var(--brand); }
.img-sheet__archive-select-all:disabled { opacity: 0.6; cursor: not-allowed; }
.img-sheet__archive-count {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}
.img-sheet__archive-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.img-sheet__archive-item {
    position: relative;
    aspect-ratio: 1;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    background: var(--bg);
    cursor: pointer;
}
.img-sheet__archive-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.img-sheet__archive-item:hover { border-color: var(--brand); }
/* 선택 상태 — 브랜드 테두리 + 체크 버튼 강조 */
.img-sheet__archive-item.is-selected {
    border-color: var(--brand);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand) 40%, transparent);
}
/* 선택 체크 버튼 — 좌상단 (탭은 미리보기이므로 별도 버튼) */
.img-sheet__archive-check {
    position: absolute;
    top: 6px;
    left: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: 2px solid rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.45);
    color: transparent;
    cursor: pointer;
}
/* brand 채움은 라이트·다크 모두 밝아 어두운 아이콘(#07120e) — .img-sheet__archive-add와 동일 표준 */
.img-sheet__archive-check.is-checked {
    background: var(--brand);
    border-color: var(--brand);
    color: #07120e;
}
.img-sheet__archive-check:disabled { opacity: 0.6; cursor: not-allowed; }
/* 보관함 이미지 삭제 — 업로드된 이미지를 파일까지 정리 */
.img-sheet__archive-delete {
    position: absolute;
    top: 6px;
    right: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    font-size: 11px;
    line-height: 1;
    cursor: pointer;
}
.img-sheet__archive-delete:hover { background: rgba(0, 0, 0, 0.85); }
.img-sheet__archive-delete:disabled { opacity: 0.6; cursor: not-allowed; }
/* 미리보기 힌트 — 우하단 */
.img-sheet__archive-view {
    position: absolute;
    bottom: 6px;
    right: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    pointer-events: none;
}
/* 더보기 — 다음 페이지 이어서 */
.img-sheet__archive-more {
    width: 100%;
    padding: 10px 0;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.img-sheet__archive-more:hover { border-color: var(--brand); color: var(--brand); }
.img-sheet__archive-more:disabled { opacity: 0.6; cursor: not-allowed; }
/* 선택 액션 — 전송 추가 / 일괄 삭제 버튼 */
.img-sheet__archive-actions {
    display: flex;
    gap: 8px;
    margin-top: 4px;
}
.img-sheet__archive-add,
.img-sheet__archive-delete-selected {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 14px;
    border: 0;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
/* 비율 — 추가 70% / 삭제 30% */
.img-sheet__archive-add { flex: 7; }
.img-sheet__archive-delete-selected { flex: 3; }
/* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝은 색 — 흰 글자 대비가 약해 ChatRequestSheet .rq-form__send와 동일하게 어두운 글자를 쓴다 */
.img-sheet__archive-add {
    background: var(--brand);
    color: #07120e;
}
.img-sheet__archive-delete-selected {
    background: var(--danger);
    color: #fff;
}
.img-sheet__archive-add:disabled,
.img-sheet__archive-delete-selected:disabled { opacity: 0.6; cursor: not-allowed; }

/* 썸네일 그리드 — 3열, 순서 번호 + 개별 삭제 */
.img-sheet__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.img-sheet__thumb {
    position: relative;
    aspect-ratio: 1;
    border-radius: 12px;
    overflow: hidden;
    background: var(--bg);
    border: 1px solid var(--border);
    -webkit-user-drag: none;
    user-select: none;
    /* 터치에서 브라우저가 스크롤로 가로채지 않도록 — 드래그 재정렬이 바로 시작되게 한다 */
    touch-action: none;
    cursor: grab;
}
.img-sheet__thumb:active { cursor: grabbing; }
.img-sheet__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
    -webkit-user-drag: none;
}
/* SortableJS 드래그 상태 — 드래그 중인 이미지가 따라오고, 놓일 자리가 비워지며 밀려난다 */
.img-sheet__drag { opacity: 0.85; }
.img-sheet__chosen { box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25); }
/* forceFallback — 손가락/커서를 따라오는 드래그 이미지 (body에 붙어 자르기·가리기에서 벗어난다) */
.img-sheet__fallback {
    opacity: 0.9;
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
}
.img-sheet__ghost {
    opacity: 0.35;
    border-color: var(--brand);
    border-style: dashed;
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand) 30%, transparent);
}
/* 순서 번호 배지 */
.img-sheet__thumb-num {
    position: absolute;
    top: 6px;
    left: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
}
/* 개별 삭제 */
.img-sheet__thumb-remove {
    position: absolute;
    top: 6px;
    right: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    font-size: 11px;
    line-height: 1;
    cursor: pointer;
}
.img-sheet__thumb-remove:hover { background: rgba(0, 0, 0, 0.85); }
.img-sheet__thumb-remove:disabled { opacity: 0.6; cursor: not-allowed; }
/* 업로드 실패 — 빨간 테두리 + 중앙 다시 시도 버튼 */
.img-sheet__thumb.is-upload-error { border-color: var(--status-cancelled); }
.img-sheet__thumb.is-upload-error img { opacity: 0.4; }
.img-sheet__thumb-retry {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: color-mix(in srgb, var(--status-cancelled) 90%, transparent);
    color: #fff;
    cursor: pointer;
}
.img-sheet__thumb-retry:disabled { opacity: 0.6; cursor: not-allowed; }
/* 보낼 이미지 선택 체크 — 좌하단 */
.img-sheet__thumb-check {
    position: absolute;
    bottom: 6px;
    left: 6px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 2px solid rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.45);
    color: transparent;
    cursor: pointer;
}
.img-sheet__thumb-check.is-checked {
    background: var(--brand);
    border-color: var(--brand);
    color: #07120e;
}
.img-sheet__thumb-check:disabled { opacity: 0.6; cursor: not-allowed; }
/* 선택 안 된 이미지 — 어둡게 */
.img-sheet__thumb.is-unselected img { opacity: 0.35; }

.img-sheet__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 160px;
    border: 1px dashed var(--border);
    border-radius: 14px;
    color: var(--text-muted);
}
.img-sheet__empty p { margin: 0; font-size: 11px; }

.img-sheet__count {
    margin: 0;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}

/* 드래그 힌트 — 2장 이상일 때 순서 변경 안내 */
.img-sheet__hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin: 0;
    font-size: 10px;
    color: var(--text-muted);
}

.img-sheet__caption {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 11px;
    background: var(--bg);
    color: var(--text);
    outline: none;
}
.img-sheet__caption:focus { border-color: var(--brand); }
.img-sheet__caption:disabled { opacity: 0.6; }

/* 액션 — 아이콘 우선 버튼 */
.img-sheet__actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.img-sheet__pick,
.img-sheet__remove,
.img-sheet__send {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}
.img-sheet__pick:hover { border-color: var(--brand); color: var(--brand); }
.img-sheet__pick.is-disabled { opacity: 0.6; cursor: not-allowed; }
.img-sheet__remove { color: var(--danger); }
.img-sheet__remove:hover { border-color: var(--danger); }
.img-sheet__remove:disabled { opacity: 0.6; cursor: not-allowed; }
/* brand 채움은 라이트·다크 모두 밝아 어두운 글자(#07120e) — 위 .img-sheet__archive-add와 동일 표준 */
.img-sheet__send {
    flex: 1;
    border: 0;
    background: var(--brand);
    color: #07120e;
    font-weight: 700;
}
/* 취소 상태는 위험색(의미색) 배경 — 흰 글자로 되돌린다 (앱 관용 .btn--danger와 동일) */
.img-sheet__send.is-cancel {
    background: var(--danger);
    color: #ffffff;
}
.img-sheet__send:disabled { opacity: 0.6; cursor: not-allowed; }

/* 전송 진행 상태 — 현재 업로드 이미지 위 로딩 */
.img-sheet__thumb-status {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.55);
    z-index: 1;
}
/* 전송 완료 표시 — brand 채움이라 어두운 아이콘(#07120e) 표준 */
.img-sheet__thumb-done {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--brand);
    color: #07120e;
}
.img-sheet__thumb-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 0 14px;
    box-sizing: border-box;
}
.img-sheet__thumb-spin {
    width: 22px;
    height: 22px;
    border: 2px solid var(--border);
    border-top-color: var(--brand);
    border-radius: 50%;
    animation: img-sheet-spin 0.8s linear infinite;
}
/* 현재 업로드 이미지 하단 로딩바 */
.img-sheet__thumb-bar {
    display: block;
    width: 100%;
    height: 3px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.25);
    overflow: hidden;
}
.img-sheet__thumb-bar-fill {
    display: block;
    width: 40%;
    height: 100%;
    border-radius: 999px;
    background: var(--brand);
    animation: img-sheet-bar-slide 1s ease-in-out infinite;
}
@keyframes img-sheet-bar-slide {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(300%); }
}
/* 대기 중 이미지 — 어둡게 */
.img-sheet__thumb.is-pending img { opacity: 0.35; }

@keyframes img-sheet-spin {
    to { transform: rotate(360deg); }
}
</style>
