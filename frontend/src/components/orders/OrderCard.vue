<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { statusColorVar } from '../../utils/colors';
import { relativeDateLabel } from '../../utils/dateText';
import { trackClick, trackImpression } from '../../utils/tracking';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    highlight: {
        type: Boolean,
        default: false,
    },
    // 상태 배지 옆 추가 텍스트 — 요청보냄의 '남은 시간' 등 (빈 값이면 미표시)
    statusExtra: {
        type: String,
        default: '',
    },
    // 상태 배지 표시 재정의 — 휴지통의 '요청취소' 등 실제 상태와 다르게 보여야 할 때 { label, color }
    statusOverride: {
        type: Object,
        default: null,
    },
    // 추천 근거 표시 — 홈 추천 카드에서만 켠다 (마켓·내 운행 등은 심플 카드 유지)
    showMatchReasons: {
        type: Boolean,
        default: false,
    },
    // 행동 신호(노출·클릭) 컨텍스트 — 홈 추천·마켓 목록에서만 넘긴다 { scope, section, rank }
    tracking: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['toggle']);

const router = useRouter();
const auth = useAuthStore();

// 추천 근거 문구 — 백엔드 match_reasons (홈 추천에서만 표시)
const matchReasons = computed(() => props.order.matchReasons ?? props.order.match_reasons ?? []);

const open = () => {
    // 행동 신호 — 카드 클릭으로 상세에 진입 (추천·마켓 노출 카드만)
    if (props.tracking) {
        trackClick({
            orderId: props.order.id,
            scope: props.tracking.scope,
            section: props.tracking.section,
            rank: props.tracking.rank,
        });
    }

    router.push({ name: 'order-detail', params: { id: props.order.id } });
};

// 카드가 실제로 화면에 그려지면 노출 신호 — 같은 자리는 세션 동안 한 번만
onMounted(() => {
    if (props.tracking) {
        trackImpression({
            orderId: props.order.id,
            scope: props.tracking.scope,
            section: props.tracking.section,
            rank: props.tracking.rank,
        });
    }
});

// 선택 모드에서는 카드 클릭 대신 체크만 토글한다
const handleClick = () => {
    if (props.selectable) {
        emit('toggle', props.order.id);

        return;
    }

    open();
};

// 상태별 배지 색상 — 중앙 팔레트(utils/colors.js)에서 참조 (테마 자동 적용)
// 재정의(statusOverride)가 있으면 그 라벨·색상을 우선 사용한다 (휴지통 '요청취소' 등)
const statusText = computed(() => {
    if (props.statusOverride?.label) {
        return props.statusOverride.label;
    }

    // 승인 대기 상태 — 요청을 보낸 기사(claimant)와 등록자에게만 '수락대기'로 보여준다.
    // 그 외 드라이버/관람자에게는 아직 가져올 수 있는 운행으로 안내한다.
    if (props.order.status === 'acceptance_pending') {
        const me = auth.user?.id;
        const mine = props.order.claimantUserId === me || props.order.userId === me;

        return mine ? props.order.statusLabel : '가져오기 가능';
    }

    return props.order.statusLabel;
});
const statusColor = computed(() => {
    if (props.statusOverride) return props.statusOverride.color;

    // 예약(가져오기 수락) 배지 — 오렌지색 (테마 대응은 거래중 색상 재사용)
    if (props.order.status === 'accepted') return 'var(--status-trading)';

    return statusColorVar[props.order.status] ?? 'var(--status-draft)';
});
const statusBadgeClass = computed(() => (props.statusOverride ? 'status-badge--override' : `status-badge--${props.order.status}`));

// 공개 상태 — 배지는 정보 가치가 낮아 생략하고 배지 자리에 금액을 노출한다
const isPublished = computed(() => props.order.status === 'published');

// 카드 일시 — 오늘/내일/모레는 상대 라벨, 이후 날짜는 "9/2(수)" 유지
const dateLabel = computed(() => relativeDateLabel(props.order.date, props.order.sortDate));

// 승인받은 시간 — 오늘 승인 건은 '승인 HH:MM', 다른 날은 '승인 M/D HH:MM'
const approvedTimeText = computed(() => {
    const at = props.order.approvedAt;
    if (!at) return '';

    const approved = new Date(at);
    const now = new Date();
    const time = `${String(approved.getHours()).padStart(2, '0')}:${String(approved.getMinutes()).padStart(2, '0')}`;
    const sameDay = approved.getFullYear() === now.getFullYear()
        && approved.getMonth() === now.getMonth()
        && approved.getDate() === now.getDate();

    return sameDay ? `승인 ${time}` : `승인 ${approved.getMonth() + 1}/${approved.getDate()} ${time}`;
});

// 운행 시작 전 남은 시간 — 내 운행(진행중) 카드 위 좌측에 표시.
// 서비스 일시가 아직 지나지 않은 진행중(수락·운행) 운행만 계산한다.
// 남은 시간에 따라 긴급도 색이 달라진다: 12시간+ 초록 → 6시간+ 주황 → 3시간+ 노랑 → 3시간 미만 틸
const startRemain = computed(() => {
    const active = props.order.status === 'accepted' || props.order.status === 'driving';

    if (!active || !props.order.sortDate || !props.order.sortTime) return null;

    const start = new Date(`${props.order.sortDate}T${props.order.sortTime}:00`);
    const remainMs = start.getTime() - Date.now();
    if (remainMs <= 0) return null;

    const minutes = Math.floor(remainMs / 60000);

    let text = '곧 시작';

    if (minutes >= 1) {
        const days = Math.floor(minutes / 1440);
        const hours = Math.floor((minutes % 1440) / 60);
        const mins = minutes % 60;

        if (days > 0) text = `시작 ${days}일 ${hours}시간 전`;
        else if (hours > 0) text = `시작 ${hours}시간 ${mins}분 전`;
        else text = `시작 ${mins}분 전`;
    }

    let level = 'teal';

    if (minutes >= 12 * 60) level = 'green';
    else if (minutes >= 6 * 60) level = 'orange';
    else if (minutes >= 3 * 60) level = 'yellow';

    return { text, level };
});

// 긴급도별 아이콘 — 휴면(달) → 준비(알람) → 이동(차) → 주행(속도계)
const START_REMAIN_ICONS = {
    green: 'moon',
    orange: 'alarm',
    yellow: 'car',
    teal: 'speedometer',
};

const startRemainIcon = computed(() => START_REMAIN_ICONS[startRemain.value?.level] ?? 'time');
</script>

<template>
    <div class="order-card-wrap">
        <!-- 카드 밖 위쪽 한 줄 — 좌측: 운행 시작 전 시간 / 우측: 승인받은 시간 (카드 안에는 운행정보만) -->
        <div class="order-card-topline">
            <span v-if="startRemain" class="order-card-start" :class="`order-card-start--${startRemain.level}`">
                <BaseIcon :name="startRemainIcon" :size="12" />
                {{ startRemain.text }}
            </span>
            <span v-if="approvedTimeText" class="order-card-approved">
                <BaseIcon name="check-done" :size="12" />
                {{ approvedTimeText }}
            </span>
        </div>
        <article
        class="order-card"
        :class="{ 'order-card--selected': selected, 'order-card--selectable': selectable, 'order-card--highlight': highlight }"
        role="button"
        tabindex="0"
        @click="handleClick"
        @keydown.enter="handleClick"
    >
        <div class="order-card__head">
            <div class="order-card__route">
                <div class="order-card__route-top">
                    <span v-if="order.isPriority" class="order-card__priority" title="긴급 운행">긴급</span>
                    <strong>{{ order.route }}</strong>
                    <span v-if="order.isUrgent" class="order-card__urgent" title="곧 운행 시작">임박</span>
                </div>
                <div class="order-card__route-bottom">
                    <span class="order-card__datetime">
                        {{ dateLabel }} {{ order.time }}
                    </span>
                    <span v-if="order.flightNumber" class="order-card__flight" title="항공편">
                        <BaseIcon name="airplane" :size="12" />
                        {{ order.flightNumber }}
                    </span>
                </div>
            </div>
            <div class="order-card__side">
                <n-checkbox
                    v-if="selectable"
                    :checked="selected"
                    class="order-card__check"
                    @click.stop
                    @update:checked="emit('toggle', order.id)"
                />
                <!-- 공개 상태는 배지 대신 금액 — 그 외 상태는 상태 배지 유지 -->
                <span v-else-if="isPublished" class="order-card__amount">
                    <BaseIcon class="order-card__coin-icon" name="coin" :size="16" />
                    {{ order.amount }}
                </span>
                <span v-else class="status-badge" :class="statusBadgeClass" :style="{ background: statusColor, borderColor: statusColor }">
                    {{ statusText }}
                </span>
                <span v-if="statusExtra" class="order-card__status-extra">{{ statusExtra }}</span>
                <!-- 차량 — 한 줄 -->
                <span v-if="order.vehicle" class="order-card__side-line">
                    <BaseIcon name="car" :size="12" />
                    {{ order.vehicle }}
                </span>
                <!-- 인원·캐리어 — 같은 줄 (아이콘 + 숫자) -->
                <span v-if="order.passengerCount || order.luggageCount" class="order-card__side-line">
                    <BaseIcon v-if="order.passengerCount" name="people" :size="12" />
                    <span v-if="order.passengerCount">{{ order.passengerCount }}</span>
                    <BaseIcon v-if="order.luggageCount" name="bag" :size="12" />
                    <span v-if="order.luggageCount">{{ order.luggageCount }}</span>
                </span>
            </div>
        </div>
        <!-- 어필 태그 — 등록자가 붙인 운행 설명 태그 (비우면 표시 안 함) -->
        <div v-if="order.tags?.length" class="order-card__tags">
            <span v-for="tag in order.tags" :key="tag" class="order-card__tag">{{ tag }}</span>
        </div>
        <!-- 추천 근거 — 홈 추천에서 '왜 추천했는지' 체크리스트 (퍼센트 없이 이유 문구만) -->
        <div v-if="showMatchReasons && matchReasons.length" class="order-card__reasons">
            <span v-for="reason in matchReasons" :key="reason" class="order-card__reason">
                <BaseIcon name="check" :size="11" />
                {{ reason }}
            </span>
        </div>
        <!-- 금액 행 — 공개가 아닌 상태(배지가 금액 자리를 사용) 또는 선택 모드에서 하단 표시 -->
        <div v-if="selectable || !isPublished" class="order-card__meta">
            <span class="order-card__amount">
                <BaseIcon class="order-card__coin-icon" name="coin" :size="17" />
                {{ order.amount }}
            </span>
        </div>
    </article>
    </div>
</template>

<style scoped>
.order-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: var(--card-pad);
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.order-card:hover,
.order-card:focus-visible {
    border-color: var(--brand);
    box-shadow: 0 4px 16px color-mix(in srgb, var(--brand) 12%, transparent);
    transform: translateY(-1px);
    outline: none;
}

.order-card--selectable {
    cursor: pointer;
}

.order-card--selected {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 4%, transparent);
}

/* 신규 운행 하이라이트 — 슬라이드 인 + 브랜드 배경 깜빡임 */
.order-card--highlight {
    animation: card-highlight-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2), card-highlight-pulse 2.4s ease 0.4s infinite;
    border-color: color-mix(in srgb, var(--brand) 55%, transparent);
}

@keyframes card-highlight-in {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes card-highlight-pulse {
    0%,
    100% {
        background: var(--surface);
    }
    50% {
        background: color-mix(in srgb, var(--brand) 12%, transparent);
    }
}

.order-card__check {
    flex-shrink: 0;
}

.order-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.order-card__route {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.order-card__route-top {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.order-card__route-top strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.order-card__route-bottom {
    display: flex;
    align-items: center;
    margin-top: 5px;
}

.order-card__datetime {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 500;
}

/* 항공편 — 날짜 옆 아이콘 + 텍스트 (알약 스타일 없이 텍스트로) */
.order-card__flight {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.1px;
    white-space: nowrap;
}

.order-card__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}

/* 상태 배지 옆 추가 텍스트 — 요청보냄 남은 시간 등 */
.order-card__status-extra {
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.order-card__side-line {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    color: var(--text-muted);
    font-size: 11px;
    white-space: nowrap;
    opacity: 0.85;
}

.order-card__meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: 10px;
    padding-top: 9px;
    border-top: 1px solid var(--border);
}

/* 어필 태그 — 등록자가 붙인 운행 설명 태그 뭉치. 정보(노선·시간)를 압도하지 않게 연한 무채색 */
.order-card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}
.order-card__tag {
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}

/* 추천 근거 체크리스트 — 홈 추천 카드에서만 표시. 퍼센트·점수 없이 이유 문구만 연하게 */
.order-card__reasons {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--border);
}
.order-card__reason {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 500;
    line-height: 1.4;
}
.order-card__reason :deep(svg) {
    flex-shrink: 0;
    color: var(--brand);
}

/* 카드 밖 상단 래퍼 — 시작 전 시간(좌)·승인 시간(우)을 카드 위에 둔다 */
.order-card-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.order-card-wrap .order-card {
    width: 100%;
}

.order-card-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
}

/* 운행 시작 전 남은 시간 — 카드 위 좌측 (긴급도에 따라 색상 변화) */
.order-card-start {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding-left: 2px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

/* 남은 시간 색 — 12시간+ 초록 → 6시간+ 주황 → 3시간+ 노랑 → 3시간 미만 틸 */
.order-card-start--green {
    color: #18a058;
}
.order-card-start--orange {
    color: #ffa940;
}
.order-card-start--yellow {
    color: #f0a800;
}
.order-card-start--teal {
    color: #13c2c2;
}
html.dark .order-card-start--green {
    color: #3fae7b;
}
html.dark .order-card-start--orange {
    color: #e8a34e;
}
html.dark .order-card-start--yellow {
    color: #e8bc3f;
}
html.dark .order-card-start--teal {
    color: #31caca;
}

/* 승인받은 시간 — 카드 위 우측 (단독 표시 시에도 우측 유지) */
.order-card-approved {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
    padding-right: 2px;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}

/* 임박 배지 — 곧 운행 시작 (빨강 펄스) */
.order-card__urgent {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    letter-spacing: -0.2px;
    background: var(--danger);
    color: #ffffff;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--danger) 40%, transparent);
    animation: urgent-pulse 1.6s ease-in-out infinite;
}

@keyframes urgent-pulse {
    0%,
    100% {
        box-shadow: 0 1px 4px color-mix(in srgb, var(--danger) 40%, transparent);
    }
    50% {
        box-shadow: 0 1px 8px color-mix(in srgb, var(--danger) 80%, transparent);
    }
}

.order-card__priority {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    letter-spacing: -0.2px;
    background: var(--status-settled);
    color: #ffffff;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--status-settled) 40%, transparent);
}

.order-card__amount {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--brand);
    font-weight: 800;
    font-size: 14px;
    letter-spacing: -0.3px;
    white-space: nowrap;
}

/* 동전 아이콘 — 자체 금색·립·₩을 가진 SVG라 색상 지정 없이 그림자만 살짝 */
.order-card__coin-icon {
    flex-shrink: 0;
    filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.18));
}

/* 상태 배지 */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 6px;
    border-radius: 999px;
    color: #ffffff;
    font-size: 10px;
    white-space: nowrap;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

/* 밝은 배경 배지 — 흰 글자 대비가 약하므로 라이트·다크 모두 어두운 글자 (접근성) */
.status-badge--published,
.status-badge--driving,
.status-badge--trading,
.status-badge--acceptance-pending,
/* accepted(예약) 배지는 script에서 앰버(--status-trading)로 칠하고, completed(완료)는 초록 — 둘 다 밝아 어두운 글자 */
.status-badge--accepted,
.status-badge--completed,
html.dark .status-badge--published,
html.dark .status-badge--driving {
    color: #101418;
}
</style>
