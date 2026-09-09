<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useChatsStore } from '../../stores/chats';
import { getApiErrorMessage } from '../../api/client';
import BaseIcon from '../common/BaseIcon.vue';
import { getChatTimestamp, formatClock } from '../../utils/chatTime';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, required: true },
});

const router = useRouter();
const store = useChatsStore();
const naiveMessage = useMessage();
const resolving = ref(false);

const type = computed(() => props.msg.type ?? 'text');
const payload = computed(() => props.msg.payload ?? {});

// 시간·경로·금액·취소 요청 — 상대가 수락·거절로 확정할 수 있다 (아직 처리 전일 때만)
const RESOLVABLE_TYPES = ['time_change', 'route_change', 'payment_change', 'cancel'];
const requestStatus = computed(() => payload.value.status ?? 'pending');
const isResolvable = computed(() =>
    RESOLVABLE_TYPES.includes(type.value) && !props.isMine && requestStatus.value === 'pending'
);

// 확정 결과 라벨 — "수락되었습니다 / 거절되었습니다"
const resolvedLabel = computed(() => {
    if (requestStatus.value === 'accepted') {
        return '수락되었습니다';
    }
    if (requestStatus.value === 'rejected') {
        return '거절되었습니다';
    }
    return '';
});

const cardTitle = computed(() => {
    if (type.value === 'approval') {
        return '승인 요청';
    }
    const titles = {
        time_change: '시간 변경 요청',
        route_change: '경로 변경 요청',
        payment_change: '요금 협의 요청',
        cancel: '운행 취소 요청',
    };

    return titles[type.value] ?? '요청';
});

const cardText = computed(() => {
    if (type.value === 'approval') {
        return '운행 시작 승인을 요청했습니다.';
    }
    const texts = {
        time_change: '픽업 시간 변경을 요청합니다.',
        route_change: '경로 변경을 요청합니다.',
        payment_change: '운행 금액 변경을 협의 요청합니다.',
        cancel: '운행 취소를 요청합니다.',
    };

    return texts[type.value] ?? props.msg.body ?? '요청';
});

const detailLines = computed(() => {
    const p = payload.value;
    const base = (() => {
        switch (type.value) {
            case 'time_change':
                return [
                    p.to_time ? `변경 시간: ${p.to_time}` : '',
                    p.note ? `사유: ${p.note}` : '',
                ];
            case 'route_change':
                return [
                    `변경 대상: ${p.target === 'dropoff' ? '목적지' : '픽업 위치'}`,
                    p.from ? `현재: ${p.from}` : '',
                    p.to ? `변경: ${p.to}` : '',
                    p.note ? `사유: ${p.note}` : '',
                ];
            case 'payment_change':
                return [
                    p.amount ? `변경 금액: ${Number(p.amount).toLocaleString()}원` : '',
                    p.note ? `사유: ${p.note}` : '',
                ];
            case 'cancel':
                return [
                    p.reason ? `사유: ${p.reason}` : '',
                ];
            default:
                return [];
        }
    })();

    // 거절 응답 사유는 별도로 보관돼 있으므로 요청 사유와 분리해 보여준다
    if (p.response_reason) {
        base.push(`응답: ${p.response_reason}`);
    }

    return base.filter(Boolean);
});

const timeLabel = computed(() => {
    const ts = getChatTimestamp(props.msg.created_at_iso ?? props.msg.created_at);

    return formatClock(ts);
});

// 수락·거절 확정 — 요청 카드 상태가 갱신되도록 메시지 목록을 다시 읽는다
const resolve = async (action) => {
    if (resolving.value) {
        return;
    }

    resolving.value = true;

    try {
        await store.resolveRequest(props.msg.id, action);
        naiveMessage.success(action === 'accept' ? '요청을 수락했습니다.' : '요청을 거절했습니다.');
    } catch (e) {
        naiveMessage.error(getApiErrorMessage(e, '처리에 실패했습니다.'));
    } finally {
        resolving.value = false;
    }
};

// 승인 처리는 처리할 일에서 — 채팅 카드는 안내·기록 역할만 한다
const goActions = () => router.push({ name: 'actions' });
</script>

<template>
    <div class="rq-card">
        <div class="rq-card__label" :class="`rq-card__label--${type}`">{{ cardTitle }}</div>
        <div class="rq-card__title">{{ cardText }}</div>
        <div v-if="detailLines.length" class="rq-card__lines">
            <p v-for="line in detailLines" :key="line">{{ line }}</p>
        </div>

        <!-- 승인 요청 — 수신자는 처리할 일(액션 센터)에서 승인한다 -->
        <div v-if="type === 'approval' && !isMine" class="rq-card__actions">
            <button type="button" class="rq-card__btn rq-card__btn--primary" @click="goActions">
                처리할 일에서 승인
            </button>
        </div>
        <div v-else-if="type === 'approval' && isMine" class="rq-card__waiting">등록자의 승인을 기다리는 중입니다.</div>

        <!-- 시간·경로·요금·취소 요청 — 받은 미처리 요청은 여기서 바로 수락·거절한다 -->
        <div v-else-if="isResolvable" class="rq-card__actions">
            <button type="button" class="rq-card__btn rq-card__btn--reject" :disabled="resolving" @click="resolve('reject')">
                거절
            </button>
            <button type="button" class="rq-card__btn rq-card__btn--primary" :disabled="resolving" @click="resolve('accept')">
                수락
            </button>
        </div>
        <!-- 처리 대기/완료 상태 — 보낸 쪽 대기 중이거나 확정된 카드 -->
        <div v-else-if="requestStatus === 'pending' && isMine" class="rq-card__waiting">상대방의 응답을 기다리는 중입니다.</div>
        <div v-else-if="resolvedLabel" class="rq-card__result" :class="`rq-card__result--${requestStatus}`">
            <BaseIcon :name="requestStatus === 'accepted' ? 'check' : 'close'" :size="12" />
            {{ resolvedLabel }}
        </div>

        <div class="rq-card__meta">
            <span>{{ isMine ? '나' : props.msg.user_name || '상대' }} · {{ timeLabel }}</span>
        </div>
    </div>
</template>

<style scoped>
.rq-card {
    align-self: center;
    width: min(100%, 420px);
    margin: 6px 0;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.rq-card__label {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    margin-bottom: 8px;
    background: color-mix(in srgb, var(--brand) 14%, transparent);
    color: var(--brand);
}
.rq-card__label--approval { background: color-mix(in srgb, var(--status-accepted) 14%, transparent); color: var(--status-accepted); }
.rq-card__label--cancel { background: color-mix(in srgb, var(--danger) 14%, transparent); color: var(--danger); }

.rq-card__title { font-size: 11px; font-weight: 700; color: var(--text); line-height: 1.45; }
.rq-card__lines { margin-top: 8px; display: flex; flex-direction: column; gap: 3px; }
.rq-card__lines p { margin: 0; font-size: 11px; color: var(--text-muted); line-height: 1.5; word-break: break-word; }

.rq-card__actions { display: flex; gap: 8px; margin-top: 12px; }
.rq-card__btn { flex: 1; padding: 10px 0; border: 0; border-radius: 12px; font-size: 11px; font-weight: 700; cursor: pointer; }
.rq-card__btn:disabled { opacity: 0.6; cursor: not-allowed; }
/* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝은 색 — 흰 글자 대비가 약해 어두운 글자를 쓴다 */
.rq-card__btn--primary { background: var(--brand); color: #07120e; }
.rq-card__btn--reject { background: var(--bg); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 40%, transparent); }

/* 확정 결과 — 수락(민트)/거절(레드) */
.rq-card__result {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 12px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
}
.rq-card__result--accepted {
    background: color-mix(in srgb, var(--status-accepted) 12%, transparent);
    color: var(--status-accepted);
}
.rq-card__result--rejected {
    background: color-mix(in srgb, var(--danger) 12%, transparent);
    color: var(--danger);
}

.rq-card__waiting { margin-top: 12px; padding: 9px 0; border-radius: 12px; background: var(--bg); color: var(--text-muted); font-size: 11px; text-align: center; }

.rq-card__meta { margin-top: 10px; font-size: 11px; color: var(--text-muted); }
</style>
