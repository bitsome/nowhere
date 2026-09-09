<script setup>
import { computed, ref, watch } from 'vue';
import BaseIcon from './BaseIcon.vue';
import { useImageStatus } from '../../composables/useImageStatus';

/**
 * ImageGallery — 전체 화면 이미지 갤러리 (PhotoSwipe 방식).
 * 좌우 드래그(스와이프)로 넘기고, 핀치·더블탭으로 확대하며, 상단 인덱스·다운로드·닫기,
 * 하단 썸네일을 제공한다. Teleport로 body에 렌더링해 모달/채팅 입력창보다 항상 위에 표시된다.
 * 메시지 말풍선, 보관함 미리보기 등 어디서든 재사용한다.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    images: { type: Array, default: () => [] },
    startIndex: { type: Number, default: 0 },
});

const emit = defineEmits(['update:show']);

const index = ref(0);
const viewportEl = ref(null);

// 이미지 로딩 상태 — 로딩 스피너/실패 플레이스홀더 표시
const { statusOf, markLoaded, markError } = useImageStatus();

// 열릴 때마다 클릭한 이미지부터 시작한다
watch(
    () => props.show,
    (open) => {
        if (open) {
            index.value = Math.min(props.startIndex, Math.max(0, props.images.length - 1));
            resetZoom();
        }
    },
    { immediate: true },
);

const close = () => {
    resetZoom();
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

// ── 줌 — 핀치·더블탭 확대, 확대 중에는 1손가락으로 이동(pan) ──
const zoom = ref(1); // 1배 ~ 4배
const zoomX = ref(0);
const zoomY = ref(0);

const resetZoom = () => {
    zoom.value = 1;
    zoomX.value = 0;
    zoomY.value = 0;
    pointers.clear();
};

// 슬라이드가 바뀌면 줌을 초기화한다 (화살표·썸네일·스와이프 공통)
watch(index, () => resetZoom());

const panBounds = computed(() => {
    const s = zoom.value;

    if (s <= 1) {
        return { maxX: 0, maxY: 0 };
    }

    return {
        maxX: ((s - 1) * window.innerWidth) / 2,
        maxY: ((s - 1) * window.innerHeight) / 2,
    };
});

const clampPan = (x, y) => {
    const { maxX, maxY } = panBounds.value;

    zoomX.value = Math.max(-maxX, Math.min(maxX, x));
    zoomY.value = Math.max(-maxY, Math.min(maxY, y));
};

// 더블탭 — 1배면 탭 위치 기준 2.5배, 확대 중이면 1배로 되돌린다
const toggleZoom = (clientX, clientY) => {
    if (zoom.value > 1) {
        resetZoom();

        return;
    }
    zoom.value = 2.5;

    if (viewportEl.value) {
        const rect = viewportEl.value.getBoundingClientRect();
        const { maxX, maxY } = panBounds.value;
        const rx = (clientX - rect.left) / rect.width - 0.5;
        const ry = (clientY - rect.top) / rect.height - 0.5;

        clampPan(-rx * rect.width * (zoom.value - 1), -ry * rect.height * (zoom.value - 1));
    }
};

// 현재 슬라이드 이미지에만 줌 변환을 적용한다
const slideImgStyle = (idx) => {
    if (idx !== index.value || zoom.value <= 1) {
        return { transform: 'none' };
    }

    return {
        transform: `scale(${zoom.value}) translate(${zoomX.value}px, ${zoomY.value}px)`,
        transition: isDragging.value || panning ? 'none' : 'transform 0.25s ease-out',
    };
};

// ── 포인터(드래그·핀치·탭) 처리 ──
const isDragging = ref(false); // 1배에서 슬라이드 좌우 드래그 중
const dragStartX = ref(0);
const dragDelta = ref(0);
let dragMoved = false;
let flingV = 0; // 손목 스냅 속도 (px/ms)
let prevX = 0;
let prevT = 0;

const pointers = new Map();
let isPinching = false; // 두 손가락 핀치 중
let pinchStartDist = 0;
let pinchStartZoom = 1;
let panning = false; // 확대 상태에서 1손가락 이동 중
let panStartX = 0;
let panStartY = 0;
let panFromX = 0;
let panFromY = 0;
let lastTapAt = 0;
let lastTapX = 0;
let lastTapY = 0;
let tapCloseTimer = null; // 단일 탭 — 더블탭 확인을 위해 닫기를 잠시 미룬다

const trackStyle = computed(() => {
    const offset = index.value * 100;
    const drag = dragDelta.value;

    return {
        transform: `translateX(calc(${-offset}% ${drag >= 0 ? '+' : '-'} ${Math.abs(drag)}px))`,
        transition: isDragging.value ? 'none' : 'transform 0.32s cubic-bezier(0.25, 0.9, 0.3, 1)',
    };
});

const onSliderPointerDown = (e) => {
    clearTimeout(tapCloseTimer);
    tapCloseTimer = null;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

    // 두 번째 손가락 — 핀치 시작
    if (pointers.size === 2) {
        isPinching = true;
        isDragging.value = false;
        dragDelta.value = 0;
        const [p1, p2] = [...pointers.values()];

        pinchStartDist = Math.max(1, Math.hypot(p1.x - p2.x, p1.y - p2.y));
        pinchStartZoom = zoom.value;

        return;
    }
    // 확대 중 — 1손가락은 이미지 이동
    if (zoom.value > 1) {
        panning = true;
        panStartX = e.clientX;
        panStartY = e.clientY;
        panFromX = zoomX.value;
        panFromY = zoomY.value;

        return;
    }
    // 1배 — 좌우 슬라이드 드래그 시작
    isDragging.value = true;
    dragStartX.value = e.clientX;
    dragDelta.value = 0;
    dragMoved = false;
    flingV = 0;
    prevX = e.clientX;
    prevT = performance.now();
};

const onSliderPointerMove = (e) => {
    if (!pointers.has(e.pointerId)) {
        return;
    }
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

    // 핀치 — 두 손가락 거리로 배율 조절
    if (isPinching && pointers.size === 2) {
        const [p1, p2] = [...pointers.values()];
        const dist = Math.max(1, Math.hypot(p1.x - p2.x, p1.y - p2.y));
        const next = Math.min(4, Math.max(1, (pinchStartZoom * dist) / pinchStartDist));

        zoom.value = next;

        if (next === 1) {
            zoomX.value = 0;
            zoomY.value = 0;
        }

        return;
    }
    // 확대 중 1손가락 — 이미지 이동
    if (panning) {
        clampPan(panFromX + (e.clientX - panStartX), panFromY + (e.clientY - panStartY));

        return;
    }
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

const onSliderPointerUp = (e) => {
    pointers.delete(e.pointerId);

    // 핀치 종료 — 남은 손가락이 있으면 그 손가락으로 pan을 이어간다
    if (isPinching) {
        if (pointers.size < 2) {
            isPinching = false;

            if (pointers.size === 1 && zoom.value > 1) {
                const [p] = [...pointers.values()];

                panning = true;
                panStartX = p.x;
                panStartY = p.y;
                panFromX = zoomX.value;
                panFromY = zoomY.value;
            }
        }

        return;
    }
    // 확대 중 pan 종료
    if (panning) {
        panning = false;

        return;
    }
    if (!isDragging.value) {
        return;
    }
    const threshold = Math.max(60, Math.min(120, window.innerWidth * 0.18));
    const absDrag = Math.abs(dragDelta.value);

    // 1장 폭 이상 밀었거나, 빠르게 휙 넘기면(플링) 다음/이전으로 스냅
    if (absDrag > threshold || Math.abs(flingV) > 0.55) {
        if (dragDelta.value < 0 || flingV < -0.55) {
            showNext();
        } else if (dragDelta.value > 0 || flingV > 0.55) {
            showPrev();
        }
    } else if (!dragMoved) {
        // 탭 — 더블탭(확대)인지 확인 후 아니면 닫는다
        isDragging.value = false;
        dragDelta.value = 0;
        flingV = 0;
        const now = performance.now();
        const isDouble =
            now - lastTapAt < 320 && Math.abs(e.clientX - lastTapX) < 40 && Math.abs(e.clientY - lastTapY) < 40;

        if (isDouble) {
            lastTapAt = 0;
            toggleZoom(e.clientX, e.clientY);

            return;
        }
        lastTapAt = now;
        lastTapX = e.clientX;
        lastTapY = e.clientY;
        tapCloseTimer = setTimeout(() => {
            tapCloseTimer = null;
            close();
        }, 280);

        return;
    }

    isDragging.value = false;
    dragDelta.value = 0;
    flingV = 0;
};

const onSliderPointerCancel = (e) => {
    pointers.delete(e.pointerId);
    isPinching = false;
    panning = false;
    isDragging.value = false;
    dragDelta.value = 0;
    flingV = 0;
    clearTimeout(tapCloseTimer);
    tapCloseTimer = null;
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="ig-gallery">
            <!-- 슬라이더 뷰포트 — 드래그하면 이미지가 옆으로 따라온다 -->
            <div
                ref="viewportEl"
                class="ig-gallery__viewport"
                @pointerdown="onSliderPointerDown"
                @pointermove="onSliderPointerMove"
                @pointerup="onSliderPointerUp"
                @pointercancel="onSliderPointerCancel"
            >
                <div class="ig-gallery__track" :style="trackStyle">
                    <div
                        v-for="(url, idx) in images"
                        :key="`slide-${idx}`"
                        class="ig-gallery__slide"
                    >
                        <img
                            :src="url"
                            :alt="`이미지 ${idx + 1}`"
                            draggable="false"
                            v-show="statusOf(url) !== 'error'"
                            :style="slideImgStyle(idx)"
                            @load="markLoaded(url)"
                            @error="markError(url)"
                        />
                        <!-- 로딩 중 — 스피너 -->
                        <span v-if="statusOf(url) === 'loading'" class="ig-gallery__slide-state">
                            <span class="ig-gallery__spin"></span>
                        </span>
                        <!-- 로딩 실패 — 플레이스홀더 -->
                        <span v-else-if="statusOf(url) === 'error'" class="ig-gallery__slide-state">
                            <BaseIcon name="image" :size="26" />
                        </span>
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
                    <img
                        :src="url"
                        :alt="`썸네일 ${idx + 1}`"
                        loading="lazy"
                        v-show="statusOf(url) !== 'error'"
                        @load="markLoaded(url)"
                        @error="markError(url)"
                    />
                    <span v-if="statusOf(url) === 'error'" class="ig-gallery__thumb-state">
                        <BaseIcon name="image" :size="12" />
                    </span>
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
.ig-gallery__slide{position:relative;flex:0 0 100%;height:100%;display:flex;align-items:center;justify-content:center}
.ig-gallery__slide img{max-width:100%;max-height:100%;object-fit:contain;-webkit-user-drag:none;transform-origin:center center;will-change:transform}
/* 로딩/실패 상태 — 슬라이드 중앙 */
.ig-gallery__slide-state{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.5)}
.ig-gallery__spin{width:22px;height:22px;border:2px solid rgba(255,255,255,0.25);border-top-color:#fff;border-radius:50%;animation:ig-spin .8s linear infinite}
@keyframes ig-spin{to{transform:rotate(360deg)}}
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
.ig-gallery__thumb{position:relative;flex-shrink:0;width:44px;height:44px;padding:0;border:2px solid transparent;border-radius:10px;overflow:hidden;background:#000;cursor:pointer}
.ig-gallery__thumb img{width:100%;height:100%;object-fit:cover;display:block}
.ig-gallery__thumb-state{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.6)}
.ig-gallery__thumb.is-active{border-color:var(--brand)}
</style>
