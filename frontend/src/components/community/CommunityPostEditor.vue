<script setup>
import { ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { apiUpdateCommunityPost } from '../../api/community';
import { getApiErrorMessage } from '../../api/client';
import { COMMUNITY_CATEGORIES } from '../../utils/communityCategories';
import BaseIcon from '../common/BaseIcon.vue';

/**
 * 커뮤니티 글 수정 모달 — 피드/상세 화면에서 공용으로 사용한다.
 *
 * @param {boolean} show v-model — 모달 표시 여부
 * @param {object|null} post 수정 대상 글 (null이면 닫힌 상태)
 */
defineOptions({ name: 'CommunityPostEditor' });

const props = defineProps({
    show: { type: Boolean, default: false },
    post: { type: Object, default: null },
});

const emit = defineEmits(['update:show', 'saved']);

const message = useMessage();

const draftContent = ref('');
const draftCategory = ref('free');
const draftImage = ref(null);
const draftPreviewUrl = ref('');
const draftVideoUrl = ref('');
const submitting = ref(false);

// 열릴 때마다 원본 글 값으로 초기화
watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        draftContent.value = props.post?.content ?? '';
        draftCategory.value = props.post?.category ?? 'free';
        draftImage.value = null;
        draftPreviewUrl.value = props.post?.image_url ?? '';
        draftVideoUrl.value = props.post?.video_url ?? '';
    },
);

const close = () => {
    emit('update:show', false);
};

const pickImage = async (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    // 업로드 전 최대 1080px로 리사이즈 (대용량 원본 그대로 업로드 방지)
    const resized = await resizeImage(file, 1080);

    draftImage.value = resized ?? file;
    draftPreviewUrl.value = URL.createObjectURL(draftImage.value);
};

/**
 * 이미지를 canvas로 리사이즈해 JPEG Blob으로 변환한다.
 * 실패(비이미지 등)하면 null — 원본 그대로 사용.
 */
const resizeImage = (file, maxSize) =>
    new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
            const width = Math.max(1, Math.round(img.width * scale));
            const height = Math.max(1, Math.round(img.height * scale));
            const canvas = document.createElement('canvas');

            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);
            URL.revokeObjectURL(url);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        resolve(null);

                        return;
                    }

                    resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                },
                'image/jpeg',
                0.82,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };

        img.src = url;
    });

const submit = async () => {
    const content = draftContent.value.trim();

    if (content === '') {
        message.warning('글 내용을 입력해주세요.');

        return;
    }

    submitting.value = true;

    try {
        const { data } = await apiUpdateCommunityPost(props.post.id, {
            content,
            category: draftCategory.value,
            image: draftImage.value,
            video_url: draftVideoUrl.value.trim(),
        });

        emit('saved', data.data);
    } catch (e) {
        message.error(getApiErrorMessage(e, '글 수정에 실패했습니다.'));
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <n-modal
        :show="show"
        preset="card"
        title="글 수정"
        :style="{ maxWidth: '520px' }"
        @update:show="(v) => emit('update:show', v)"
    >
        <div class="editor">
            <!-- 카테고리 선택 -->
            <div class="editor__cats">
                <button
                    v-for="c in COMMUNITY_CATEGORIES"
                    :key="c.key"
                    type="button"
                    class="editor__cat"
                    :class="{ 'editor__cat--active': draftCategory === c.key }"
                    @click="draftCategory = c.key"
                >
                    <span><BaseIcon :name="c.icon" :size="16" /></span>{{ c.label }}
                </button>
            </div>

            <n-input
                v-model:value="draftContent"
                type="textarea"
                placeholder="무슨 일이 있었나요? 공유해보세요."
                :rows="4"
                maxlength="2000"
                show-count
            />

            <img v-if="draftPreviewUrl" :src="draftPreviewUrl" alt="첨부 미리보기" class="editor__preview" />

            <n-input
                v-model:value="draftVideoUrl"
                type="text"
                placeholder="영상/숏츠 URL (예: https://youtube.com/shorts/...) 선택"
                clearable
                class="editor__video"
            />

            <div class="editor__footer">
                <label class="editor__upload">
                    <input type="file" accept="image/*" hidden @change="pickImage" />
                    <BaseIcon name="image" :size="16" />
                    {{ props.post?.image_url ? '사진 교체' : '사진 첨부' }}
                </label>
                <div class="editor__actions">
                    <n-button quaternary @click="close">취소</n-button>
                    <n-button
                        type="primary"
                        :loading="submitting"
                        :disabled="!draftContent.trim()"
                        @click="submit"
                    >
                        저장
                    </n-button>
                </div>
            </div>
        </div>
    </n-modal>
</template>

<style scoped>
.editor { display: flex; flex-direction: column; gap: 14px; }

.editor__cats {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.editor__cat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 11px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.editor__cat--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
}

.editor__preview {
    width: 100%;
    max-height: 260px;
    object-fit: cover;
    border-radius: 10px;
}

.editor__video { margin-top: 0; }

.editor__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.editor__upload {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
    font-size: 13px;
    cursor: pointer;
}

.editor__upload svg { width: 20px; height: 20px; }

.editor__actions {
    display: flex;
    gap: 8px;
}
</style>
