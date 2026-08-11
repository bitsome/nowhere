<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({
    set: {
        type: Object,
        required: true,
    },
    highlight: {
        type: Boolean,
        default: false,
    },
});

const router = useRouter();

// 셋트 카드 클릭 — 첫 번째 오더 상세로 이동
const open = () => {
    if (props.set.firstOrderId) {
        router.push({ name: 'order-detail', params: { id: props.set.firstOrderId } });
    }
};

// 상태별 배지 색상 (오더 카드와 동일 팔레트)
const STATUS_COLORS = {
    draft: '#909399',
    published: '#36adff',
    trading: '#ffa940',
    accepted: '#2f54eb',
    driving: '#13c2c2',
    completed: '#18a058',
    settled: '#722ed1',
    cancelled: '#e5484d',
    mixed: '#909399',
};

const statusColor = computed(() => STATUS_COLORS[props.set.status] ?? '#909399');

// 셋트명에서 "KLOOK 8월 셋트" 앞 글자
const avatarText = computed(() => (props.set.name ?? 'S').charAt(0));
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
                <span class="status-badge" :style="{ background: statusColor, borderColor: statusColor }">
                    {{ set.statusLabel }}
                </span>
                <span v-if="set.routes[0]?.vehicle || set.routes[0]?.passengerCount" class="set-card__side-line">
                    <span v-if="set.routes[0]?.vehicle" class="side-chip">
                        <svg class="side-chip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17a1 1 0 0 0 2 0M16 17a1 1 0 0 0 2 0M4 17V11l2.5-4h11L20 11v6h-1M4.5 11h15" /></svg>
                        {{ set.routes[0].vehicle }}
                    </span>
                    <span v-if="set.routes[0]?.passengerCount" class="side-chip">
                        <svg class="side-chip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5" /><circle cx="17" cy="9" r="2.5" /><path d="M15 15.5c2.3.2 4 1.3 4.8 3.7" /></svg>
                        {{ set.routes[0].passengerCount }}명
                    </span>
                </span>
            </div>
        </div>

        <div class="set-card__routes">
            <div
                v-for="(route, index) in set.routes"
                :key="index"
                class="set-card__route-row"
            >
                <span class="set-card__route-time">{{ route.date }} {{ route.time }}</span>
                <span class="set-card__route-dot">{{ route.serviceLabel }}</span>
                <strong class="set-card__route-name">{{ route.route }}</strong>
                <span v-if="index === 0 && set.isToday" class="set-card__today">오늘</span>
                <span v-else-if="index === 0 && set.isTomorrow" class="set-card__tomorrow">내일</span>
            </div>
        </div>

        <div class="set-card__meta">
            <span>총 {{ set.passengerCount }}명</span>
            <span class="set-card__amount">{{ set.totalAmount }}</span>
        </div>
    </article>
</template>

<style scoped>
.set-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.set-card:hover,
.set-card:focus-visible {
    border-color: #36adff;
    box-shadow: 0 4px 16px rgba(54, 173, 255, 0.12);
    transform: translateY(-1px);
    outline: none;
}

/* 신규 셋트 하이라이트 — 슬라이드 인 + 파랑 배경 깜빡임 */
.set-card--highlight {
    animation: set-highlight-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2), set-highlight-pulse 2.4s ease 0.4s infinite;
    border-color: rgba(54, 173, 255, 0.55);
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
        background: rgba(54, 173, 255, 0.12);
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
    background: linear-gradient(135deg, #36adff, #2f54eb);
    color: #ffffff;
    font-size: 15px;
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
    font-size: 15px;
}

.set-card__count {
    color: var(--text-muted);
    font-size: 12px;
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
    background: #e5484d;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(229, 72, 77, 0.3);
}

/* 임박 배지 */
.set-card__urgent {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    background: #e5484d;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
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
    padding: 4px 12px;
    border-radius: 999px;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.set-card__routes {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
}

.set-card__route-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}

.set-card__route-time {
    color: var(--text-muted);
    font-size: 13px;
    flex-shrink: 0;
    min-width: 88px;
}

.set-card__route-dot {
    color: var(--text-muted);
    font-size: 11px;
    flex-shrink: 0;
    padding: 1px 8px;
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
    font-size: 13px;
}

.set-card__route-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 0 1 auto;
}

.set-card__side-line {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    color: var(--text-muted);
    font-size: 12px;
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

.set-card__today,
.set-card__tomorrow {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.set-card__today {
    background: rgba(24, 160, 88, 0.12);
    color: #18a058;
}

.set-card__tomorrow {
    background: rgba(255, 169, 64, 0.14);
    color: #ffa940;
}

.set-card__amount {
    color: var(--text);
    font-weight: 700;
    font-size: 15px;
}
</style>
