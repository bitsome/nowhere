<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { statusColorVar } from '../../utils/colors';
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
});

const emit = defineEmits(['toggle']);

const router = useRouter();
const auth = useAuthStore();

const open = () => router.push({ name: 'order-detail', params: { id: props.order.id } });

// 선택 모드에서는 카드 클릭 대신 체크만 토글한다
const handleClick = () => {
    if (props.selectable) {
        emit('toggle', props.order.id);

        return;
    }

    open();
};

// 상태별 배지 색상 — 중앙 팔레트(utils/colors.js)에서 참조 (테마 자동 적용)
const statusColor = computed(() => statusColorVar[props.order.status] ?? 'var(--status-draft)');

// 가져오기 요청(수락 대기) — 요청자가 나인지 여부
const isMyClaim = computed(() =>
    props.order.status === 'acceptance_pending' && props.order.claimantUserId === auth.user?.id,
);
</script>

<template>
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
                    <span v-if="order.isNew" class="order-card__new" title="새로 등록된 운행">N</span>
                    <span v-if="order.is_matched_to_me" class="order-card__matched" title="내 매칭 조건에 맞는 운행">매칭</span>
                    <span v-if="order.pendingOffers > 0" class="order-card__offer" title="대기 중인 요금 제안">제안 {{ order.pendingOffers }}</span>
                    <span v-if="order.isPriority" class="order-card__priority" title="긴급 운행">긴급</span>
                    <strong>{{ order.route }}</strong>
                    <span v-if="order.isUrgent" class="order-card__urgent" title="곧 운행 시작">임박</span>
                    <span v-else-if="order.isToday" class="order-card__today">오늘</span>
                    <span v-else-if="order.isTomorrow" class="order-card__tomorrow">내일</span>
                </div>
                <div class="order-card__route-bottom">
                    <span class="order-card__datetime">
                        <BaseIcon class="order-card__datetime-icon" name="time" :size="13" />
                        {{ order.date }} {{ order.time }}
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
                <span v-else class="status-badge" :class="`status-badge--${order.status}`" :style="{ background: statusColor, borderColor: statusColor }">
                    {{ order.statusLabel }}
                </span>
                <span v-if="order.vehicle || order.passengerCount" class="order-card__side-line">
                    <span v-if="order.vehicle" class="side-chip">
                        <BaseIcon class="side-chip__icon" name="car" :size="12" />
                        {{ order.vehicle }}
                    </span>
                    <span v-if="order.passengerCount" class="side-chip">
                        <BaseIcon class="side-chip__icon" name="people" :size="12" />
                        {{ order.passengerCount }}명
                    </span>
                </span>
            </div>
        </div>
        <div class="order-card__meta">
            <span v-if="order.serviceLabel" class="order-card__meta-item">{{ order.serviceLabel }}</span>
            <span v-if="order.flightNumber" class="order-card__meta-item">{{ order.flightNumber }}</span>
            <span class="order-card__amount">{{ order.amount }}</span>
        </div>
        <div
            v-if="order.owner && (order.owner.review_count > 0 || order.owner.completed_count > 0)"
            class="order-card__owner"
        >
            <span class="order-card__owner-name">{{ order.owner.name }}</span>
            <span v-if="order.owner.review_count > 0" class="order-card__owner-trust">{{ order.owner.rating }}점 · 리뷰 {{ order.owner.review_count }}</span>
            <span v-if="order.owner.completed_count > 0" class="order-card__owner-trust">완료 {{ order.owner.completed_count }}건</span>
        </div>

        <!-- 가져오기 요청(수락 대기) 상태 — 등록자는 요청자, 요청자는 승인 안내 -->
        <div v-if="order.status === 'acceptance_pending'" class="order-card__claim">
            <span class="order-card__claim-dot" />
            <span v-if="isMyClaim">등록자 승인이 필요합니다</span>
            <span v-else>{{ order.claimantName }}님이 가져오기를 요청</span>
        </div>
    </article>
</template>

<style scoped>
.order-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
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
    font-size: 16px;
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
    font-size: 13px;
    font-weight: 500;
}

.order-card__datetime-icon {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
    opacity: 0.8;
}

.order-card__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}

.order-card__side-line {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    color: var(--text-muted);
    font-size: 13px;
    white-space: nowrap;
}

.side-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.side-chip__icon {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
}

.order-card__meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 13px;
}

/* 등록자 신뢰 정보 — 마켓 카드에 표시 */
.order-card__owner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 10px;
    margin-top: 8px;
    font-size: 12px;
}

.order-card__owner-name {
    font-weight: 700;
    color: var(--text);
}

.order-card__owner-trust {
    color: var(--text-muted);
}

/* 가져오기 요청(수락 대기) 힌트 바 */
.order-card__claim {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 12px;
    padding: 9px 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--status-acceptance-pending) 12%, transparent);
    color: var(--text);
    font-size: 13px;
    font-weight: 600;
}

.order-card__claim-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--status-acceptance-pending);
    flex-shrink: 0;
}

/* 신규 운행 배지 */
.order-card__new {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e5484d;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(229, 72, 77, 0.3);
}

/* 오늘/내일 배지 — 도착지(route) 뒤에 표시 */
.order-card__today,
.order-card__tomorrow {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: -0.2px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.order-card__today {
    background: var(--status-completed);
    color: #ffffff;
}

.order-card__urgent {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
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

.order-card__tomorrow {
    background: #ffa940;
    color: #ffffff;
}

/* 긴급 배지 — 임박(빨강 펄스)과 구분되는 보라 */
.order-card__priority {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: -0.2px;
    background: var(--status-settled);
    color: #ffffff;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--status-settled) 40%, transparent);
}

/* 매칭 배지 — 내 매칭 설정 조건에 맞는 운행 */
.order-card__matched {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: -0.2px;
    background: var(--brand);
    color: #ffffff;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--brand) 40%, transparent);
}

/* 요금 제안 배지 — 대기 중인 제안이 있는 운행 */
.order-card__offer {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: -0.2px;
    background: var(--status-trading);
    color: #ffffff;
    box-shadow: 0 1px 4px color-mix(in srgb, var(--status-trading) 45%, transparent);
    animation: offer-pulse 2s ease-in-out infinite;
}

@keyframes offer-pulse {
    0%,
    100% {
        box-shadow: 0 1px 4px color-mix(in srgb, var(--status-trading) 45%, transparent);
    }
    50% {
        box-shadow: 0 1px 9px color-mix(in srgb, var(--status-trading) 85%, transparent);
    }
}

.order-card__amount {
    margin-left: auto;
    color: var(--brand);
    font-weight: 800;
    font-size: 16.5px;
    letter-spacing: -0.3px;
    white-space: nowrap;
}

/* 상태 배지 */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 999px;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

/* 다크모드 — 밝은 틸(#63e2b7) 배지 위엔 검은 글자 (흰 글자는 대비가 약함) */
html.dark .status-badge--published,
html.dark .status-badge--driving,
html.dark .order-card__matched {
    color: #101418;
}
</style>
