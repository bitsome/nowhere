<script setup>
import { computed, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiApproveClaim, apiRejectClaim } from '../../api/orders';
import { getApiErrorMessage } from '../../api/client';
import { getChatTimestamp, formatClock } from '../../utils/chatTime';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, required: true },
});

// 승인 처리 후 부모에서 메시지 목록을 다시 불러오도록 알린다
const emit = defineEmits(['acted']);

const naiveMessage = useMessage();
const acting = ref(false);

// 승인 처리 성공 시 즉시 반영될 상태 — 리로드 전에도 카드가 확정 상태로 바뀐다
// (pending → approved / rejected / stale)
const resolved = ref('');
const status = computed(() => resolved.value || props.msg.payload?.status || 'pending');

const type = computed(() => props.msg.type ?? 'text');
const payload = computed(() => props.msg.payload ?? {});

// 승인 요청 상태 — 보낸 사람(sender)이 아닌 상대방에게만 수락/거절 버튼을 보여준다
const isApprovalPending = computed(
    () => type.value === 'approval' && status.value === 'pending',
);

const cardTitle = computed(() => {
    if (type.value === 'approval') {
        if (status.value === 'approved') return '✓ 승인 확인됨';
        if (status.value === 'rejected') return '승인 거절';
        if (status.value === 'stale') return '이미 처리됨';
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
        if (status.value === 'approved') return '운행 시작 승인을 수락했습니다. 운행을 진행할 수 있습니다.';
        if (status.value === 'rejected') return '운행 시작 승인이 거절되었습니다.';
        if (status.value === 'stale') return '이미 처리된 승인 요청입니다.';
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

    switch (type.value) {
        case 'time_change':
            return [
                p.to_time ? `변경 시간: ${p.to_time}` : '',
                p.note ? `사유: ${p.note}` : '',
            ].filter(Boolean);
        case 'route_change':
            return [
                `변경 대상: ${p.target === 'dropoff' ? '목적지' : '픽업 위치'}`,
                p.from ? `현재: ${p.from}` : '',
                p.to ? `변경: ${p.to}` : '',
                p.note ? `사유: ${p.note}` : '',
            ].filter(Boolean);
        case 'payment_change':
            return [
                p.amount ? `변경 금액: ${Number(p.amount).toLocaleString()}원` : '',
                p.note ? `사유: ${p.note}` : '',
            ].filter(Boolean);
        case 'cancel':
            return [
                p.reason ? `사유: ${p.reason}` : '',
            ].filter(Boolean);
        default:
            return [];
    }
});

const timeLabel = computed(() => {
    const ts = getChatTimestamp(props.msg.created_at_iso ?? props.msg.created_at);

    return formatClock(ts);
});

// 승인 수락/거절 — 등록자만 가능 (이벤트의 수신자)
const doAction = async (action) => {
    const orderId = payload.value.order_id;

    if (!orderId || acting.value) return;
    acting.value = true;

    try {
        if (action === 'approve') {
            await apiApproveClaim(orderId);
            resolved.value = 'approved';
            naiveMessage.success('승인되었습니다.');
        } else {
            await apiRejectClaim(orderId);
            resolved.value = 'rejected';
            naiveMessage.success('거절되었습니다.');
        }
        emit('acted');
    } catch (e) {
        const errText = getApiErrorMessage(e, '처리하지 못했습니다.');

        // 이미 승인/거절 처리된 요청이라면 카드를 '이미 처리됨'으로 표시하고 버튼을 숨긴다
        if (/등록자|승인 대기|이미/.test(errText)) {
            resolved.value = 'stale';
        }

        naiveMessage.error(errText);
    } finally {
        acting.value = false;
    }
};
</script>

<template>
    <div
        class="rq-card"
        :class="{ 'rq-card--resolved': type === 'approval' && (status === 'approved' || status === 'rejected') }"
    >
        <div class="rq-card__label" :class="`rq-card__label--${type}`">{{ cardTitle }}</div>
        <div class="rq-card__title">{{ cardText }}</div>
        <div v-if="detailLines.length" class="rq-card__lines">
            <p v-for="line in detailLines" :key="line">{{ line }}</p>
        </div>
        <div v-if="isApprovalPending && !isMine" class="rq-card__actions">
            <button
                type="button"
                class="rq-card__btn rq-card__btn--primary"
                :disabled="acting"
                @click="doAction('approve')"
            >
                {{ acting ? '처리 중...' : '승인 수락' }}
            </button>
            <button
                type="button"
                class="rq-card__btn rq-card__btn--danger"
                :disabled="acting"
                @click="doAction('reject')"
            >
                거절
            </button>
        </div>
        <div v-else-if="isApprovalPending && isMine" class="rq-card__waiting">등록자의 승인을 기다리는 중입니다.</div>
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
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.rq-card__label {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
    background: color-mix(in srgb, var(--brand) 14%, transparent);
    color: var(--brand);
}
.rq-card__label--approval { background: color-mix(in srgb, var(--status-accepted) 14%, transparent); color: var(--status-accepted); }
.rq-card__label--cancel { background: color-mix(in srgb, var(--danger) 14%, transparent); color: var(--danger); }
.rq-card--resolved { border-color: color-mix(in srgb, var(--status-accepted) 30%, transparent); background: color-mix(in srgb, var(--status-accepted) 3%, var(--surface)); }

.rq-card__title { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.45; }
.rq-card__lines { margin-top: 8px; display: flex; flex-direction: column; gap: 3px; }
.rq-card__lines p { margin: 0; font-size: 12px; color: var(--text-muted); line-height: 1.5; word-break: break-word; }

.rq-card__actions { display: flex; gap: 8px; margin-top: 12px; }
.rq-card__btn { flex: 1; padding: 10px 0; border: 0; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; }
.rq-card__btn:disabled { opacity: 0.6; cursor: not-allowed; }
.rq-card__btn--primary { background: var(--brand); color: #fff; }
.rq-card__btn--danger { background: color-mix(in srgb, var(--danger) 14%, transparent); color: var(--danger); }

.rq-card__waiting { margin-top: 12px; padding: 9px 0; border-radius: 12px; background: var(--bg); color: var(--text-muted); font-size: 12px; text-align: center; }

.rq-card__meta { margin-top: 10px; font-size: 11px; color: var(--text-muted); }
</style>
