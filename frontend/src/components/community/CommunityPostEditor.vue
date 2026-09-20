<script setup>
import { computed, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { apiUpdateCommunityPost } from '../../api/community';
import { getApiErrorMessage } from '../../api/client';
import { COMMUNITY_CATEGORIES } from '../../utils/communityCategories';
import { resizeImage } from '../../utils/imageResize';
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

// 수정으로는 설문을 만들 수 없다(선택지 입력이 없어 카테고리에서 제외) — 설문 글은 카테고리 자체가 고정된다
const EDIT_CATEGORIES = COMMUNITY_CATEGORIES.filter((c) => c.key !== 'survey');

const message = useMessage();

const draftContent = ref('');
const draftCategory = ref('free');
const draftImage = ref(null);
const draftPreviewUrl = ref('');
const draftVideoUrl = ref('');
const submitting = ref(false);

// 여행지 맛집 — 장소 정보
const draftPlaceName = ref('');
const draftPlaceRegion = ref('');
const draftPlaceAddress = ref('');
const draftPlaceMapUrl = ref('');

// 설문조사는 선택지가 투표 순번과 묶여 있어 수정할 수 없다 (카테고리도 고정)
const isSurvey = computed(() => Boolean(props.post?.survey));
const isPlaceCategory = computed(() => draftCategory.value === 'food');

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
        draftPlaceName.value = props.post?.place?.name ?? '';
        draftPlaceRegion.value = props.post?.place?.region ?? '';
        draftPlaceAddress.value = props.post?.place?.address ?? '';
        draftPlaceMapUrl.value = props.post?.place?.map_url ?? '';
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

const submit = async () => {
    const content = draftContent.value.trim();

    if (content === '') {
        message.warning('글 내용을 입력해 주세요.');

        return;
    }

    if (isPlaceCategory.value && draftPlaceName.value.trim() === '') {
        message.warning('맛집 이름을 입력해 주세요.');

        return;
    }

    submitting.value = true;

    try {
        const { data } = await apiUpdateCommunityPost(props.post.id, {
            content,
            category: draftCategory.value,
            image: draftImage.value,
            video_url: draftVideoUrl.value.trim(),
            // 맛집 카테고리에서 벗어나면 장소 정보를 비운다 (카드가 아닌 일반 글로 돌아간다)
            place_name: isPlaceCategory.value ? draftPlaceName.value.trim() : '',
            place_region: isPlaceCategory.value ? draftPlaceRegion.value.trim() : '',
            place_address: isPlaceCategory.value ? draftPlaceAddress.value.trim() : '',
            place_map_url: isPlaceCategory.value ? draftPlaceMapUrl.value.trim() : '',
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
            <!-- 카테고리 선택 — 설문 글은 선택지가 투표와 묶여 있어 고정 -->
            <div v-if="!isSurvey" class="editor__cats">
                <button
                    v-for="c in EDIT_CATEGORIES"
                    :key="c.key"
                    type="button"
                    class="editor__cat"
                    :class="{ 'editor__cat--active': draftCategory === c.key }"
                    @click="draftCategory = c.key"
                >
                    <span><BaseIcon :name="c.icon" :size="16" /></span>{{ c.label }}
                </button>
            </div>
            <p v-else class="editor__locked">설문조사 — 선택지와 카테고리는 수정할 수 없습니다.</p>

            <n-input
                v-model:value="draftContent"
                type="textarea"
                placeholder="무슨 일이 있었나요? 공유해 보세요."
                :rows="4"
                maxlength="2000"
                show-count
            />

            <img v-if="draftPreviewUrl" :src="draftPreviewUrl" alt="첨부 미리보기" class="editor__preview" />

            <n-input
                v-model:value="draftVideoUrl"
                type="text"
                placeholder="영상/숏츠 링크 (예: https://youtube.com/shorts/...) 선택"
                clearable
                class="editor__video"
            />

            <!-- 여행지 맛집 — 장소 정보 -->
            <div v-if="isPlaceCategory" class="editor__place">
                <n-input v-model:value="draftPlaceName" type="text" placeholder="맛집 이름 (필수)" maxlength="80" />
                <n-input v-model:value="draftPlaceRegion" type="text" placeholder="지역 (예: 제주 서귀포)" maxlength="40" />
                <n-input v-model:value="draftPlaceAddress" type="text" placeholder="주소" maxlength="150" />
                <n-input v-model:value="draftPlaceMapUrl" type="text" placeholder="지도 링크 (선택)" maxlength="500" />
            </div>

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
    font-size: 11px;
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

/* ── 설문 고정 안내 / 여행지 맛집 입력 ── */
.editor__locked {
    margin: 0;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.02);
    color: var(--text-muted);
    font-size: 11px;
}

html.dark .editor__locked { background: rgba(255, 255, 255, 0.03); }

.editor__place {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .editor__place { background: rgba(255, 255, 255, 0.03); }

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
    font-size: 11px;
    cursor: pointer;
}

.editor__upload svg { width: 20px; height: 20px; }

.editor__actions {
    display: flex;
    gap: 8px;
}
</style>
