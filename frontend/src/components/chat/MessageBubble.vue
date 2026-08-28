<script setup>
import { computed, ref } from 'vue';
import { useDialog } from 'naive-ui';
import { getChatTimestamp, formatClock } from '../../utils/chatTime';
import { useImageStatus } from '../../utils/useImageStatus';
import ImageGallery from '../common/ImageGallery.vue';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, required: true },
    // 그룹(같은 상대·같은 분) 내 위치 — 꼬리 모서리와 아바타/시간 표시에 사용
    isFirst: { type: Boolean, default: true },
    isLast: { type: Boolean, default: true },
    counterpartName: { type: String, default: '' },
});

const emit = defineEmits(['delete']);

const dialog = useDialog();

// 정확한 시각 (서버 배포 전엔 상대시간으로 추정)
const ts = computed(() => getChatTimestamp(props.msg.created_at_iso ?? props.msg.created_at));

// 말풍선 연결 모서리 — 시작: 위 꼬리+아래 직선, 마지막: 아래 꼬리+위 직선, 중간: 양쪽 직선
const bubbleCornerClass = computed(() => {
    if (props.isFirst && props.isLast) return 'cb-bubble--solo';
    if (props.isFirst) return 'cb-bubble--tail-top';
    if (props.isLast) return 'cb-bubble--tail-bottom';

    return 'cb-bubble--mid';
});

// 한 개 말풍선에 담긴 이미지들 — 서버가 images(순서 유지)를 내려주고, 구 메시지는 image_url로 보정한다
const images = computed(() => {
    if (Array.isArray(props.msg.images) && props.msg.images.length) {
        return props.msg.images;
    }

    return props.msg.image_url ? [props.msg.image_url] : [];
});

// 전체 화면 갤러리 — 공용 ImageGallery(스와이프 슬라이더) 사용
const galleryOpen = ref(false);
const galleryIndex = ref(0);

// 이미지 로딩 상태 — 로딩 스켈레톤/실패 플레이스홀더 표시
const { statusOf, markLoaded, markError } = useImageStatus();

const openGallery = (index) => {
    if (suppressGalleryClick) {
        suppressGalleryClick = false; // 길게 눌러 삭제 직후의 클릭으로 갤러리가 열리지 않게 한다
        return;
    }
    galleryIndex.value = index;
    galleryOpen.value = true;
};

// ── 길게 눌러 삭제 (내가 보낸 메시지) — 누른 채 550ms 유지하면 확인창이 뜬다 ──
let pressTimer = null;
let suppressGalleryClick = false;
let pressStartX = 0;
let pressStartY = 0;

const cancelPress = () => {
    if (pressTimer) {
        clearTimeout(pressTimer);
        pressTimer = null;
    }
};

const onPressStart = (e) => {
    if (!props.isMine) {
        return;
    }
    pressStartX = e.clientX;
    pressStartY = e.clientY;
    cancelPress();
    pressTimer = setTimeout(() => {
        pressTimer = null;
        suppressGalleryClick = true;
        dialog.warning({
            title: '메시지 삭제',
            content: '이 메시지를 삭제할까요?',
            positiveText: '삭제',
            negativeText: '취소',
            onPositiveClick: () => emit('delete', props.msg.id),
        });
    }, 550);
};

// 스크롤 등으로 손가락이 10px 이상 움직이면 길게 누르기로 보지 않는다
const onPressMove = (e) => {
    if (!pressTimer) {
        return;
    }
    if (Math.abs(e.clientX - pressStartX) > 10 || Math.abs(e.clientY - pressStartY) > 10) {
        cancelPress();
    }
};
</script>

<template>
    <div class="cb-row" :class="{ 'cb-row--mine': isMine }">
        <!-- 상대 아바타 — 그룹 시작에만 노출, 나머지는 자리만 유지해 말풍선 라인 정렬 -->
        <span v-if="!isMine && isFirst" class="cb-avatar">{{ (counterpartName || '?').charAt(0) }}</span>
        <span v-else-if="!isMine" class="cb-avatar cb-avatar--hidden" />
        <span v-if="isMine" class="cb-avatar cb-avatar--mine" />

        <div class="cb-col">
            <span v-if="!isMine && isFirst" class="cb-name">{{ counterpartName || '사용자' }}</span>
            <div
                class="cb-bubble"
                :class="[{ 'cb-bubble--mine': isMine }, bubbleCornerClass]"
                @pointerdown="onPressStart"
                @pointermove="onPressMove"
                @pointerup="cancelPress"
                @pointercancel="cancelPress"
                @pointerleave="cancelPress"
            >
                <!-- 여러 장은 한 말풍선 안에서 그리드로 — 클릭하면 전체 화면 슬라이더로 넘겨본다 -->
                <div
                    v-if="images.length"
                    class="cb-bubble__media"
                    :class="{ 'cb-bubble__media--multi': images.length > 1 }"
                >
                    <div
                        v-for="(url, idx) in images"
                        :key="`${url}-${idx}`"
                        class="cb-bubble__media-item"
                        :class="{ 'is-error': statusOf(url) === 'error' }"
                    >
                        <img
                            :src="url"
                            :alt="`첨부 이미지 ${idx + 1}`"
                            class="cb-bubble__image"
                            loading="lazy"
                            v-show="statusOf(url) !== 'error'"
                            @load="markLoaded(url)"
                            @error="markError(url)"
                            @click="openGallery(idx)"
                        />
                        <!-- 로딩 중 — 스피너 -->
                        <span v-if="statusOf(url) === 'loading'" class="cb-bubble__media-state">
                            <span class="cb-bubble__spin"></span>
                        </span>
                        <!-- 로딩 실패 — 플레이스홀더 -->
                        <span v-else-if="statusOf(url) === 'error'" class="cb-bubble__media-state">
                            <BaseIcon name="image" :size="18" />
                        </span>
                    </div>
                </div>
                <div v-if="msg.body" class="cb-bubble__body">{{ msg.body }}</div>
            </div>
            <div v-if="isLast" class="cb-meta" :class="{ 'cb-meta--mine': isMine }">
                <span v-if="isMine && msg.read" class="cb-meta__read">읽음</span>
                <span class="cb-meta__time">{{ formatClock(ts) }}</span>
            </div>
        </div>
    </div>

    <!-- 전체 화면 이미지 갤러리 — 공용 컴포넌트 (옆으로 넘기는 슬라이더 + 닫기 버튼) -->
    <ImageGallery
        :show="galleryOpen"
        :images="images"
        :start-index="galleryIndex"
        @update:show="galleryOpen = $event"
    />
</template>

<style scoped>
/* 메시지 행 */
.cb-row{display:flex;align-items:flex-end;gap:8px}
.cb-row--mine{justify-content:flex-end}
.cb-avatar{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;font-size: 11px;font-weight:700;flex-shrink:0;align-self:flex-start}
.cb-avatar--mine,.cb-avatar--hidden{visibility:hidden}

/* 말풍선 */
.cb-col{display:flex;flex-direction:column;max-width:75%}
.cb-row--mine .cb-col{align-items:flex-end}
.cb-name{font-size: 11px;color:var(--text-muted);margin:0 6px 3px;font-weight:600}
.cb-bubble{padding:10px 14px;border-radius:16px;background:var(--surface);border:1px solid var(--border)}
.cb-bubble--mine{background:var(--brand);color:#fff;border-color:var(--brand)}

/* 그룹 연결 — 1개: 전부 라운드 / 첫: 하단만 각짐 / 마지막: 상단만 각짐 / 중간: 상하 각짐 */
.cb-row:not(.cb-row--mine) .cb-bubble--tail-top{border-bottom-left-radius:0}
.cb-row:not(.cb-row--mine) .cb-bubble--tail-bottom{border-top-left-radius:0}
.cb-row:not(.cb-row--mine) .cb-bubble--mid{border-top-left-radius:0;border-bottom-left-radius:0}
.cb-row--mine .cb-bubble--tail-top{border-bottom-right-radius:0}
.cb-row--mine .cb-bubble--tail-bottom{border-top-right-radius:0}
.cb-row--mine .cb-bubble--mid{border-top-right-radius:0;border-bottom-right-radius:0}
.cb-bubble__body{font-size: 11px;word-break:break-word;line-height:1.5;white-space:pre-wrap}

/* 이미지 — 1장은 큰 미리보기, 여러 장은 2열 그리드 */
.cb-bubble__media{display:flex;flex-direction:column;gap:3px;margin-bottom:4px}
.cb-bubble__media--multi{display:grid;grid-template-columns:repeat(2,1fr);gap:3px}
.cb-bubble__media-item{position:relative;max-width:min(260px,100%);max-height:300px;border-radius:10px;overflow:hidden;background:var(--bg);border:1px solid var(--border)}
.cb-bubble__media--multi .cb-bubble__media-item{width:100%;max-width:none;max-height:none;aspect-ratio:1}
.cb-bubble__image{display:block;width:100%;height:100%;object-fit:cover;cursor:zoom-in}
/* 로딩/실패 상태 — 이미지 위 중앙 표시 */
.cb-bubble__media-state{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--text-muted)}
.cb-bubble__media-item.is-error{border-style:dashed;border-color:var(--border)}
.cb-bubble__spin{width:16px;height:16px;border:2px solid var(--border);border-top-color:var(--brand);border-radius:50%;animation:cb-spin .8s linear infinite}
@keyframes cb-spin{to{transform:rotate(360deg)}}

.cb-meta{display:flex;align-items:center;gap:6px;margin-top:2px;padding:0 4px}
.cb-meta--mine{justify-content:flex-end}
.cb-meta__read{font-size: 11px;color:var(--text-muted)}
.cb-meta__time{font-size: 11px;color:var(--text-muted)}
</style>
