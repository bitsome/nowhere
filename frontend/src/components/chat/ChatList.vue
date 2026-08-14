<script setup>
import { formatTime } from '../../utils/formatTime';
import { useAuthStore } from '../../stores/auth';

defineProps({
    conversations: { type: Array, required: true },
});

const emit = defineEmits(['open']);

const auth = useAuthStore();
</script>

<template>
    <div class="chat-list">
        <div
            v-for="conv in conversations"
            :key="conv.id"
            class="chat-list__item"
            :class="{ 'chat-list__item--unread': conv.unread_count > 0 }"
            @click="emit('open', conv.id)"
        >
            <span
                class="chat-list__avatar"
                :class="{ 'chat-list__avatar--unread': conv.unread_count > 0 }"
            >
                {{ conv.counterpart?.name?.charAt(0) ?? '?' }}
            </span>
            <div class="chat-list__body">
                <div class="chat-list__row">
                    <strong>{{ conv.counterpart?.name }}</strong>
                    <span class="chat-list__time">{{ formatTime(conv.last_message_at) }}</span>
                </div>
                <div class="chat-list__row">
                    <span
                        class="chat-list__preview"
                        :class="{ 'chat-list__preview--unread': conv.unread_count > 0 }"
                    >
                        <template v-if="conv.last_message">
                            <span v-if="conv.last_message.user_id === auth.user?.id" class="chat-list__mine">나: </span>{{ conv.last_message.body }}
                        </template>
                        <template v-else>대화를 시작해보세요</template>
                    </span>
                    <span v-if="conv.order" class="chat-list__order-chip">운행</span>
                    <n-badge v-if="conv.unread_count > 0" :value="conv.unread_count" :max="99" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 대화 목록 — 개별 카드형 */
.chat-list{display:flex;flex-direction:column;gap:10px;padding-top:12px;overflow:visible}
.chat-list__item{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.04);cursor:pointer;transition:border-color .12s ease,box-shadow .12s ease}
.chat-list__item:hover{border-color:color-mix(in srgb,var(--brand) 35%,transparent);box-shadow:0 2px 8px rgba(0,0,0,.06)}

/* 안읽음 대화 강조 */
.chat-list__item--unread{border-color:color-mix(in srgb,var(--brand) 40%,transparent);background:color-mix(in srgb,var(--brand) 4%,transparent)}
.chat-list__item--unread:hover{border-color:color-mix(in srgb,var(--brand) 55%,transparent)}
.chat-list__item--unread .chat-list__row > strong{font-weight:800}
.chat-list__avatar--unread{box-shadow:0 0 0 2px var(--accent)}
.chat-list__preview--unread{font-weight:700;color:var(--text)}
.chat-list__avatar{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;background:var(--accent);color:#fff;font-size:16px;font-weight:700;flex-shrink:0}
.chat-list__body{flex:1;min-width:0}
.chat-list__row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.chat-list__time{color:var(--text-muted);font-size:12px;flex-shrink:0}
.chat-list__preview{color:var(--text-muted);font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-list__mine{font-weight:600;color:var(--text)}
.chat-list__order-chip{flex-shrink:0;padding:1px 6px;border-radius:6px;background:color-mix(in srgb,var(--brand) 12%,transparent);color:var(--brand);font-size:11px;font-weight:700}
</style>
