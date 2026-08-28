<script setup>
import { computed, ref, watch } from 'vue';
import BaseIcon from './BaseIcon.vue';

/**
 * ImageGallery — 전체 화면 이미지 갤러리 (PhotoSwipe 방식).
 * 여러 장을 좌우 드래그(스와이프)로 넘겨보고, 상단 인덱스·닫기 버튼, 하단 썸네일을 제공한다.
 * Teleport로 body에 렌더링해 모달/채팅 입력창(z-index)보다 항상 위에 표시된다.
 * 메시지 말풍선, 보관함 미리보기 등 어디서든 재사용한다.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    images: { type: Array, default: () => [] },
    startIndex: { type: Number, default: 0 },
});

const emit = defineEmits(['update:show']);

const index = ref(0);

// 열릴 때마다 클릭한 이미지부터 시작한다
watch(
    () => props.show,
    (open) => {
        if (open) {
            index.value = Math.min(props.startIndex, Math.max(0, props.images.length - 1));
        }
    },
    { immediate: true },
);

const close = () => {
    emit('update:show', false);
};

// 현재 이미지를 다운로드한다 — 같은 오리진 URL이므로 download 속성으로 강제 저장된다
const downloadCurrent = () => {
    const url = props.images[index.value];

    if (!url) {
        return;
    }
    const link = document.createElement('a');

    link.href = url;
    link.download = `nowhere-image-${index.value + 1}.jpg`;
    document.body.appendChild(link);
    link.click();
    link.remove();
};

const showPrev = () => {
    if (index.value > 0) {
        index.value -= 1;
    }
};

const showNext = () => {
    if (index.value < props.images.length - 1) {
        index.value += 1;
    }
};

// 드래그 슬라이드 — 손가락/마우스로 옆으로 밀면 이미지가 따라오고 놓으면 스냅.
// 경계(첫/마지막)에서는 탄성 저항을 줘서 부드럽게 멈춘다.
const isDragging = ref(false);
const dragStartX = ref(0);
const dragDelta = ref(0);
let dragMoved = false;
let suppressCloseClick = false; // 드래그 직후 클릭으로 닫히는 것 방지
let flingV = 0; // 손목 스냅 속도 (px/ms)
let prevX = 0;
let prevT = 0;

const trackStyle = computed(() => {
    const offset = index.value * 100;
    const drag = dragDelta.value;

    return {
        transform: `translateX(calc(${-offset}% ${drag >= 0 ? '+' : '-'} ${Math.abs(drag)}px))`,
        transition: isDragging.value ? 'none' : 'transform 0.32s cubic-bezier(0.25, 0.9, 0.3, 1)',
    };
});

const onSliderPointerDown = (e) => {
    isDragging.value = true;
    dragStartX.value = e.clientX;
    dragDelta.value = 0;
    dragMoved = false;
    flingV = 0;
    prevX = e.clientX;
    prevT = performance.now();
};

const onSliderPointerMove = (e) => {
    if (!isDragging.value) {
        return;
    }

    // 손목 스냅 감지 — 마지막 프레임의 이동 속도
    const now = performance.now();
    const dt = now - prevT;

    if (dt > 0) {
        flingV = (e.clientX - prevX) / dt;
    }
    prevX = e.clientX;
    prevT = now;

    let delta = e.clientX - dragStartX.value;

    // 다음/이전 이미지 1장 폭 이상은 못 밀린다 (빈 공간 방지)
    const limit = Math.max(320, window.innerWidth);

    delta = Math.max(-limit, Math.min(limit, delta));

    // 경계(첫/마지막)에서는 탄성 저항 — 조금 밀리다가 멈춘다
    if (index.value === 0 && delta > 0) {
        delta *= 0.25;
    }
    if (index.value === props.images.length - 1 && delta < 0) {
        delta *= 0.25;
    }
    dragDelta.value = delta;

    if (Math.abs(dragDelta.value) > 6) {
        dragMoved = true;
    }
};

const onSliderPointerUp = () => {
    if (!isDragging.value) {
        return;
    }
    const threshold = Math.max(60, Math.min(120, window.innerWidth * 0.18));
    const absDrag = Math.abs(dragDelta.value);

    // 1장 폭 이상 밀었거나, 빠르게 휙 넘기면(플링) 다음/이전으로 스냅
    if (absDrag > threshold || Math.abs(flingV) > 0.55) {
        suppressCloseClick = true;

        if (dragDelta.value < 0 || flingV < -0.55) {
            showNext();
        } else if (dragDelta.value > 0 || flingV > 0.55) {
            showPrev();
        }
    } else if (dragMoved) {
        suppressCloseClick = true; // 이리저리 밀다가 제자리 — 닫히지 않게
    }

    isDragging.value = false;
    dragDelta.value = 0;
    flingV = 0;
};

// 빈 영역(배경) 클릭으로 닫기
const onSliderClick = () => {
    if (suppressCloseClick) {
        suppressCloseClick = false;

        return;
    }
    close();
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="ig-gallery">
            <!-- 슬라이더 뷰포트 — 드래그하면 이미지가 옆으로 따라온다 -->
            <div
                class="ig-gallery__viewport"
                @click="onSliderClick"
                @pointerdown="onSliderPointerDown"
                @pointermove="onSliderPointerMove"
                @pointerup="onSliderPointerUp"
                @pointercancel="onSliderPointerUp"
            >
                <div class="ig-gallery__track" :style="trackStyle">
                    <div
                        v-for="(url, idx) in images"
                        :key="`slide-${idx}`"
                        class="ig-gallery__slide"
                    >
                        <img :src="url" :alt="`이미지 ${idx + 1}`" draggable="false" @click.stop />
                    </div>
                </div>
            </div>

            <!-- 닫기 버튼 — 최우상단 -->
            <button type="button" class="ig-gallery__close" aria-label="갤러리 닫기" @click="close">
                <BaseIcon name="close" :size="18" />
            </button>
            <!-- 다운로드 — 닫기 옆 -->
            <button type="button" class="ig-gallery__download" aria-label="이미지 저장" @click="downloadCurrent">
                <BaseIcon name="download" :size="18" />
            </button>
            <!-- 인덱스 — 최상단 중앙 -->
            <span v-if="images.length > 1" class="ig-gallery__index">{{ index + 1 }} / {{ images.length }}</span>
            <button
                v-if="images.length > 1"
                type="button"
                class="ig-gallery__nav ig-gallery__nav--prev"
                aria-label="이전 이미지"
                @click.stop="showPrev"
            >
                <BaseIcon name="arrow-back" :size="20" />
            </button>
            <button
                v-if="images.length > 1"
                type="button"
                class="ig-gallery__nav ig-gallery__nav--next"
                aria-label="다음 이미지"
                @click.stop="showNext"
            >
                <BaseIcon name="arrow-forward" :size="20" />
            </button>
            <!-- 하단 썸네일 스트립 — 현재 이미지 강조, 탭하면 이동 -->
            <div v-if="images.length > 1" class="ig-gallery__thumbs">
                <button
                    v-for="(url, idx) in images"
                    :key="`thumb-${idx}`"
                    type="button"
                    class="ig-gallery__thumb"
                    :class="{ 'is-active': idx === index }"
                    :aria-label="`${idx + 1}번째 이미지`"
                    @click.stop="index = idx"
                >
                    <img :src="url" :alt="`썸네일 ${idx + 1}`" loading="lazy" />
                </button>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* 전체 화면 이미지 갤러리 — 옆으로 넘기는 슬라이더.
   Teleport로 body에 렌더링되므로 z-index는 어느 UI(모달·입력창)보다 높게 둔다. */
.ig-gallery{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.94);user-select:none}
.ig-gallery__viewport{position:absolute;inset:0;overflow:hidden;touch-action:none;cursor:grab}
.ig-gallery__track{display:flex;height:100%;will-change:transform}
.ig-gallery__slide{flex:0 0 100%;height:100%;display:flex;align-items:center;justify-content:center}
.ig-gallery__slide img{max-width:100%;max-height:100%;object-fit:contain;-webkit-user-drag:none}
/* 닫기 버튼 — 최우상단 */
.ig-gallery__close{position:absolute;top:calc(16px + env(safe-area-inset-top));right:16px;z-index:3;display:flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:0;border-radius:50%;background:rgba(255,255,255,0.18);color:#fff;cursor:pointer}
.ig-gallery__close:hover{background:rgba(255,255,255,0.32)}
/* 다운로드 — 닫기 버튼 왼쪽 */
.ig-gallery__download{position:absolute;top:calc(16px + env(safe-area-inset-top));right:64px;z-index:3;display:flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:0;border-radius:50%;background:rgba(255,255,255,0.18);color:#fff;cursor:pointer}
.ig-gallery__download:hover{background:rgba(255,255,255,0.32)}
/* 인덱스 — 최상단 중앙 */
.ig-gallery__index{position:absolute;top:calc(16px + env(safe-area-inset-top));left:50%;transform:translateX(-50%);z-index:3;padding:4px 12px;border-radius:999px;background:rgba(0,0,0,0.55);color:#fff;font-size:11px;font-weight:600}
/* 좌우 화살표 */
.ig-gallery__nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;display:flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border:0;border-radius:50%;background:rgba(255,255,255,0.12);color:#fff;cursor:pointer}
.ig-gallery__nav:hover{background:rgba(255,255,255,0.28)}
.ig-gallery__nav--prev{left:12px}
.ig-gallery__nav--next{right:12px}
/* 하단 썸네일 스트립 — 현재 이미지 브랜드 테두리 강조 */
.ig-gallery__thumbs{position:absolute;bottom:calc(18px + env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:3;display:flex;gap:6px;max-width:calc(100% - 32px);padding:6px;border-radius:14px;background:rgba(0,0,0,0.55);overflow-x:auto}
.ig-gallery__thumb{flex-shrink:0;width:44px;height:44px;padding:0;border:2px solid transparent;border-radius:10px;overflow:hidden;background:#000;cursor:pointer}
.ig-gallery__thumb img{width:100%;height:100%;object-fit:cover;display:block}
.ig-gallery__thumb.is-active{border-color:var(--brand)}
</style>
