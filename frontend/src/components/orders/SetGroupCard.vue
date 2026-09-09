<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { statusColorVar } from '../../utils/colors';
import { relativeDateLabel } from '../../utils/dateText';
import { trackClick, trackImpression } from '../../utils/tracking';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    set: {
        type: Object,
        required: true,
    },
    highlight: {
        type: Boolean,
        default: false,
    },
    // 행동 신호(노출·클릭) 컨텍스트 — 홈 추천·마켓 목록에서만 넘긴다 { scope, section, rank }
    tracking: {
        type: Object,
        default: null,
    },
});

const router = useRouter();

// 셋트 카드 클릭 — 첫 번째 운행 상세로 이동
const open = () => {
    // 행동 신호 — 셋트 카드 클릭 (meta.group으로 단일 운행과 구분)
    if (props.tracking) {
        trackClick({
            orderId: props.set.firstOrderId,
            scope: props.tracking.scope,
            section: props.tracking.section,
            rank: props.tracking.rank,
            meta: { group: true },
        });
    }

    if (props.set.firstOrderId) {
        router.push({ name: 'order-detail', params: { id: props.set.firstOrderId } });
    }
};

// 카드가 실제로 화면에 그려지면 노출 신호 — 같은 셋트는 세션 동안 한 번만
onMounted(() => {
    if (props.tracking) {
        trackImpression({
            orderId: props.set.firstOrderId,
            scope: props.tracking.scope,
            section: props.tracking.section,
            rank: props.tracking.rank,
            meta: { group: true },
        });
    }
});

// 상태별 배지 색상 — 중앙 팔레트에서 참조 (mixed=회색)
// 예약(가져오기 수락) 배지는 오렌지색 — 테마 대응은 거래중 색상 재사용
const statusColor = computed(() => {
    if (props.set.status === 'accepted') return 'var(--status-trading)';

    return statusColorVar[props.set.status] ?? 'var(--status-draft)';
});

// 셋트명에서 "KLOOK 8월 셋트" 앞 글자
const avatarText = computed(() => (props.set.name ?? 'S').charAt(0));

// 공개 상태 — 배지는 정보 가치가 낮아 생략하고 배지 자리에 금액을 노출한다
const isPublished = computed(() => props.set.status === 'published');

// 라우트별 일시 — 오늘/내일/모레는 상대 라벨, 이후 날짜는 "9/2(수)" 유지
const routeDateLabel = (route) => relativeDateLabel(route.date, route.sortDate);
</script>

<template>
    <article class="set-card" :class="{ 'set-card--highlight': highlight }" role="button" tabindex="0" @click="open" @keydown.enter="open">
        <div class="set-card__head">
            <div class="set-card__header">
                <span class="set-card__avatar">{{ avatarText }}</span>
                <div class="set-card__title">
                    <strong>{{ set.name }}</strong>
                    <span class="set-card__count">{{ set.count }}개 일정</span>
                </div>
            </div>
            <div class="set-card__flags">
                <span v-if="set.isNew" class="set-card__new" title="새로 등록된 셋트">N</span>
                <span v-if="set.isUrgent" class="set-card__urgent" title="곧 운행 시작">임박</span>
                <!-- 공개 상태는 배지 대신 금액 -->
                <span v-if="isPublished" class="set-card__amount">
                    <BaseIcon name="coin" :size="15" />
                    {{ set.totalAmount }}
                </span>
                <span v-else class="status-badge" :class="`status-badge--${set.status}`" :style="{ background: statusColor, borderColor: statusColor }">
                    {{ set.statusLabel }}
                </span>
                <!-- 차량 — 한 줄 -->
                <span v-if="set.routes[0]?.vehicle" class="set-card__side-line">
                    <BaseIcon name="car" :size="12" />
                    {{ set.routes[0].vehicle }}
                </span>
                <!-- 인원·캐리어 — 같은 줄 (아이콘 + 숫자) -->
                <span v-if="set.routes[0]?.passengerCount || set.routes[0]?.luggageCount" class="set-card__side-line">
                    <BaseIcon v-if="set.routes[0]?.passengerCount" name="people" :size="12" />
                    <span v-if="set.routes[0]?.passengerCount">{{ set.routes[0].passengerCount }}</span>
                    <BaseIcon v-if="set.routes[0]?.luggageCount" name="bag" :size="12" />
                    <span v-if="set.routes[0]?.luggageCount">{{ set.routes[0].luggageCount }}</span>
                </span>
            </div>
        </div>

        <div class="set-card__routes">
            <div
                v-for="(route, index) in set.routes"
                :key="index"
                class="set-card__route-row"
            >
                <span class="set-card__route-time">{{ routeDateLabel(route) }} {{ route.time }}</span>
                <span v-if="route.flightNumber" class="set-card__route-flight" title="항공편">
                    <BaseIcon name="airplane" :size="11" />
                    {{ route.flightNumber }}
                </span>
                <span class="set-card__route-dot">{{ route.serviceLabel }}</span>
                <strong class="set-card__route-name">{{ route.route }}</strong>
            </div>
        </div>

        <div class="set-card__meta">
            <span>총 {{ set.passengerCount }}명</span>
            <span v-if="!isPublished" class="set-card__amount">{{ set.totalAmount }}</span>
        </div>
    </article>
</template>

<style scoped>
.set-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: var(--card-pad);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.set-card:hover,
.set-card:focus-visible {
    border-color: var(--brand);
    box-shadow: 0 4px 16px color-mix(in srgb, var(--brand) 12%, transparent);
    transform: translateY(-1px);
    outline: none;
}

/* 신규 셋트 하이라이트 — 슬라이드 인 + 브랜드 배경 깜빡임 */
.set-card--highlight {
    animation: set-highlight-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2), set-highlight-pulse 2.4s ease 0.4s infinite;
    border-color: color-mix(in srgb, var(--brand) 55%, transparent);
}

@keyframes set-highlight-in {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes set-highlight-pulse {
    0%,
    100% {
        background: var(--surface);
    }
    50% {
        background: color-mix(in srgb, var(--brand) 12%, transparent);
    }
}

.set-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.set-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.set-card__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}

.set-card__title {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.set-card__title strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
}

.set-card__count {
    color: var(--text-muted);
    font-size: 11px;
    margin-top: 2px;
}

.set-card__flags {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}

/* 신규 배지 */
.set-card__new {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--danger);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
    box-shadow: 0 2px 6px color-mix(in srgb, var(--danger) 30%, transparent);
}

/* 임박 배지 */
.set-card__urgent {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--danger);
    color: #ffffff;
    font-size: 10px;
    font-weight: 400;
    letter-spacing: -0.2px;
    box-shadow: 0 1px 4px rgba(229, 72, 77, 0.4);
    animation: urgent-pulse 1.6s ease-in-out infinite;
}

@keyframes urgent-pulse {
    0%,
    100% {
        box-shadow: 0 1px 4px rgba(229, 72, 77, 0.4);
    }
    50% {
        box-shadow: 0 1px 8px rgba(229, 72, 77, 0.8);
    }
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

.set-card__routes {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}

.set-card__route-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
}

.set-card__route-time {
    color: var(--text-muted);
    font-size: 11px;
    flex-shrink: 0;
    min-width: 88px;
}

/* 항공편 — 시간 옆 아이콘 + 텍스트 (알약 스타일 없이 텍스트로) */
.set-card__route-flight {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
}

.set-card__route-dot {
    color: var(--text-muted);
    font-size: 10px;
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.05);
}

html.dark .set-card__route-dot {
    background: rgba(255, 255, 255, 0.08);
}

.set-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 14px;
    color: var(--text-muted);
    font-size: 11px;
}

.set-card__route-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 0 1 auto;
}

.set-card__side-line {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    color: var(--text-muted);
    font-size: 11px;
    white-space: nowrap;
    opacity: 0.85;
}

.set-card__amount {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--brand);
    font-weight: 800;
    font-size: 13px;
    letter-spacing: -0.2px;
    white-space: nowrap;
}
</style>
