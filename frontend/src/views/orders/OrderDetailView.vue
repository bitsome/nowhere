<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useChatsStore } from '../../stores/chats';
import { useMessage as useNaiveMessage } from 'naive-ui';
import { useOrderDetail } from '../../composables/useOrderDetail';
import { useOrderMap } from '../../composables/useOrderMap';
import { apiAdvanceRideStep, apiTransitionOrder } from '../../api/orders';
import { getApiErrorMessage } from '../../api/client';
import BaseIcon from '../../components/common/BaseIcon.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import VerifiedBadge from '../../components/common/VerifiedBadge.vue';
import OrderDetailChat from '../../components/orders/OrderDetailChat.vue';
import ReportDialog from '../../components/reports/ReportDialog.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const chats = useChatsStore();
const naiveMessage = useNaiveMessage();

// ── 모듈: 운행 상세(로드·파생·상태변경·리뷰·취소·분리) / 경로 지도 ──
const detail = useOrderDetail({ route, router, auth, chats, naiveMessage });
const map = useOrderMap({ order: detail.order });

const {
    order, group, statusOptions, nextTransitions, timeline, loading, error, acting, message, messageType,
    currentStep, isCancelled, isPriority, serviceTime, minutesToService, isUrgent, isToday, isTomorrow,
    serviceCountdownLabel, amountLabel, serviceDatetimeLabel, isClaimable, canClaim, showDriverOnlyNotice, isRegistrant, isPerformer,
    statusLabel, canReview, myReview,
    reviewOpen, reviewRating, reviewContent, reviewSubmitting, openReview, submitReview,
    canChat, chatTargetId, hasRegistrantChat, canEdit, goEdit, isClaimPending, claims, isRegistrantPending, isClaimantPending,
    canManageStatus,
    canSeeStatusManagement,
    isWaitingClaims, confirmState, askConfirm, closeConfirm, doConfirm, withdrawClaim, approveClaim, rejectClaim, requestDetails, openChat, goUserPage,
    rejectOpen, rejectReason, rejectSubmitting, submitRejectClaim,
    detailRequestOpen, detailRequestReason, detailRequestMessage, detailRequestSubmitting, submitDetailRequest,
    favorited, toggleFavorite,
    primaryAction, primaryActionStatus, statusButtonColor, resolveCssVarColor, groupOrderRows, groupTotalAmount, stepStyle,
    lineItems, statusTagType, refresh, claim, transition, cancelOpen, cancelReason, requestTransition,
    confirmCancel, completionOpen, completionRevenue, confirmComplete, detach,
    offers, offersLoading, canOffer, myPendingOffer,
    offerOpen, offerAmount, offerMessage, offerSubmitting, openOffer, submitOffer,
    withdrawOffer,
    SERVICE_LABELS, STATUS_FLOW,
} = detail;

const {
    mapTarget, mapOpen, mapQueryLabel, mapQuery, mapEmbedUrl, mapGoogleUrl, mapNaverUrl, mapKakaoUrl,
} = map;

// ── 운행 진행 스테퍼 — 기사 본인이 맡은 예약/운행중 운행만 상세에서 순차 진행한다 ──
// 하단 바에 원터치로 다음 단계를 진행하는 버튼을 보여준다. 완료 후에는 '운행 완료' 안내로 유지.
const ridePanelOrder = computed(() => {
    const o = order.value;

    if (!o || !isPerformer.value || !['accepted', 'driving', 'completed'].includes(o.status)) {
        return null;
    }

    return {
        id: o.id,
        userId: o.user_id,
        status: o.status,
        statusLabel: statusLabel.value,
        rideStep: o.ride_step,
        route: `${o.pickup_location || '-'} → ${o.dropoff_location || '-'}`,
        date: o.service_date,
        time: o.service_time,
        amount: amountLabel.value,
    };
});

// 스테퍼 노출 — 수행자는 진행/완료 전체에서 보이고, 등록자는 수행자가 '운행시작'을 눌러
// 실제 운행이 시작된 뒤부터 진행 상황을 함께 볼 수 있다. (등록자에게는 진행 버튼이 없다)
const showRideStepper = computed(() => {
    if (ridePanelOrder.value) {
        return true;
    }

    const o = order.value;

    return Boolean(isRegistrant.value && o && ['driving', 'completed'].includes(o.status));
});

// ── 운행 신고 — 문제 있는 운행을 운영팀에 접수한다 (내가 등록한 운행·관리자는 제외) ──
const reportOpen = ref(false);

const canReportOrder = computed(() => {
    const o = order.value;

    return Boolean(o && auth.user && !auth.isAdmin && o.user_id !== auth.user?.id);
});

// 찜(즐겨찾기) — 마켓에서 가져올 수 있는 운행일 때만 하트를 노출한다
// (내가 등록했거나 이미 가져간 운행, 숨김·보류 운행은 찜 대상이 아니다)
const canFavorite = computed(() => {
    const o = order.value;

    if (!o || isPerformer.value) {
        return false;
    }
    if (o.user_id === auth.user?.id) {
        return false;
    }
    if (o.is_hidden || o.admin_hold) {
        return false;
    }

    return ['published', 'trading', 'acceptance_pending'].includes(o.status);
});

const reportSubject = computed(() => {
    const o = order.value;

    return o ? `${o.pickup_location || '-'} → ${o.dropoff_location || '-'}` : '';
});

// 운행 절차 단계 — 원터치 진행을 위해 다음 단계와 진행도를 계산한다.
// 각 단계는 진행 단계(운행중·도착 등)를 연상시키는 색으로 구분한다.
// 하단 버튼은 전체 문구(label), 히어로 진행 표시는 짧은 문구(shortLabel)를 쓴다.
// 운행시작 → 픽업장소 도착 → 승객탑승 → 출발 → 이동중 → 목적지 도착 (도착지 도착 = 완료)
const RIDE_PROCEDURE_STEPS = [
    { value: 'ride_start', label: '운행시작', shortLabel: '시작', color: 'var(--status-published)' },
    { value: 'pickup_arrived', label: '픽업장소 도착', shortLabel: '장소', color: 'var(--status-driving)' },
    { value: 'passenger_arrived', label: '승객탑승', shortLabel: '탑승', color: 'var(--status-trading)' },
    { value: 'departed', label: '출발', shortLabel: '출발', color: 'var(--status-accepted)' },
    { value: 'moving', label: '이동중', shortLabel: '이동', color: 'var(--status-settled)' },
    { value: 'arrived', label: '목적지 도착', shortLabel: '도착', color: 'var(--status-completed)' },
];

const rideStepIndex = computed(() => {
    const index = RIDE_PROCEDURE_STEPS.findIndex((s) => s.value === order.value?.ride_step);

    return index !== -1 ? index : -1;
});

// 타임라인 표시 — 상태 전이는 "공개 → 수락 대기", 단계 전이는 운행 절차 라벨로 보여준다
const timelineLabel = (event) => {
    if (!event) {
        return '';
    }
    if (event.event === 'ride_step') {
        return RIDE_PROCEDURE_STEPS.find((s) => s.value === event.to_status)?.label ?? event.to_status ?? '';
    }
    const from = statusOptions.value[event.from_status] ?? event.from_status ?? '';
    const to = statusOptions.value[event.to_status] ?? event.to_status ?? '';

    return `${from} → ${to}`;
};

const timelineTime = (iso) => {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return '';
    }

    return `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

const rideStepCount = computed(() => (rideStepIndex.value < 0 ? 0 : rideStepIndex.value + 1));
const rideNextStep = computed(() => RIDE_PROCEDURE_STEPS[rideStepIndex.value + 1] ?? null);

// 하단 바 버튼 글자색 — 단계 색(상태색 채움)이 밝으면 어두운 글자, 짙으면 흰 글자.
// (RideHistoryView statusTextColor 등 앱 표준의 밝은 상태 목록과 동일한 규칙 — naive text-color prop은 CSS var를 받지 못해 상태명에서 판단)
const rideBarDarkText = computed(() => {
    const step = rideNextStep.value;
    const colorVar = (step?.color ?? 'var(--status-completed)');
    const status = colorVar.replace('var(--status-', '').replace(')', '');

    return ['published', 'driving', 'trading', 'completed'].includes(status);
});
const rideBarTextColor = computed(() => (rideBarDarkText.value ? '#101418' : '#ffffff'));

// 각 단계가 기록된 시각 — 스테퍼 단계 아래에 HH:MM로 표시한다
const rideStepTimeLabel = (stepValue) => {
    const iso = order.value?.ride_step_times?.[stepValue];

    if (!iso) {
        return '';
    }

    const d = new Date(iso);

    if (isNaN(d.getTime())) {
        return '';
    }

    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

const rideAdvancing = ref(false);

// 운행 완료 축하 모달 — 마지막 단계(목적지 도착)를 기록하면 금액 입력 없이 바로 완료되고 축하를 띄운다
const rideCompleteOpen = ref(false);

// 폭죽 조각 — 완료 모달 중앙에서 바깥으로 흩어지는 CSS 애니메이션 좌표·색상·지연
const CONFETTI_COLORS = ['#ffd666', '#ffa940', '#73d13d', '#40a9ff', '#ff4d4f', '#b37feb'];
const confettiStyle = (n) => {
    const angle = (n / 24) * Math.PI * 2;
    const distance = 72 + (n % 5) * 14;
    const delay = (n % 8) * 0.06;

    return {
        '--cx': `${Math.cos(angle) * distance}px`,
        '--cy': `${Math.sin(angle) * distance}px`,
        '--confetti-color': CONFETTI_COLORS[n % CONFETTI_COLORS.length],
        animationDelay: `${delay}s`,
    };
};

// 하단 바 원터치 — 확인 모달을 거친 뒤 다음 단계로 진행한다 (오탭으로 인한 실수 방지)
const advanceRideFromBar = () => {
    const step = rideNextStep.value;

    if (!step || rideAdvancing.value) return;

    // 목적지 도착 = 완료 — 완료 확인 문구
    if (step.value === 'arrived') {
        askConfirm({
            title: '운행 완료',
            message: '목적지에 도착했습니다.\n운행을 완료할까요?',
            confirmText: '운행 완료',
            type: 'primary',
            onConfirm: () => runAdvanceStep(step),
        });

        return;
    }

    askConfirm({
        title: '운행 절차',
        message: `다음 단계 '${step.label}'로 진행할까요?`,
        confirmText: '진행',
        type: 'primary',
        onConfirm: () => runAdvanceStep(step),
    });
};

const runAdvanceStep = async (step) => {
    if (rideAdvancing.value) return;

    rideAdvancing.value = true;

    try {
        // 첫 단계 '운행시작' — 예약 상태에서 탭하면 운행중 전환과 함께 시작 단계를 기록한다
        const { data } = step.value === 'ride_start' && order.value?.status === 'accepted'
            ? await apiTransitionOrder(order.value.id, 'driving')
            : await apiAdvanceRideStep(order.value.id);

        order.value.status = data.data.status ?? order.value.status;
        order.value.ride_step = data.data.ride_step ?? order.value.ride_step;
        order.value.ride_step_times = data.data.ride_step_times || order.value.ride_step_times;

        // 목적지 도착 = 완료 — 완료 직후 축하 모달
        if (step.value === 'arrived') {
            rideCompleteOpen.value = true;
        }
    } catch (e) {
        naiveMessage.error(getApiErrorMessage(e, '단계 진행에 실패했습니다.'));
    } finally {
        rideAdvancing.value = false;
    }
};

onMounted(refresh);

// ── 가져오기 요청 감지 — 등록자가 상세에 머무는 동안 기사 요청이 오면 바로 반영한다 ──
// SSE(알림) 이벤트로 즉시 감지하고, 폴링은 연결이 끊겼을 때의 보험 역할을 한다.
let claimPollTimer = null;

const stopClaimPolling = () => {
    if (claimPollTimer) {
        clearInterval(claimPollTimer);
        claimPollTimer = null;
    }
};

const startClaimPolling = () => {
    stopClaimPolling();
    claimPollTimer = setInterval(() => detail.refreshSilently(), 8000);
};

// 요청/승인/정산 변화를 감시해야 하는 상태 — 승인 대기 중이거나,
// 등록자는 신청 대기(공개/거래중)부터 운행 진행(수락/운행중/완료) 동안,
// 수행자는 운행 진행(수락/운행중)부터 완료 후 정산 결과(완료/정산)까지
// 상태 변화를 실시간 반영한다. → 새로고침 없이 스테퍼·정산 상태가 자동으로 나타난다.
const claimWatchActive = computed(() => {
    if (isClaimantPending.value || isRegistrantPending.value) {
        return true;
    }

    // 등록자 — 기사 신청 감지 + 운행 진행 상황 반영
    if (
        isRegistrant.value
        && ['published', 'trading', 'accepted', 'driving', 'completed'].includes(order.value?.status)
    ) {
        return true;
    }

    // 수행자 — 완료 후 등록자가 정산 처리하면 그 결과를 자동으로 받아본다
    return Boolean(
        isPerformer.value
        && ['accepted', 'driving', 'completed'].includes(order.value?.status),
    );
});

const onApprovalRefresh = () => {
    if (claimWatchActive.value) {
        detail.refreshSilently();
    }
};

watch(claimWatchActive, (active) => {
    if (active) {
        startClaimPolling();
    } else {
        stopClaimPolling();
    }
}, { immediate: true });

onMounted(() => window.addEventListener('app:sse-refresh', onApprovalRefresh));

onBeforeUnmount(() => {
    window.removeEventListener('app:sse-refresh', onApprovalRefresh);
    stopClaimPolling();
});

// ── 등록자와 대화 바텀 시트 — 하단 채팅 버튼으로 아래에서 위로 열린다 ──
const chatOpen = ref(false);

// 채팅 버튼 안 읽음 표시 — 대화 상대가 보낸 안 읽은 메시지 수(0이면 표시 안 함).
// 같은 운행에 대화가 여러 개여도 현재 채팅 상대와 일치하는 대화만 센다.
const chatUnread = computed(() => {
    if (!order.value?.id || !chatTargetId.value) {
        return 0;
    }

    const conversation = chats.conversations.find(
        (c) => c.order_id === order.value.id && c.counterpart?.id === chatTargetId.value,
    );

    return conversation ? Math.max(1, Math.min(conversation.unread_count ?? 0, 99)) : 0;
});

// 대화창을 열면 서버에서 읽음 처리되므로 목록의 안 읽음 수를 갱신해 배지를 즉시 지운다
watch(chatOpen, (open) => {
    if (open) {
        chats.loadConversations().catch(() => {});
    }
});

// 대화 상대 이름 — 가져온 기사 입장에선 원 등록자, 등록자 입장에선 수행 기사
const chatCounterpartName = computed(() => {
    if (!chatTargetId.value || !order.value) return '';

    const o = order.value;

    if (o.original_owner_id === chatTargetId.value) {
        return o.original_owner?.name || '';
    }

    return o.user?.name || '';
});

// 승인·제안 처리는 처리할 일에서
const goActions = () => router.push({ name: 'actions' });

// 기사 차량 요약 — 등록된 차량이 없으면 '미등록'으로 안내한다
const vehicleText = (v) => {
    if (!v) return '미등록';

    const parts = [];
    if (v.name) parts.push(v.name);
    if (v.type) parts.push(v.type);
    if (v.license_plate) parts.push(`[${v.license_plate}]`);
    if (v.color) parts.push(v.color);

    return parts.length ? parts.join(' ') : '미등록';
};

// 상세 정보 요청 사유 — 자주 쓰는 요청을 정리해 선택지로 제공한다
const detailRequestReasonOptions = [
    '승객 연락처가 필요해요',
    '상세 주소가 필요해요',
    '항공편 정보가 필요해요',
    '인원·짐 정보가 필요해요',
    '차량 정보가 필요해요',
    '기타',
].map((label) => ({ label, value: label }));

// 리뷰 자주 쓰는 문구 — 선택하면 리뷰 내용을 채운 뒤 선택을 비운다
const reviewPhrase = ref('');
const reviewPhraseOptions = [
    '친절하고 안전하게 운행해 주셨어요. 감사합니다!',
    '시간을 잘 지켜주시고 서비스가 좋았습니다.',
    '차량이 깨끗하고 운전도 안정적이었어요.',
    '승객을 배려하는 좋은 서비스였습니다.',
    '다음에도 이용하고 싶습니다.',
].map((label) => ({ label, value: label }));
const applyReviewPhrase = (value) => {
    if (!value) {
        return;
    }

    reviewContent.value = value;
    reviewPhrase.value = '';
};

// 가져오기 신청 시각 표시 — "HH:MM"으로 간단히 보여준다
const formatClaimTime = (iso) => {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (isNaN(date.getTime())) {
        return '';
    }

    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
};
</script>

<template>
    <div
        class="detail-page page-shell"
        :class="{ 'detail-page--bar': ridePanelOrder }"
    >
        <div class="detail-hero">
            <div class="detail-hero__body">
                <p class="detail-hero__eyebrow">운행 상세</p>
                <div class="detail-hero__route">
                    <span class="detail-hero__loc">{{ order?.pickup_location || '-' }}</span>
                    <span class="detail-hero__arrow">→</span>
                    <span class="detail-hero__loc">{{ order?.dropoff_location || '-' }}</span>
                </div>
                <div class="detail-hero__badges">
                    <span v-if="isWaitingClaims" class="hero-badge hero-badge--waiting">요청 대기 중</span>
                    <span v-if="isPriority" class="hero-badge hero-badge--priority">긴급</span>
                    <span v-if="isUrgent" class="hero-badge hero-badge--urgent">임박</span>
                </div>
                <p class="detail-hero__meta">{{ serviceDatetimeLabel }}</p>
                <!-- 운행 신고 — 문제 있는 운행을 운영팀에 접수 (유틸성 작은 텍스트 버튼) -->
                <button
                    v-if="canReportOrder"
                    type="button"
                    class="detail-hero__report"
                    @click="reportOpen = true"
                >
                    <BaseIcon name="warning" :size="12" />
                    신고
                </button>
            </div>
            <div class="detail-hero__side">
                <!-- 금액과 한 줄 — 하트를 금액 바로 옆에 두어 '이 운행을 찜'하는 버튼임을 알게 한다 -->
                <div class="detail-hero__side-top">
                    <!-- 찜 — 마켓 운행을 나중에 다시 보려고 보관한다 -->
                    <button
                        v-if="canFavorite"
                        type="button"
                        class="detail-hero__fav"
                        :class="{ 'detail-hero__fav--on': favorited }"
                        :aria-label="favorited ? '찜 해제' : '찜하기'"
                        :title="favorited ? '찜 해제' : '찜하기'"
                        @click="toggleFavorite"
                    >
                        <BaseIcon :name="favorited ? 'heart-filled' : 'heart'" :size="21" />
                    </button>
                    <div class="detail-hero__amount">{{ amountLabel }}</div>
                </div>
                <n-tag size="large" round :type="statusTagType">
                    {{ statusLabel }}
                </n-tag>
            </div>
        </div>

        <!-- 운행 진행 스테퍼 — 히어로와 운행 정보 사이, 기사 본인은 진행 버튼 없이 표시만
             (수행자: 예약/운행중/완료, 등록자: 운행이 시작된 뒤부터 함께 표시) -->
        <div v-if="showRideStepper" class="ride-stepper">
            <div class="ride-steps">
                <template v-for="(step, i) in RIDE_PROCEDURE_STEPS" :key="step.value">
                    <div
                        class="ride-steps__item"
                        :class="{
                            'ride-steps__item--done': i < rideStepCount,
                            'ride-steps__item--current': i === rideStepIndex,
                        }"
                    >
                        <span
                            class="ride-steps__dot"
                            :style="i < rideStepCount ? { backgroundColor: step.color } : undefined"
                        />
                        <span class="ride-steps__label">{{ step.shortLabel }}</span>
                        <span class="ride-steps__time">{{ rideStepTimeLabel(step.value) }}</span>
                    </div>
                    <span
                        v-if="i < RIDE_PROCEDURE_STEPS.length - 1"
                        class="ride-steps__line"
                        :class="{ 'ride-steps__line--done': i < rideStepCount - 1 }"
                    />
                </template>
            </div>
            <span class="ride-stepper__count">{{ rideStepCount }}/{{ RIDE_PROCEDURE_STEPS.length }}</span>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="detail-block">
            {{ error }}
        </n-alert>

        <div v-if="loading" class="detail-skeleton">
            <div class="sk-card detail-skeleton__hero" />
            <div class="sk-card detail-skeleton__card">
                <div class="sk-line sk-line--md" style="width: 30%" />
                <div class="sk-line" style="margin-top: 12px" />
                <div class="sk-line" style="margin-top: 8px; width: 75%" />
                <div class="sk-line" style="margin-top: 8px; width: 55%" />
            </div>
            <div class="sk-card detail-skeleton__card">
                <div class="sk-line sk-line--md" style="width: 25%" />
                <div class="sk-line" style="margin-top: 12px" />
                <div class="sk-line" style="margin-top: 8px; width: 65%" />
            </div>
        </div>

        <template v-else>
            <div v-if="order" class="detail-body">
                <n-alert v-if="isCancelled" type="error" :show-icon="true" class="detail-block">
                    취소된 운행입니다.
                </n-alert>

                <n-card v-if="isClaimantPending" :bordered="true" class="detail-block detail-pending-card" size="small">
                    <div class="detail-pending-card__head">
                        <div>
                            <p class="detail-pending-card__eyebrow">승인 요청</p>
                            <strong class="detail-pending-card__title">등록자 승인 대기 중</strong>
                        </div>
                        <div class="detail-pending-card__timer">{{ serviceCountdownLabel || '대기 중' }}</div>
                    </div>
                    <p class="detail-pending-card__desc">
                        운행 등록자에게 운행 신청을 보냈습니다. 제한시간 내 승인되지 않으면 신청이 자동 취소됩니다.
                    </p>
                    <div class="detail-pending-card__actions">
                        <div v-if="canChat" class="detail-chat-wrap detail-chat-wrap--block">
                            <n-button
                                type="primary"
                                size="large"
                                block
                                @click="chatOpen = true"
                            >
                                채팅으로 승인 요청
                            </n-button>
                            <span
                                v-if="chatUnread > 0"
                                class="detail-chat-wrap__dot"
                                :class="{ 'detail-chat-wrap__dot--num': chatUnread > 1 }"
                            >
                                {{ chatUnread > 9 ? '9+' : chatUnread > 1 ? chatUnread : '' }}
                            </span>
                        </div>
                        <n-button
                            size="large"
                            secondary
                            block
                            :loading="acting"
                            @click="withdrawClaim"
                        >
                            운행 신청 취소
                        </n-button>
                    </div>
                </n-card>

                <n-alert v-if="isRegistrantPending" type="info" :show-icon="true" class="detail-block">
                    기사 {{ claims.length }}명이 이 운행을 가져오기 요청했습니다. 내용 하단에서 신청자별로 승인하거나 거절할 수 있습니다.
                </n-alert>

                <!-- 완료 후 정산 대기 — 수행 기사는 완료가 마지막, 등록자가 정산 처리 -->
                <n-alert
                    v-if="order.status === 'completed' && !isRegistrant"
                    type="info"
                    :show-icon="true"
                    class="detail-block"
                >
                    운행이 완료되었습니다. 등록자가 정산을 처리하면 '정산 완료'로 전환됩니다.
                </n-alert>

                <n-card :bordered="true" class="detail-block">
                    <template #header>
                        <div class="detail-info-head">
                            <span>운행 정보</span>
                            <n-button
                                v-if="!isRegistrant"
                                size="tiny"
                                secondary
                                type="warning"
                                @click="requestDetails"
                            >
                                상세 정보 요청
                            </n-button>
                        </div>
                    </template>

                    <!-- 노선·일시·금액·긴급은 상단 히어로에서 보여주므로 생략 -->

                    <div class="detail-rows">
                        <div class="detail-row">
                            <span>출발지</span>
                            <strong>{{ order.pickup_location || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>도착지</span>
                            <strong>{{ order.dropoff_location || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>차량</span>
                            <strong>{{ order.vehicle_type || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>항공편</span>
                            <strong>{{ order.flight_number || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>인원 · 짐</span>
                            <strong>{{ order.passenger_count || 0 }}명 · {{ order.luggage_count || 0 }}개</strong>
                        </div>
                        <div
                            v-if="['completed', 'settled'].includes(order.status) && order.actual_revenue != null"
                            class="detail-row"
                        >
                            <span>실제 수익</span>
                            <strong>{{ Number(order.actual_revenue).toLocaleString() }}원</strong>
                        </div>
                    </div>

                    <p class="detail-group-title">예약</p>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span>고객명</span>
                            <strong>{{ order.customer_name || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>고객 연락처</span>
                            <strong>
                                <a
                                    v-if="order.customer_phone"
                                    class="detail-phone"
                                    :href="`tel:${order.customer_phone}`"
                                >
                                    {{ order.customer_phone }}
                                </a>
                                <template v-else>-</template>
                            </strong>
                        </div>
                        <div class="detail-row">
                            <span>예약처</span>
                            <strong>{{ order.reservation_company || '-' }} · {{ order.reservation_channel || '-' }}</strong>
                        </div>
                    </div>

                    <p class="detail-group-title">기타</p>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span>등록자</span>
                            <strong
                                v-if="order.user?.id"
                                class="detail-link"
                                @click="goUserPage(order.user.id)"
                            >
                                {{ order.user?.name || '-' }}
                            </strong>
                            <strong v-else>{{ order.user?.name || '-' }}</strong>
                        </div>
                    </div>
                </n-card>

                <!-- 등록자와 대화 — 하단 채팅 버튼으로 여는 바텀 시트에서 소통 -->

                <n-card v-if="order.pickup_location || order.dropoff_location" :bordered="true" class="detail-block">
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>경로 지도</span>
                            <n-radio-group v-model:value="mapTarget" size="small">
                                <n-radio-button value="pickup">출발지</n-radio-button>
                                <n-radio-button value="dropoff">도착지</n-radio-button>
                            </n-radio-group>
                        </n-space>
                    </template>
                    <div class="detail-map">
                        <iframe
                            v-if="mapOpen && mapEmbedUrl"
                            :src="mapEmbedUrl"
                            class="detail-map__frame"
                            title="지도"
                            loading="lazy"
                        />
                        <div v-else class="detail-map__placeholder">
                            <span class="detail-map__label">{{ mapQueryLabel }} · {{ mapQuery || '-' }}</span>
                            <n-button size="small" secondary @click="mapOpen = true">
                                지도 보기
                            </n-button>
                        </div>
                    </div>
                    <div class="detail-map__links">
                        <span class="detail-map__hint">앱으로 열기</span>
                        <a :href="mapGoogleUrl" target="_blank" rel="noopener">구글 지도</a>
                        <a :href="mapNaverUrl" target="_blank" rel="noopener">네이버 지도</a>
                        <a :href="mapKakaoUrl" target="_blank" rel="noopener">카카오 지도</a>
                    </div>
                </n-card>

                <n-card
                    v-if="group"
                    :bordered="true"
                    class="detail-block"
                >
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>셋트 그룹</span>
                            <n-tag size="small" round>{{ group.name }}</n-tag>
                            <n-tag v-if="groupTotalAmount > 0" size="small" round type="warning">
                                총 {{ Number(groupTotalAmount).toLocaleString() }}원
                            </n-tag>
                        </n-space>
                    </template>
                    <div class="group-schedule-list">
                        <div
                            v-for="sibling in groupOrderRows"
                            :key="sibling.id"
                            class="group-schedule-item"
                            :class="{ 'group-schedule-item--current': sibling.isCurrent }"
                        >
                            <div class="group-schedule-item__head">
                                <strong>{{ sibling.scheduled_time || sibling.service_time || '-' }}</strong>
                                <n-tag size="small" round>
                                    {{ SERVICE_LABELS[sibling.service_type] ?? (sibling.service_type || '-') }}
                                </n-tag>
                                <span v-if="sibling.displayAmount" class="group-schedule-item__amount">
                                    {{ Number(sibling.displayAmount).toLocaleString() }}원
                                </span>
                                <n-button
                                    v-if="!sibling.isCurrent"
                                    size="tiny"
                                    quaternary
                                    type="warning"
                                    :loading="acting"
                                    class="group-schedule-item__detach"
                                    @click.stop="detach(sibling.id)"
                                >
                                    분리
                                </n-button>
                            </div>
                            <div class="group-schedule-item__route">
                                {{ sibling.pickup_location || '-' }} → {{ sibling.dropoff_location || '-' }}
                            </div>
                            <div class="group-schedule-item__meta">
                                {{ sibling.displayDate }}
                                <template v-if="sibling.flight_number">
                                     · 항공편: {{ sibling.flight_number }}
                                </template>
                                <template v-if="sibling.passenger_count">
                                     · {{ sibling.passenger_count }}명
                                </template>
                            </div>
                        </div>
                    </div>
                </n-card>

                <n-card v-if="lineItems.length" :bordered="true" class="detail-block">
                    <template #header>일정</template>
                    <div class="schedule-list">
                        <n-card
                            v-for="(item, index) in lineItems"
                            :key="index"
                            size="small"
                            class="schedule-card"
                        >
                            <template #header>
                                <div class="schedule-card__head">
                                    <n-space align="center" :size="8">
                                        <strong class="schedule-time">{{ item.scheduled_time }}</strong>
                                        <n-tag size="small" round>{{ item.service_type }}</n-tag>
                                    </n-space>
                                    <span class="schedule-date">{{ item.service_date }} {{ item.service_weekday }}</span>
                                </div>
                            </template>
                            <div class="schedule-card__route">
                                {{ item.pickup_location }}
                                <span class="schedule-card__arrow">→</span>
                                {{ item.dropoff_location }}
                            </div>
                            <div
                                v-if="item.flight_number && item.flight_number !== '-'"
                                class="schedule-card__meta"
                            >
                                항공편: {{ item.flight_number }}
                            </div>
                        </n-card>
                    </div>
                </n-card>

                <n-card v-if="canSeeStatusManagement" :bordered="true" class="detail-block">
                    <template #header>상태 관리</template>

                    <n-alert
                        v-if="message"
                        :type="messageType"
                        :show-icon="true"
                        class="detail-message"
                    >
                        {{ message }}
                    </n-alert>

                    <!-- 상태 전이 — 하단 고정 바 없이 내용 하단에서 바로 진행
                         (운행자·수행자가 진행, 완료→정산은 등록자만) -->
                    <p
                        v-if="nextTransitions.length && canManageStatus && !ridePanelOrder"
                        class="detail-next-hint"
                    >
                        다음 단계:
                        <strong>{{ statusOptions[nextTransitions[0]] ?? nextTransitions[0] }}</strong>
                        <template v-if="nextTransitions.length > 1">
                            외 {{ nextTransitions.length - 1 }}건
                        </template>
                    </p>
                    <n-space
                        v-if="nextTransitions.length && canManageStatus && !ridePanelOrder"
                        wrap
                    >
                        <n-button
                            v-for="next in nextTransitions"
                            :key="next"
                            size="large"
                            :color="statusButtonColor(next)"
                            :loading="acting"
                            @click="requestTransition(next)"
                        >
                            → {{ statusOptions[next] ?? next }}
                        </n-button>
                    </n-space>

                    <!-- 등록자·관찰자 — 운행자(수행 기사)가 상태를 진행한다 -->
                    <n-alert
                        v-if="nextTransitions.length && !canManageStatus && !isClaimable && order?.status !== 'acceptance_pending' && order?.status !== 'completed'"
                        type="info"
                        :show-icon="true"
                        class="detail-message"
                    >
                        운행 상태 관리는 운행자(수행 기사)가 진행합니다.
                    </n-alert>

                    <n-alert
                        v-if="order?.status === 'cancelled' && order.cancel_reason"
                        type="warning"
                        :show-icon="true"
                        class="detail-message"
                    >
                        취소 사유: {{ order.cancel_reason }}
                    </n-alert>

                    <n-empty
                        v-if="canManageStatus && !ridePanelOrder && !nextTransitions.length && !isClaimable && order?.status !== 'cancelled'"
                        description="진행할 수 있는 상태 전이가 없습니다."
                        :show-description="true"
                    />

                </n-card>

                <!-- 일반 화면 액션 — 박스 밖 내용 하단에서 바로 실행
                     (기사 본인의 예약/운행중 운행은 하단 고정 바로 이동) -->
                <div
                    v-if="!ridePanelOrder && (primaryAction || canChat || canEdit)"
                    class="detail-actions"
                >
                    <n-button
                        v-if="primaryAction"
                        type="primary"
                        size="large"
                        block
                        :loading="acting"
                        :disabled="primaryAction.indicator"
                        :color="primaryActionStatus ? statusButtonColor(primaryActionStatus) : undefined"
                        @click="primaryAction.handler"
                    >
                        {{ primaryAction.label }}
                    </n-button>
                    <div v-if="canChat || canEdit" class="detail-actions__row">
                        <div v-if="canChat" class="detail-chat-wrap detail-chat-wrap--block">
                            <n-button
                                size="large"
                                secondary
                                block
                                @click="chatOpen = true"
                            >
                                채팅
                            </n-button>
                            <span
                                v-if="chatUnread > 0"
                                class="detail-chat-wrap__dot"
                                :class="{ 'detail-chat-wrap__dot--num': chatUnread > 1 }"
                            >
                                {{ chatUnread > 9 ? '9+' : chatUnread > 1 ? chatUnread : '' }}
                            </span>
                        </div>
                        <n-button
                            v-if="canEdit"
                            size="large"
                            secondary
                            block
                            @click="goEdit"
                        >
                            수정
                        </n-button>
                    </div>
                </div>

                <!-- 요금 제안(오퍼) — 등록자는 전체 제안 관리, 기사는 제안/철회 -->
                <n-card
                    v-if="order && ['published', 'trading'].includes(order.status) && (canOffer || order.user_id === auth.user?.id)"
                    :bordered="true"
                    class="detail-block"
                >
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>요금 제안</span>
                            <n-tag v-if="offers.length" size="small" round type="warning">
                                {{ offers.length }}
                            </n-tag>
                        </n-space>
                    </template>

                    <!-- 기사 — 아직 제안 전이면 제안하기 버튼 -->
                    <template v-if="canOffer && !myPendingOffer">
                        <p class="offer-hint">
                            이 운행의 운임을 직접 제안해 보세요. 등록자가 제안을 비교한 뒤 수락하면 운행이 넘어옵니다.
                        </p>
                        <n-button size="small" secondary type="warning" @click="openOffer">
                            요금 제안하기
                        </n-button>
                    </template>

                    <!-- 기사 — 보낸 제안이 수락 대기 중이면 철회 가능 -->
                    <div v-else-if="myPendingOffer" class="offer-item">
                        <div class="offer-item__info">
                            <strong>{{ Number(myPendingOffer.amount).toLocaleString() }}원</strong>
                            <n-tag size="small" round type="warning">수락 대기</n-tag>
                            <span v-if="myPendingOffer.message" class="offer-item__msg">{{ myPendingOffer.message }}</span>
                        </div>
                        <n-button size="small" secondary type="warning" :loading="acting" @click="withdrawOffer(myPendingOffer)">
                            철회
                        </n-button>
                    </div>

                    <!-- 등록자 — 모든 제안을 비교하고 수락/거절 -->
                    <template v-else-if="order.user_id === auth.user?.id">
                        <n-empty
                            v-if="!offers.length"
                            description="아직 요금 제안이 없습니다."
                            :show-description="true"
                        />
                        <div v-else class="offer-list">
                            <div
                                v-for="offer in offers"
                                :key="offer.id"
                                class="offer-item"
                                :class="`offer-item--${offer.status}`"
                            >
                                <div class="offer-item__info">
                                    <strong>{{ Number(offer.amount).toLocaleString() }}원</strong>
                                    <span class="offer-item__driver">{{ offer.driver?.name || '기사' }}</span>
                                    <span v-if="offer.driver?.rating" class="offer-item__rating">
                                        ★ {{ offer.driver.rating }} ({{ offer.driver.review_count }})
                                    </span>
                                    <span class="offer-item__vehicle">
                                        {{ vehicleText(offer.driver?.vehicle) }}
                                        <VerifiedBadge :show="offer.driver?.vehicle?.is_verified" />
                                    </span>
                                    <n-tag size="small" round>{{ offer.status_label }}</n-tag>
                                    <span v-if="offer.message" class="offer-item__msg">{{ offer.message }}</span>
                                </div>
                                <div v-if="offer.status === 'pending'" class="offer-item__actions">
                                    <n-button size="small" type="primary" @click="goActions">
                                        처리할 일에서 처리
                                    </n-button>
                                </div>
                            </div>
                        </div>
                    </template>
                </n-card>

                <!-- 내 운행으로 가져오기 — 내용 하단의 카드와 동일한 넓이 주동작 버튼 -->
                <n-button
                    v-if="canClaim"
                    type="primary"
                    size="large"
                    block
                    class="detail-cta-btn"
                    :loading="acting"
                    @click="claim"
                >
                    내 운행으로 가져오기
                </n-button>

                <!-- 일반 관람자 안내 — 가져오기 요청은 기사(Driver)만 보낼 수 있다 -->
                <div v-if="showDriverOnlyNotice" class="detail-driver-only">
                    <BaseIcon name="info" :size="15" />
                    이 운행을 가져오려면 기사 역할이 필요합니다.
                </div>

                <!-- 리뷰 남기기 — 박스 밖 내용 하단의 전체 넓이 버튼 -->
                <n-button
                    v-if="canReview"
                    type="warning"
                    size="large"
                    block
                    class="detail-cta-btn"
                    @click="openReview"
                >
                    리뷰 남기기
                </n-button>
                <div v-else-if="myReview" class="detail-review-done">
                    <n-tag size="large" round color="#ffa940">
                        <BaseIcon name="star" :size="14" />
                        {{ myReview.rating }} 리뷰 완료
                    </n-tag>
                </div>

                <!-- 가져오기 요청 승인/거절 — 등록자가 내용 가장 하단에서 신청자별로 바로 처리 -->
                <div v-if="isRegistrantPending" class="detail-claim-list">
                    <div class="detail-claim-title">
                        <BaseIcon name="people" :size="15" />
                        가져오기 신청
                        <span class="detail-claim-title__count">{{ claims.length }}</span>
                    </div>
                    <n-card
                        v-for="claim in claims"
                        :key="claim.claim_id"
                        size="small"
                        :bordered="true"
                        class="detail-claim-card"
                    >
                        <div class="detail-claim-item__head">
                            <div class="detail-claim-item__avatar">{{ (claim.driver_name || '기사').charAt(0) }}</div>
                            <div class="detail-claim-item__who">
                                <strong class="detail-claim-item__name">{{ claim.driver_name || '기사' }}</strong>
                                <span class="detail-claim-item__time">{{ formatClaimTime(claim.requested_at) }} 신청</span>
                            </div>
                            <div v-if="claim.rating" class="detail-claim-item__rating">
                                <BaseIcon name="star" :size="13" />
                                <strong>{{ claim.rating }}</strong>
                                <span v-if="claim.review_count">({{ claim.review_count }})</span>
                            </div>
                        </div>
                        <div v-if="claim.vehicle" class="detail-claim-item__vehicle">
                            <BaseIcon name="car" :size="14" />
                            <span>{{ vehicleText(claim.vehicle) }}</span>
                            <VerifiedBadge :show="claim.vehicle?.is_verified" />
                        </div>
                        <template #footer>
                            <div class="detail-claim-actions">
                                <n-button
                                    size="large"
                                    secondary
                                    block
                                    :loading="acting"
                                    @click="rejectClaim(claim)"
                                >
                                    거절
                                </n-button>
                                <n-button
                                    size="large"
                                    type="primary"
                                    block
                                    :loading="acting"
                                    @click="approveClaim(claim)"
                                >
                                    승인
                                </n-button>
                            </div>
                        </template>
                    </n-card>
                </div>

                <!-- 리뷰 작성 모달 -->
                <n-modal
                    v-model:show="reviewOpen"
                    preset="card"
                    title="리뷰 남기기"
                    :style="{ maxWidth: '400px' }"
                >
                    <div class="review-modal">
                        <n-rate v-model:value="reviewRating" size="large" color="#ffa940" />
                        <n-select
                            v-model:value="reviewPhrase"
                            placeholder="자주 쓰는 문구를 선택하세요"
                            :options="reviewPhraseOptions"
                            clearable
                            @update:value="applyReviewPhrase"
                        />
                        <n-input
                            v-model:value="reviewContent"
                            type="textarea"
                            placeholder="운행 서비스는 어땠나요? (500자 이내)"
                            :maxlength="500"
                            :rows="4"
                        />
                    </div>
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="reviewOpen = false">취소</n-button>
                            <n-button type="primary" :loading="reviewSubmitting" @click="submitReview">
                                등록
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 취소 사유 모달 -->
                <n-modal
                    v-model:show="cancelOpen"
                    preset="card"
                    title="운행 취소"
                    :style="{ maxWidth: '400px' }"
                >
                    <p class="cancel-modal__desc">운행을 취소합니다. 취소 사유를 입력해 주세요. (선택)</p>
                    <n-input
                        v-model:value="cancelReason"
                        type="textarea"
                        placeholder="예) 차량 수리로 운행 불가"
                        :maxlength="500"
                        :rows="3"
                    />
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="cancelOpen = false">닫기</n-button>
                            <n-button type="error" :loading="acting" @click="confirmCancel">
                                운행 취소
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 가져오기 거절 사유 모달 — 사유(선택)와 함께 해당 신청만 거절 -->
                <n-modal
                    v-model:show="rejectOpen"
                    preset="card"
                    title="가져오기 거절"
                    :style="{ maxWidth: '400px' }"
                >
                    <p class="cancel-modal__desc">
                        {{ rejectTarget?.driver_name || '기사' }}님의 가져오기 요청을 거절할까요?
                        해당 신청만 거절되고 다른 신청자는 그대로 남습니다. 거절 사유를 입력하면 기사에게 전달됩니다. (선택)
                    </p>
                    <n-input
                        v-model:value="rejectReason"
                        type="textarea"
                        placeholder="예) 일정이 맞지 않아요"
                        :maxlength="500"
                        :rows="3"
                    />
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="rejectOpen = false">닫기</n-button>
                            <n-button type="error" :loading="rejectSubmitting" @click="submitRejectClaim">
                                거절
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 운행 완료 모달 — 실제 수익 입력 (입력 없으면 기대 금액 유지) -->
                <n-modal
                    v-model:show="completionOpen"
                    preset="card"
                    title="운행 완료"
                    :style="{ maxWidth: '400px' }"
                >
                    <p class="cancel-modal__desc">운행이 완료되었습니다. 실제 수익을 입력해 주세요. (입력하지 않으면 기대 금액으로 기록됩니다)</p>
                    <n-input-number
                        v-model:value="completionRevenue"
                        :min="0"
                        placeholder="실제 수익 (원)"
                        class="completion-revenue"
                    />
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="completionOpen = false">닫기</n-button>
                            <n-button type="primary" :loading="acting" @click="confirmComplete">
                                운행 완료
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 운행 완료 축하 모달 — 마지막 단계 기록 시 금액 입력 없이 바로 완료, 체크 애니메이션 + 폭죽 -->
                <n-modal
                    v-model:show="rideCompleteOpen"
                    preset="card"
                    :closable="true"
                    :mask-closable="true"
                    :style="{ maxWidth: '360px' }"
                >
                    <div class="complete-celebration">
                        <div class="complete-celebration__confetti" aria-hidden="true">
                            <span
                                v-for="n in 24"
                                :key="n"
                                class="complete-celebration__piece"
                                :style="confettiStyle(n)"
                            />
                        </div>
                        <div class="complete-celebration__check">
                            <BaseIcon name="check" :size="46" />
                        </div>
                        <p class="complete-celebration__title">운행 완료</p>
                        <p class="complete-celebration__msg">
                            기사님 감사합니다.<br />수고하셨습니다.
                        </p>
                        <n-button type="primary" size="large" block @click="rideCompleteOpen = false">
                            확인
                        </n-button>
                    </div>
                </n-modal>

                <!-- 요금 제안 작성 모달 — 기사가 운임과 메모를 입력 -->
                <n-modal
                    v-model:show="offerOpen"
                    preset="card"
                    title="요금 제안"
                    :style="{ maxWidth: '400px' }"
                >
                    <div class="offer-modal">
                        <p class="cancel-modal__desc">
                            이 운행의 운임을 제안하세요. 등록자가 여러 제안을 비교한 뒤 수락하면 운행이 넘어옵니다. (수락 전까지 기사 연락처는 비공개)
                        </p>
                        <n-input-number
                            v-model:value="offerAmount"
                            :min="1000"
                            :step="1000"
                            placeholder="제안 금액 (원)"
                            class="offer-amount"
                        />
                        <n-input
                            v-model:value="offerMessage"
                            type="textarea"
                            placeholder="메모 (선택) — 예) 오전 출발 가능합니다"
                            :maxlength="500"
                            :rows="3"
                        />
                    </div>
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="offerOpen = false">취소</n-button>
                            <n-button type="primary" :loading="offerSubmitting" @click="submitOffer">
                                제안 보내기
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 상세 정보 요청 모달 — 사유 선택 + 메모를 함께 등록자에게 전달 -->
                <n-modal
                    v-model:show="detailRequestOpen"
                    preset="card"
                    title="상세 정보 요청"
                    :style="{ maxWidth: '400px' }"
                >
                    <div class="detail-request-modal">
                        <p class="cancel-modal__desc">
                            정보가 부족합니다. 등록자에게 더 자세한 입력을 요청할 내용을 선택·입력해 주세요.
                        </p>
                        <n-select
                            v-model:value="detailRequestReason"
                            placeholder="요청 사유를 선택하세요"
                            :options="detailRequestReasonOptions"
                            clearable
                        />
                        <n-input
                            v-model:value="detailRequestMessage"
                            type="textarea"
                            placeholder="메모 (선택) — 예) 승객 수와 탑승 시간을 확인하고 싶습니다"
                            :maxlength="500"
                            :rows="3"
                        />
                    </div>
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="detailRequestOpen = false">취소</n-button>
                            <n-button type="primary" :loading="detailRequestSubmitting" @click="submitDetailRequest">
                                요청 보내기
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 운행 타임라인 — 상태·단계 변경 기록 (분쟁·정산 대응 근거) -->
                <n-card v-if="timeline.length" :bordered="true" class="detail-block detail-timeline">
                    <template #header>
                        <div class="detail-info-head">
                            <strong>진행 기록</strong>
                        </div>
                    </template>
                    <ol class="order-timeline">
                        <li v-for="(event, index) in timeline" :key="event.id" class="order-timeline__item">
                            <span class="order-timeline__rail">
                                <i
                                    class="order-timeline__dot"
                                    :class="{ 'order-timeline__dot--current': index === 0 }"
                                />
                            </span>
                            <div class="order-timeline__body">
                                <p class="order-timeline__text">
                                    <strong>{{ timelineLabel(event) }}</strong>
                                    <em v-if="event.note">{{ event.note }}</em>
                                </p>
                                <p class="order-timeline__meta">
                                    {{ event.user_name || '시스템' }} · {{ timelineTime(event.created_at_iso) }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </n-card>

                <!-- 공용 확인 다이얼로그 — 모든 상태 변경 전 확인 -->
                <ConfirmDialog
                    v-model:open="confirmState.open"
                    :title="confirmState.title"
                    :message="confirmState.message"
                    :confirm-text="confirmState.confirmText"
                    :type="confirmState.type"
                    :loading="acting"
                    @confirm="doConfirm"
                    @cancel="closeConfirm"
                />

                <!-- 운행 신고 다이얼로그 — 운영팀 접수 (진행 기록 옆) -->
                <ReportDialog
                    v-model:show="reportOpen"
                    target-type="order"
                    :target-id="order?.id"
                    :subject-text="reportSubject"
                />
            </div>
        </template>

        <!-- 하단 액션 바 — 기사 본인의 예약/운행중 운행에서만 고정으로 노출, 운행 절차는 원터치 진행 -->
        <div v-if="ridePanelOrder" class="detail-actionbar">
            <div v-if="canChat" class="detail-chat-wrap">
                <n-button size="large" secondary @click="chatOpen = true">
                    채팅
                </n-button>
                <span
                    v-if="chatUnread > 0"
                    class="detail-chat-wrap__dot"
                    :class="{ 'detail-chat-wrap__dot--num': chatUnread > 1 }"
                >
                    {{ chatUnread > 9 ? '9+' : chatUnread > 1 ? chatUnread : '' }}
                </span>
            </div>
            <n-button
                size="large"
                class="detail-actionbar__primary"
                :color="resolveCssVarColor((rideNextStep ?? { color: 'var(--status-completed)' }).color)"
                :text-color="rideBarTextColor"
                :loading="rideAdvancing"
                @click="advanceRideFromBar"
            >
                <template v-if="rideNextStep">
                    <span
                        class="detail-actionbar__step"
                        :class="{ 'detail-actionbar__step--dark': rideBarDarkText }"
                    >{{ rideStepCount }}/{{ RIDE_PROCEDURE_STEPS.length }}</span>
                    다음: {{ rideNextStep.label }}
                </template>
                <template v-else>운행 완료</template>
            </n-button>
        </div>

        <!-- 등록자와 대화 바텀 시트 — 하단 채팅 버튼으로 아래에서 위로 열린다 -->
        <n-drawer
            v-model:show="chatOpen"
            placement="bottom"
            :height="'72%'"
            :auto-focus="false"
            :trap-focus="false"
        >
            <div class="chat-sheet">
                <div class="chat-sheet__handle" />
                <div class="chat-sheet__head">
                    <span class="chat-sheet__title">등록자와 대화</span>
                    <span class="chat-sheet__name">{{ chatCounterpartName || '등록자' }}</span>
                    <button type="button" class="chat-sheet__close" aria-label="닫기" @click="chatOpen = false">
                        <BaseIcon name="close" :size="16" />
                    </button>
                </div>
                <OrderDetailChat
                    v-if="chatOpen && chatTargetId"
                    :order-id="order.id"
                    :user-id="chatTargetId"
                    :counterpart-name="chatCounterpartName"
                    hide-head
                    bare
                    class="chat-sheet__body"
                />
            </div>
        </n-drawer>
    </div>
</template>

<style scoped>
.cancel-modal__desc {
    margin: 0 0 12px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.6;
}
.detail-body {
    display: block;
    min-height: 200px;
}

/* 로딩 스켈레톤 — 히어로 + 정보 카드 골격 */
.detail-skeleton {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 16px;
}

.detail-pending-card {
    background: linear-gradient(180deg, rgba(96, 226, 187, 0.08) 0%, rgba(96, 226, 187, 0.02) 100%);
}

.detail-pending-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.detail-pending-card__eyebrow {
    margin: 0 0 4px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-muted);
}

.detail-pending-card__title {
    font-size: 18px;
    font-weight: 800;
    line-height: 1.3;
    color: var(--text);
}

.detail-pending-card__timer {
    flex-shrink: 0;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255, 176, 32, 0.12);
    color: #ffb020;
    font-size: 14px;
    font-weight: 800;
    line-height: 1;
}

.detail-pending-card__desc {
    margin: 14px 0 0;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.6;
}

.detail-pending-card__actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 16px;
}

.detail-skeleton__hero {
    height: 120px;
}

.detail-skeleton__card {
    display: flex;
    flex-direction: column;
}

.detail-block {
    margin-bottom: 16px;
    border-radius: var(--card-radius);
}

.detail-message {
    margin-bottom: 14px;
}

/* 일반 화면 액션 — 박스 밖 내용 하단의 전체 넓이 버튼 (창을 벗어나지 않게 정렬) */
.detail-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
}

.detail-actions__row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.detail-actions__row .n-button {
    flex: 1 1 45%;
    min-width: 0;
}

/* 내용 하단 주동작 버튼(가져오기·리뷰 남기기) — 카드와 동일한 넓이 */
.detail-cta-btn {
    width: 100%;
    margin-bottom: 16px;
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

/* 일반 관람자 안내 — 가져오기 요청은 기사만 가능 */
.detail-driver-only {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-bottom: 16px;
    padding: 12px 16px;
    border: 1px dashed var(--border);
    border-radius: 14px;
    font-size: 13px;
    color: var(--text-muted);
    background: color-mix(in srgb, var(--text-muted) 5%, transparent);
}

/* 가져오기 요청 승인/거절 — 신청자별 카드 목록 (여러 드라이버 동시 신청) */
.detail-claim-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
    margin-top: 16px;
}

.detail-claim-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
}

.detail-claim-title__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 400;
    /* brand 채움(#36adff 라이트·#63e2b7 다크)은 모두 밝은 색 — 14:05에 정리된 형제 건수 칩과 동일하게 어두운 글자 */
    color: #07120e;
    background: var(--brand);
}

.detail-claim-card {
    border-radius: var(--card-radius);
}

/* 기사 정보 헤더 — 아바타 + 이름/신청시각 + 평점 */
.detail-claim-item__head {
    display: flex;
    align-items: center;
    gap: 12px;
}

.detail-claim-item__avatar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 16px;
    font-weight: 700;
}

.detail-claim-item__who {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.detail-claim-item__name {
    font-size: 15px;
    font-weight: 700;
}

.detail-claim-item__time {
    font-size: 12px;
    color: var(--text-muted);
}

.detail-claim-item__rating {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 3px;
    padding: 4px 8px;
    border-radius: 8px;
    background: rgba(240, 160, 32, 0.1);
    font-size: 13px;
    color: #f0a020;
}

.detail-claim-item__rating strong {
    font-weight: 700;
    color: var(--text);
}

.detail-claim-item__rating span {
    color: var(--text-muted);
}

/* 기사 차량 정보 */
.detail-claim-item__vehicle {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 9px 12px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.04);
    font-size: 13px;
    color: var(--text);
}

html.dark .detail-claim-item__vehicle {
    background: rgba(255, 255, 255, 0.06);
}

.detail-claim-item__vehicle span {
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 승인/거절 — 카드 하단 액션 (구분선으로 분리) */
.detail-claim-card :deep(.n-card__footer) {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

html.dark .detail-claim-card :deep(.n-card__footer) {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.detail-claim-actions {
    display: flex;
    gap: 10px;
}

.detail-claim-actions .n-button {
    flex: 1;
}

.detail-review-done {
    margin-top: 16px;
    display: flex;
    justify-content: center;
}

.review-modal {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* 상세 정보 요청 모달 — 사유 셀렉트 + 메모 입력 */
.detail-request-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.filter-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.completion-revenue {
    width: 100%;
}

/* ── 운행 완료 축하 모달 — 체크 애니메이션 + 폭죽 ── */
.complete-celebration {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 26px 6px 6px;
    text-align: center;
}

.complete-celebration__confetti {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.complete-celebration__piece {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 9px;
    height: 9px;
    border-radius: 2px;
    background: var(--confetti-color);
    opacity: 0;
    animation: celebration-burst 1.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes celebration-burst {
    0% {
        transform: translate(-50%, -50%) scale(0.4) rotate(0deg);
        opacity: 1;
    }
    55% {
        opacity: 1;
    }
    100% {
        transform: translate(calc(-50% + var(--cx)), calc(-50% + var(--cy))) scale(1) rotate(300deg);
        opacity: 0;
    }
}

.complete-celebration__check {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 88px;
    height: 88px;
    border-radius: 50%;
    background: linear-gradient(135deg, #52c41a, #73d13d);
    color: #ffffff;
    box-shadow: 0 10px 24px rgba(82, 196, 26, 0.35);
    animation: celebration-pop 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes celebration-pop {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    70% {
        transform: scale(1.12);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.complete-celebration__title {
    margin: 8px 0 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
}

.complete-celebration__msg {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.6;
    color: var(--text);
}

/* ── 요금 제안(오퍼) ── */
.offer-hint {
    margin: 0 0 12px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.6;
}

.offer-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.offer-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
}

.offer-item--accepted {
    border-color: var(--status-accepted);
    background: color-mix(in srgb, var(--status-accepted) 6%, transparent);
}

.offer-item--rejected,
.offer-item--cancelled {
    opacity: 0.55;
}

.offer-item__info {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.offer-item__info strong {
    font-size: 11px;
}

.offer-item__driver {
    font-size: 11px;
    font-weight: 600;
}

.offer-item__rating {
    color: #ffa940;
    font-size: 11px;
    font-weight: 700;
}

.offer-item__vehicle {
    color: var(--text-muted);
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.offer-item__msg {
    width: 100%;
    color: var(--text-muted);
    font-size: 11px;
}

.offer-item__actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.offer-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.offer-amount {
    width: 100%;
}

/* 상태 관리 — 다음 단계 힌트 */
.detail-next-hint {
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 25%, transparent);
    color: var(--text-muted);
    font-size: 11px;
}

.detail-next-hint strong {
    color: var(--brand);
    font-weight: 800;
}

/* 운행 정보 — 라벨/값 행 (심플 카드) */
/* 운행 정보 헤더 — 제목 + 우측 끝 상세 정보 요청 버튼 */
.detail-info-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
}

/* 운행 정보 그룹 제목 — 서비스/예약/기타 구분 */
.detail-group-title {
    margin: 16px 0 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--text-muted);
}

.detail-group-title:first-child {
    margin-top: 0;
}

.detail-rows {
    display: flex;
    flex-direction: column;
}

.detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row span {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 11px;
}

.detail-row strong {
    text-align: right;
    font-size: 11px;
}

/* 일정 카드 */
.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.schedule-card {
    border-radius: 10px;
}

.schedule-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
}

.schedule-time {
    font-size: 11px;
}

.schedule-date {
    color: var(--text-muted);
    font-size: 11px;
}

.schedule-card__route {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
}

.schedule-card__arrow {
    color: var(--accent);
    font-size: 12px;
    font-weight: 700;
}

.schedule-card__meta {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 11px;
}

/* 셋트 그룹 일정 리스트 */
.group-schedule-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.group-schedule-item {
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.group-schedule-item--current {
    border-color: color-mix(in srgb, var(--brand) 50%, transparent);
    background: color-mix(in srgb, var(--brand) 5%, transparent);
}

/* 셋트 일정 금액 */
.group-schedule-item__amount {
    margin-left: auto;
    font-size: 11px;
    font-weight: 700;
    color: var(--text);
}

.group-schedule-item__head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.group-schedule-item__head strong {
    font-size: 11px;
}

.group-schedule-item__detach {
    margin-left: auto;
}

.group-schedule-item__route {
    margin-top: 6px;
    font-size: 11px;
    font-weight: 600;
}

.group-schedule-item__meta {
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 11px;
}

/* ── 히어로 헤더 ── */
.detail-page {
    position: relative;
}

.detail-page--bar {
    padding-bottom: 92px;
}

.detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 18px 18px 16px;
    margin-bottom: 16px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--brand), color-mix(in srgb, var(--brand) 35%, transparent) 60%, color-mix(in srgb, var(--status-settled) 18%, transparent));
    color: #ffffff;
}

.detail-hero__eyebrow {
    margin: 0 0 8px;
    font-size: 11px;
    letter-spacing: 0.3px;
    opacity: 0.85;
}

.detail-hero__route {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 15px;
    font-weight: 800;
    line-height: 1.3;
}

.detail-hero__loc {
    max-width: 42vw;
    word-break: keep-all;
}

.detail-hero__arrow {
    color: rgba(255, 255, 255, 0.75);
    font-size: 16px;
}

.detail-hero__badges {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.hero-badge {
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    color: #ffffff;
}

.hero-badge--waiting {
    background: var(--brand);
    border: 1px solid rgba(255, 255, 255, 0.4);
}

.hero-badge--priority {
    background: #722ed1;
}

.hero-badge--urgent {
    background: #e5484d;
    animation: hero-pulse 1.6s ease-in-out infinite;
}

@keyframes hero-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(229, 72, 77, 0.55);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(229, 72, 77, 0);
    }
}

.detail-hero__meta {
    margin: 8px 0 0;
    font-size: 11px;
    opacity: 0.9;
}

/* 운행 신고 — 히어로 아래 작은 유틸 버튼 (문의가 아니라 문제 → 레드 계열)
   히어로는 백색 텍스트 영역이므로 muted(페이지 배경용) 대신 백색 계열로 대비를 맞춘다 */
.detail-hero__report {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    align-self: flex-start;
    margin-top: 10px;
    padding: 2px 6px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: rgba(255, 255, 255, 0.88);
    font-size: 10px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease;
}
.detail-hero__report:hover {
    background: #e5484d;
    color: #ffffff;
}

.detail-hero__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

/* 금액·하트 한 줄 — 금액을 오른쪽 끝에 두고 하트가 그 옆에 붙는다 */
.detail-hero__side-top {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    max-width: 100%;
}

.detail-hero__amount {
    font-size: 18px;
    font-weight: 900;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}

/* 찜 하트 — 테두리 없는 심플 하트. 미찜: 흰 테두리 하트 / 찜: 빨간 채움 하트 */
.detail-hero__fav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3px;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.92);
    cursor: pointer;
    transition: color 0.15s ease, transform 0.1s ease;
}
.detail-hero__fav:hover {
    color: #ffffff;
    background: transparent;
}
.detail-hero__fav:active {
    transform: scale(0.88);
}
.detail-hero__fav--on {
    color: #e5484d;
}

/* 히어로와 운행 정보 사이 — 운행 진행 스테퍼 (짧은 문구, 단계별 색상 점) */
.ride-stepper {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: var(--card-pad);
    margin-bottom: 14px;
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.ride-steps {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
    overflow-x: auto;
}

.ride-steps__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}

.ride-steps__dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--border-strong, var(--border));
    transition: background 0.2s ease;
}

.ride-steps__item--current .ride-steps__dot {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 18%, transparent);
}

.ride-steps__label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted);
    white-space: nowrap;
}

.ride-steps__item--current .ride-steps__label {
    color: var(--text);
    font-weight: 800;
}

.ride-steps__time {
    /* 앱 최소 가독 크기(10px) — 유일한 9px 잔존 제거 */
    font-size: 10px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--text-muted);
    white-space: nowrap;
}

.ride-steps__item--done .ride-steps__time,
.ride-steps__item--current .ride-steps__time {
    color: var(--text);
}

.ride-steps__line {
    flex: 1;
    min-width: 10px;
    height: 3px;
    background: var(--border);
}

.ride-steps__line--done {
    background: var(--brand);
}

.ride-stepper__count {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 800;
    color: var(--text-muted);
}

/* 운행 정보 — 등록자 링크 / 긴급 카운트다운 */
.detail-link {
    color: var(--accent);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 3px;
}

/* 고객 연락처 — tel 링크 (누르면 전화 연결) */
.detail-phone {
    color: var(--brand);
    font-weight: 800;
    text-decoration: none;
    border-bottom: 1px dashed color-mix(in srgb, var(--brand) 55%, transparent);
    padding-bottom: 1px;
}

/* ── 경로 지도 ── */
.detail-map {
    margin: 4px 0 10px;
}

.detail-map__frame {
    display: block;
    width: 100%;
    height: 220px;
    border: 0;
    border-radius: 12px;
}

.detail-map__placeholder {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 18px 16px;
    border: 1px dashed var(--border);
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .detail-map__placeholder {
    background: rgba(255, 255, 255, 0.03);
}

.detail-map__label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}

.detail-map__links {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 11px;
}

.detail-map__links a {
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
}

.detail-map__hint {
    color: var(--text-muted);
    font-size: 11px;
}

/* ── 하단 액션 바 ── */
.detail-actionbar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
    background: color-mix(in srgb, var(--surface) 92%, transparent);
    backdrop-filter: blur(12px);
    border-top: 1px solid var(--border);
}

.detail-actionbar__primary {
    flex: 1;
    min-width: 0;
}

/* 원터치 운행 절차 버튼 — 진행도 배지 */
.detail-actionbar__step {
    margin-right: 6px;
    padding: 1px 6px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
    font-size: 10px;
    font-weight: 400;
}
/* 진행도 배지가 어두운 글자(#101418) 위일 때 — 흰 틴트 대신 어두운 틴트로 대비 유지 */
.detail-actionbar__step--dark {
    background: rgba(16, 20, 24, 0.16);
}

/* 채팅 버튼 — 상대가 보낸 안 읽은 채팅이 오면 빨간점/숫자로 표시 */
.detail-chat-wrap {
    position: relative;
    display: inline-flex;
}

.detail-chat-wrap--block {
    display: flex;
    flex: 1 1 45%;
    min-width: 0;
}

.detail-chat-wrap--block > .n-button {
    flex: 1;
}

/* ── 진행 기록 타임라인 — 상태·단계 변경 이력 (최신이 위) ── */
.order-timeline {
    list-style: none;
    margin: 0;
    padding: 0;
}
.order-timeline__item {
    display: flex;
    gap: 12px;
}
.order-timeline__rail {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 12px;
    flex-shrink: 0;
}
/* 세로선 — 마지막 항목까지 이어진다 */
.order-timeline__rail::before {
    content: '';
    position: absolute;
    top: 16px;
    bottom: 0;
    left: 50%;
    width: 1px;
    margin-left: -0.5px;
    background: var(--border);
}
.order-timeline__item:last-child .order-timeline__rail::before {
    display: none;
}
.order-timeline__dot {
    position: relative;
    width: 8px;
    height: 8px;
    margin-top: 4px;
    border-radius: 50%;
    background: var(--border);
    z-index: 1;
}
/* 가장 최근 기록 — 현재 상태를 강조한다 */
.order-timeline__dot--current {
    background: var(--brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
}
.order-timeline__body {
    flex: 1;
    min-width: 0;
    padding-bottom: 16px;
}
.order-timeline__text {
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 11px;
    line-height: 1.45;
    word-break: break-word;
}
.order-timeline__text strong {
    font-weight: 700;
    color: var(--text);
}
.order-timeline__text em {
    font-style: normal;
    color: var(--text-muted);
}
.order-timeline__meta {
    margin: 4px 0 0;
    font-size: 10px;
    color: var(--text-muted);
}


.detail-chat-wrap__dot {
    position: absolute;
    top: -6px;
    right: -6px;
    z-index: 2;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ff4d4f;
    border: 2px solid var(--surface);
}

.detail-chat-wrap__dot--num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: auto;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
    color: #fff;
}

/* ── 등록자와 대화 바텀 시트 — 아래에서 위로 슬라이드 ── */
.chat-sheet {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.chat-sheet__handle {
    flex-shrink: 0;
    align-self: center;
    width: 40px;
    height: 4px;
    margin: 9px 0 3px;
    border-radius: 999px;
    background: var(--border);
}

.chat-sheet__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px 10px;
    border-bottom: 1px solid var(--border);
}

.chat-sheet__title {
    font-size: 13px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.chat-sheet__name {
    flex-shrink: 0;
    max-width: 45%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.chat-sheet__close {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--text-muted);
    font-family: inherit;
    cursor: pointer;
    transition: background 0.12s ease;
}

.chat-sheet__close:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.chat-sheet__body {
    flex: 1;
    min-height: 0;
}
</style>