<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

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

const open = () => router.push({ name: 'order-detail', params: { id: props.order.id } });

// 선택 모드에서는 카드 클릭 대신 체크만 토글한다
const handleClick = () => {
    if (props.selectable) {
        emit('toggle', props.order.id);

        return;
    }

    open();
};

// 상태별 배지 색상 (식별하기 쉽도록)
const STATUS_COLORS = {
    draft: '#909399',
    published: '#36adff',
    trading: '#ffa940',
    accepted: '#2f54eb',
    driving: '#13c2c2',
    completed: '#18a058',
    settled: '#722ed1',
    cancelled: '#e5484d',
};

const statusColor = computed(() => STATUS_COLORS[props.order.status] ?? '#909399');
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
                    <span v-if="order.isNew" class="order-card__new" title="새로 등록된 오더">N</span>
                    <strong>{{ order.route }}</strong>
                    <span v-if="order.isToday" class="order-card__today">오늘</span>
                    <span v-else-if="order.isTomorrow" class="order-card__tomorrow">내일</span>
                </div>
                <div class="order-card__route-bottom">
                    <span class="order-card__datetime">{{ order.date }} {{ order.time }}</span>
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
                <span v-else class="status-badge" :style="{ background: statusColor, borderColor: statusColor }">
                    {{ order.statusLabel }}
                </span>
                <span v-if="order.vehicle || order.passengerCount" class="order-card__side-line">
                    <span v-if="order.vehicle" class="side-chip">
                        <svg class="side-chip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17a1 1 0 0 0 2 0M16 17a1 1 0 0 0 2 0M4 17V11l2.5-4h11L20 11v6h-1M4.5 11h15" /></svg>
                        {{ order.vehicle }}
                    </span>
                    <span v-if="order.passengerCount" class="side-chip">
                        <svg class="side-chip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5" /><circle cx="17" cy="9" r="2.5" /><path d="M15 15.5c2.3.2 4 1.3 4.8 3.7" /></svg>
                        {{ order.passengerCount }}명
                    </span>
                </span>
            </div>
        </div>
        <div class="order-card__meta">
            <span>{{ order.serviceLabel }}</span>
            <span v-if="order.flightNumber">✈ {{ order.flightNumber }}</span>
            <span class="order-card__amount">{{ order.amount }}</span>
        </div>
        <div
            v-if="order.owner && (order.owner.review_count > 0 || order.owner.completed_count > 0)"
            class="order-card__owner"
        >
            <span class="order-card__owner-name">{{ order.owner.name }}</span>
            <span v-if="order.owner.review_count > 0" class="order-card__owner-trust">⭐ {{ order.owner.rating }} · 리뷰 {{ order.owner.review_count }}</span>
            <span v-if="order.owner.completed_count > 0" class="order-card__owner-trust">완료 {{ order.owner.completed_count }}건</span>
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
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.order-card:hover,
.order-card:focus-visible {
    border-color: #36adff;
    box-shadow: 0 4px 16px rgba(54, 173, 255, 0.12);
    transform: translateY(-1px);
    outline: none;
}

.order-card--selectable {
    cursor: pointer;
}

.order-card--selected {
    border-color: #36adff;
    background: rgba(54, 173, 255, 0.04);
}

/* 신규 오더 하이라이트 — 슬라이드 인 + 파랑 배경 깜빡임 */
.order-card--highlight {
    animation: card-highlight-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2), card-highlight-pulse 2.4s ease 0.4s infinite;
    border-color: rgba(54, 173, 255, 0.55);
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
        background: rgba(54, 173, 255, 0.12);
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
    font-size: 15px;
}

.order-card__route-bottom {
    display: flex;
    align-items: center;
    margin-top: 4px;
}

.order-card__datetime {
    color: var(--text-muted);
    font-size: 13px;
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

/* 신규 오더 배지 */
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
    background: #18a058;
    color: #ffffff;
}

.order-card__tomorrow {
    background: #ffa940;
    color: #ffffff;
}

.order-card__amount {
    margin-left: auto;
    color: var(--text);
    font-weight: 700;
    font-size: 15px;
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
</style>
