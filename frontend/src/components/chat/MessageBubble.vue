<script setup>
import { formatTime } from '../../utils/formatTime';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, required: true },
    counterpartName: { type: String, default: '' },
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
        <!-- 상대 아바타 -->
        <span v-if="!isMine" class="cb-avatar">{{ (counterpartName || '?').charAt(0) }}</span>
        <span v-else class="cb-avatar cb-avatar--mine" />

        <div class="cb-col">
            <span v-if="!isMine" class="cb-name">{{ counterpartName || '사용자' }}</span>
            <div class="cb-bubble" :class="{ 'cb-bubble--mine': isMine }">
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
            <div class="cb-meta" :class="{ 'cb-meta--mine': isMine }">
                <span v-if="msg.read" class="cb-meta__read">읽음</span>
                <span class="cb-meta__time">{{ formatTime(msg.created_at) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 메시지 행 */
.cb-row{display:flex;align-items:flex-end;gap:8px}
.cb-row--mine{justify-content:flex-end}
.cb-avatar{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;font-size:12px;font-weight:700;flex-shrink:0;margin-bottom:18px}
.cb-avatar--mine{visibility:hidden}

/* 말풍선 */
.cb-col{display:flex;flex-direction:column;max-width:75%}
.cb-row--mine .cb-col{align-items:flex-end}
.cb-name{font-size:11px;color:var(--text-muted);margin:0 6px 3px;font-weight:600}
.cb-bubble{padding:10px 14px;border-radius:16px;background:var(--surface);border:1px solid var(--border)}
.cb-bubble--mine{background:#36adff;color:#fff;border-color:#36adff}
.cb-row:not(.cb-row--mine) .cb-bubble{border-top-left-radius:4px}
.cb-row--mine .cb-bubble{border-top-right-radius:4px}
.cb-bubble__body{font-size:14px;word-break:break-word;line-height:1.5}
.cb-bubble__image{display:block;max-width:min(260px,100%);max-height:300px;border-radius:10px;object-fit:cover;cursor:zoom-in;margin-bottom:4px}
.cb-meta{display:flex;align-items:center;gap:6px;margin-top:2px;padding:0 4px}
.cb-meta--mine{justify-content:flex-end}
.cb-meta__read{font-size:10px;color:var(--text-muted)}
.cb-meta__time{font-size:11px;color:var(--text-muted)}
</style>
