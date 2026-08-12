<script setup>
import { computed } from 'vue';
import { getChatTimestamp, formatClock } from '../../utils/chatTime';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, required: true },
    // 그룹(같은 상대·같은 분) 내 위치 — 꼬리 모서리와 아바타/시간 표시에 사용
    isFirst: { type: Boolean, default: true },
    isLast: { type: Boolean, default: true },
    counterpartName: { type: String, default: '' },
});

// 정확한 시각 (서버 배포 전엔 상대시간으로 추정)
const ts = computed(() => getChatTimestamp(props.msg.created_at_iso ?? props.msg.created_at));

// 말풍선 연결 모서리 — 시작: 위 꼬리+아래 직선, 마지막: 아래 꼬리+위 직선, 중간: 양쪽 직선
const bubbleCornerClass = computed(() => {
    if (props.isFirst && props.isLast) return 'cb-bubble--solo';
    if (props.isFirst) return 'cb-bubble--tail-top';
    if (props.isLast) return 'cb-bubble--tail-bottom';

    return 'cb-bubble--mid';
});

// 첨부 이미지 새 탭에서 열기
const openImage = () => {
    if (props.msg.image_url) {
        window.open(props.msg.image_url, '_blank', 'noopener');
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
            >
                <img
                    v-if="msg.image_url"
                    :src="msg.image_url"
                    alt="첨부 이미지"
                    class="cb-bubble__image"
                    loading="lazy"
                    @click="openImage"
                />
                <div v-if="msg.body" class="cb-bubble__body">{{ msg.body }}</div>
            </div>
            <div v-if="isLast" class="cb-meta" :class="{ 'cb-meta--mine': isMine }">
                <span v-if="msg.read" class="cb-meta__read">읽음</span>
                <span class="cb-meta__time">{{ formatClock(ts) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 메시지 행 */
.cb-row{display:flex;align-items:flex-end;gap:8px}
.cb-row--mine{justify-content:flex-end}
.cb-avatar{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;font-size:12px;font-weight:700;flex-shrink:0;align-self:flex-start}
.cb-avatar--mine,.cb-avatar--hidden{visibility:hidden}

/* 말풍선 */
.cb-col{display:flex;flex-direction:column;max-width:75%}
.cb-row--mine .cb-col{align-items:flex-end}
.cb-name{font-size:11px;color:var(--text-muted);margin:0 6px 3px;font-weight:600}
.cb-bubble{padding:10px 14px;border-radius:16px;background:var(--surface);border:1px solid var(--border)}
.cb-bubble--mine{background:#36adff;color:#fff;border-color:#36adff}

/* 그룹 연결 — 1개: 전부 라운드 / 첫: 하단만 각짐 / 마지막: 상단만 각짐 / 중간: 상하 각짐 */
.cb-row:not(.cb-row--mine) .cb-bubble--tail-top{border-bottom-left-radius:0}
.cb-row:not(.cb-row--mine) .cb-bubble--tail-bottom{border-top-left-radius:0}
.cb-row:not(.cb-row--mine) .cb-bubble--mid{border-top-left-radius:0;border-bottom-left-radius:0}
.cb-row--mine .cb-bubble--tail-top{border-bottom-right-radius:0}
.cb-row--mine .cb-bubble--tail-bottom{border-top-right-radius:0}
.cb-row--mine .cb-bubble--mid{border-top-right-radius:0;border-bottom-right-radius:0}
.cb-bubble__body{font-size:14px;word-break:break-word;line-height:1.5}
.cb-bubble__image{display:block;max-width:min(260px,100%);max-height:300px;border-radius:10px;object-fit:cover;cursor:zoom-in;margin-bottom:4px}
.cb-meta{display:flex;align-items:center;gap:6px;margin-top:2px;padding:0 4px}
.cb-meta--mine{justify-content:flex-end}
.cb-meta__read{font-size:10px;color:var(--text-muted)}
.cb-meta__time{font-size:11px;color:var(--text-muted)}
</style>
