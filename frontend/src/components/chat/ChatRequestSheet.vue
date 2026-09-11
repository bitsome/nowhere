<script setup>
import { computed, reactive, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useChatsStore } from '../../stores/chats';
import { getApiErrorMessage } from '../../api/client';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    // 대화에 연결된 운행 정보 (있을 때만 요청 전송 가능)
    order: { type: Object, default: null },
});

const emit = defineEmits(['update:show', 'sent']);

const store = useChatsStore();
const naiveMessage = useMessage();

const view = ref('menu'); // menu | form
const activeType = ref(null);
const sending = ref(false);

// 경로에서 픽업/목적지 분리 (예: "인천공항 T1 → 강남")
const routeParts = computed(() => {
    const route = props.order?.route ?? '';

    return route.split(/\s*(?:→|->|~)\s*/).map((p) => p.trim()).filter(Boolean);
});
const currentPickup = computed(() => routeParts.value[0] ?? '');
const currentDropoff = computed(() => routeParts.value[1] ?? '');

const form = reactive({
    to_time: '',
    note: '',
    target: 'pickup',
    from: '',
    to: '',
    amount: '',
    reason: '',
});

const close = () => {
    emit('update:show', false);
    view.value = 'menu';
    activeType.value = null;
    resetForm();
};

const resetForm = () => {
    form.to_time = '';
    form.note = '';
    form.target = 'pickup';
    form.from = '';
    form.to = '';
    form.amount = '';
    form.reason = '';
};

// 폼 진입 시 현재 위치/시간 사전 입력
const openForm = (type) => {
    if (type === 'route_change_dropoff') {
        form.target = 'dropoff';
        form.from = currentDropoff.value;
        activeType.value = 'route_change';
    } else {
        form.target = 'pickup';
        form.from = currentPickup.value;
        activeType.value = type;
    }
    form.to_time = props.order?.service_time ?? '';
    view.value = 'form';
};

// 요청 종류별 표시 메타
const menuItems = computed(() => [
    { type: 'approval', label: '승인 요청', desc: '운행 시작 승인 요청', danger: false },
    { type: 'time_change', label: '시간 변경', desc: '픽업 시간을 협의', danger: false },
    { type: 'route_change', label: '픽업 위치 변경', desc: '새로운 장소를 요청', danger: false },
    { type: 'route_change_dropoff', label: '목적지 변경', desc: '목적지를 변경', danger: false },
    { type: 'payment_change', label: '요금 협의', desc: '추가 요금 또는 금액 확인', danger: false },
    { type: 'cancel', label: '취소 요청', desc: '운행 취소를 요청', danger: true },
]);

const typeLabel = computed(() => {
    const labels = {
        approval: '승인 요청',
        time_change: '시간 변경 요청',
        route_change: '경로 변경 요청',
        payment_change: '요금 협의 요청',
        cancel: '취소 요청',
    };

    return labels[activeType.value] ?? '운행 요청';
});

const typeSub = computed(() => {
    const subs = {
        approval: '운행 등록자에게 승인을 요청합니다.',
        time_change: '등록자와 픽업 시간을 협의합니다.',
        route_change: '픽업 또는 목적지 변경을 요청합니다.',
        payment_change: '운행 금액 변경을 확인합니다.',
        cancel: '등록자에게 취소를 요청합니다.',
    };

    return subs[activeType.value] ?? '';
});

const buildPayload = () => {
    switch (activeType.value) {
        case 'time_change':
            return { to_time: form.to_time.trim(), note: form.note.trim() };
        case 'route_change':
            return { target: form.target, from: form.from.trim(), to: form.to.trim(), note: form.note.trim() };
        case 'payment_change':
            return { amount: Number(form.amount) || 0, note: form.note.trim() };
        case 'cancel':
            return { reason: form.reason };
        case 'approval':
        default:
            return {};
    }
};

const send = async () => {
    // 필수값 검증
    if (activeType.value === 'time_change' && !form.to_time.trim()) {
        naiveMessage.warning('변경 요청 시간을 입력해 주세요.');
        return;
    }
    if (activeType.value === 'route_change' && (!form.from.trim() || !form.to.trim())) {
        naiveMessage.warning('변경 위치를 입력해 주세요.');
        return;
    }
    if (activeType.value === 'payment_change' && !form.amount) {
        naiveMessage.warning('변경 요청 금액을 입력해 주세요.');
        return;
    }
    if (activeType.value === 'cancel' && !form.reason) {
        naiveMessage.warning('취소 사유를 선택해 주세요.');
        return;
    }

    sending.value = true;

    try {
        await store.sendRequest(activeType.value, buildPayload());
        naiveMessage.success('요청을 보냈습니다.');
        close();
        emit('sent');
    } catch (e) {
        naiveMessage.error(getApiErrorMessage(e, '요청을 보내지 못했습니다.'));
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <n-modal
        :show="show"
        @update:show="(v) => !v && close()"
        :mask-closable="true"
        :close-on-esc="true"
        :auto-focus="false"
        display-directive="if"
    >
        <div class="rq-sheet">
            <!-- 뒤로가기 헤더 -->
            <div class="rq-sheet__head">
                <button
                    v-if="view === 'form'"
                    type="button"
                    class="rq-sheet__back"
                    aria-label="뒤로가기"
                    @click="view = 'menu'"
                >
                    <BaseIcon name="arrow-back" :size="16" />
                </button>
                <b>{{ view === 'menu' ? '운행 요청' : typeLabel }}</b>
                <button type="button" class="rq-sheet__close" aria-label="닫기" @click="close"><BaseIcon name="close" :size="14" /></button>
            </div>

            <!-- 메뉴 -->
            <div v-if="view === 'menu'" class="rq-menu">
                <p v-if="!order" class="rq-menu__hint">이 대화에 연결된 운행이 없어 요청을 보낼 수 없습니다.</p>
                <template v-else>
                    <button
                        v-for="item in menuItems"
                        :key="item.type"
                        type="button"
                        class="rq-menu__item"
                        :class="{ 'rq-menu__item--danger': item.danger }"
                        @click="openForm(item.type)"
                    >
                        <span class="rq-menu__label">{{ item.label }}</span>
                        <span class="rq-menu__desc">{{ item.desc }}</span>
                    </button>
                </template>
            </div>

            <!-- 폼 -->
            <div v-else class="rq-form">
                <p class="rq-form__sub">{{ typeSub }}</p>

                <!-- 운행 정보 카드 -->
                <div v-if="order" class="rq-form__order">
                    <span class="rq-form__route">{{ order.route }}</span>
                    <span class="rq-form__meta">{{ order.service_date }} {{ order.service_time }} · {{ Number(order.amount || 0).toLocaleString() }}원</span>
                </div>

                <!-- 승인 요청 -->
                <template v-if="activeType === 'approval'">
                    <p class="rq-form__text">운행 시작 승인을 등록자에게 요청합니다. 등록자가 수락하면 운행을 진행할 수 있습니다.</p>
                </template>

                <!-- 시간 변경 -->
                <template v-else-if="activeType === 'time_change'">
                    <label class="rq-label">변경 요청 시간</label>
                    <input v-model="form.to_time" type="time" class="rq-input" />
                    <label class="rq-label">요청 내용</label>
                    <textarea v-model="form.note" class="rq-textarea" placeholder="변경 사유를 입력하세요." rows="3" />
                </template>

                <!-- 경로 변경 -->
                <template v-else-if="activeType === 'route_change'">
                    <div class="rq-seg">
                        <button
                            type="button"
                            :class="{ active: form.target === 'pickup' }"
                            @click="form.target = 'pickup'"
                        >
                            픽업 위치
                        </button>
                        <button
                            type="button"
                            :class="{ active: form.target === 'dropoff' }"
                            @click="form.target = 'dropoff'"
                        >
                            목적지
                        </button>
                    </div>
                    <label class="rq-label">현재 위치</label>
                    <input v-model="form.from" class="rq-input" placeholder="현재 장소" />
                    <label class="rq-label">변경 위치</label>
                    <input v-model="form.to" class="rq-input" placeholder="새로운 장소를 입력하세요" />
                    <label class="rq-label">요청 내용</label>
                    <textarea v-model="form.note" class="rq-textarea" placeholder="변경 사유를 입력하세요." rows="3" />
                </template>

                <!-- 요금 협의 -->
                <template v-else-if="activeType === 'payment_change'">
                    <label class="rq-label">변경 요청 금액</label>
                    <input v-model="form.amount" type="number" class="rq-input" placeholder="예: 130000" />
                    <label class="rq-label">사유</label>
                    <textarea v-model="form.note" class="rq-textarea" placeholder="추가 요금 또는 변경 사유를 입력하세요." rows="3" />
                </template>

                <!-- 취소 요청 -->
                <template v-else-if="activeType === 'cancel'">
                    <div class="rq-reasons">
                        <button
                            v-for="reason in ['고객 요청', '차량 문제', '기사 사정', '기타']"
                            :key="reason"
                            type="button"
                            :class="{ active: form.reason === reason }"
                            @click="form.reason = reason"
                        >
                            {{ reason }}
                        </button>
                    </div>
                </template>

                <button
                    type="button"
                    class="rq-form__send"
                    :disabled="sending || !order"
                    @click="send"
                >
                    {{ sending ? '보내는 중...' : '요청 보내기' }}
                </button>
            </div>
        </div>
    </n-modal>
</template>

<style scoped>
.rq-sheet {
    width: 100%;
    max-width: 480px;
    /* 시트 높이도 실제 보이는 높이(dvh) 기준 — 82vh는 주소창이 있는 모바일에서 화면 밖으로 넘친다 */
    max-height: 82vh;
    max-height: 82dvh;
    margin: 0 auto;
    /* 바텀시트 — 스크롤 컨테이너(행 flex)에서 아래쪽에 고정 */
    align-self: flex-end;
    display: flex;
    flex-direction: column;
    background: var(--surface);
    border-radius: 20px 20px 0 0;
    overflow: hidden;
}

.rq-sheet__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}
.rq-sheet__head b { flex: 1; text-align: center; font-size: 11px; }
.rq-sheet__back { border: 0; background: none; font-size: 14px; color: var(--text-muted); cursor: pointer; width: 28px; }
.rq-sheet__close { border: 0; background: none; font-size: 11px; color: var(--text-muted); cursor: pointer; width: 28px; }

/* 시트 안쪽 스크롤이 끝에서 뒤 화면으로 이어지지 않게 (모바일 스크롤 체이닝·당겨서 새로고침 방지) */
.rq-menu { padding: 12px 16px calc(20px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 8px; overflow-y: auto; overscroll-behavior: contain; }
.rq-menu__hint { margin: 8px 0; color: var(--text-muted); font-size: 11px; text-align: center; }
.rq-menu__item { display: flex; align-items: center; gap: 12px; width: 100%; padding: 13px 14px; border: 1px solid var(--border); border-radius: 14px; background: var(--bg); text-align: left; cursor: pointer; }
.rq-menu__item--danger .rq-menu__label { color: var(--danger); }
.rq-menu__label { font-size: 11px; font-weight: 700; color: var(--text); }
.rq-menu__desc { font-size: 11px; color: var(--text-muted); margin-left: auto; }

.rq-form { padding: 14px 18px calc(24px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 10px; overflow-y: auto; overscroll-behavior: contain; }
.rq-form__sub { margin: 0; font-size: 11px; color: var(--text-muted); }
.rq-form__order { display: flex; flex-direction: column; gap: 3px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg); }
.rq-form__route { font-size: 11px; font-weight: 700; color: var(--text); }
.rq-form__meta { font-size: 11px; color: var(--text-muted); }
.rq-form__text { margin: 4px 0 8px; font-size: 11px; color: var(--text-muted); line-height: 1.6; }

.rq-label { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; }
.rq-input { width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: 11px 14px; font-size: 11px; background: var(--bg); color: var(--text); outline: none; }
.rq-input:focus { border-color: var(--brand); }
/* 네이티브 time input — 모바일 브라우저의 고정 최소폭으로 폼 밖 삐져나감 방지 */
.rq-input[type='time'] {
    -webkit-appearance: none;
    appearance: none;
    min-width: 0;
    max-width: 100%;
}
.rq-textarea { width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: 11px 14px; font-size: 11px; background: var(--bg); color: var(--text); outline: none; resize: none; font-family: inherit; }
.rq-textarea:focus { border-color: var(--brand); }

.rq-seg { display: flex; gap: 8px; margin-top: 2px; }
.rq-seg button { flex: 1; padding: 10px 0; border: 1px solid var(--border); border-radius: 12px; background: var(--bg); color: var(--text-muted); font-size: 11px; font-weight: 600; cursor: pointer; }
.rq-seg button.active { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand); }

.rq-reasons { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 4px; }
.rq-reasons button { padding: 12px 0; border: 1px solid var(--border); border-radius: 12px; background: var(--bg); color: var(--text-muted); font-size: 11px; font-weight: 600; cursor: pointer; }
.rq-reasons button.active { border-color: var(--danger); background: color-mix(in srgb, var(--danger) 12%, transparent); color: var(--danger); }

/* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝은 색 — 흰 글자 대비가 약해 어두운 글자를 쓴다 (ChatRequestEvent와 동일) */
.rq-form__send { margin-top: 8px; padding: 13px 0; border: 0; border-radius: 12px; background: var(--brand); color: #07120e; font-size: 11px; font-weight: 700; cursor: pointer; }
.rq-form__send:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
