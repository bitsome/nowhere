<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { apiToggleFavorite } from '../../api/orders';
import { statusColorVar } from '../../utils/colors';
import { relativeDateLabel } from '../../utils/dateText';
import { trackClick, trackImpression } from '../../utils/tracking';
import BaseIcon from '../common/BaseIcon.vue';
import ScoreStars from './ScoreStars.vue';

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
    // 찜(하트) 표시 — 마켓·찜한 운행 목록에서만 켠다 (단일 카드와 같은 규칙)
    favoriteable: {
        type: Boolean,
        default: false,
    },
    // 찜 여부 — 대표 다리(첫 다리)의 찜 상태 (백엔드가 함께 내려준다)
    favorited: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['favorite-change', 'tag-search']);

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

// 태그 검색 — 태그 칩을 누르면 마켓에서 그 태그로 운행을 검색한다 (단일 카드와 같은 규칙).
// 이미 마켓에 있으면 화면에 직접 반영하고, 다른 화면에서는 마켓으로 이동해 태그를 적용한다.
const goTagSearch = (tag) => {
    if (router.currentRoute.value.name === 'market') {
        emit('tag-search', tag);

        return;
    }

    try {
        localStorage.setItem('nowhere:market:pendingTag', tag);
    } catch {
        /* 저장 실패 무시 — 마켓은 기존 필터로 열린다 */
    }

    router.push({ name: 'market' });
};

// ── 찜(하트) — 셋트는 운행 단위 찜과 1:1이 아니므로 대표 다리(첫 다리)를 찜한다 ──
const fav = ref(props.favorited);
const favBusy = ref(false);

// 목록이 새로고침되면 서버 상태(행의 is_favorited)로 초기화한다
watch(
    () => props.favorited,
    (value) => {
        fav.value = value;
    },
);

const toggleFavorite = async () => {
    if (favBusy.value || !props.set.firstOrderId) {
        return;
    }

    favBusy.value = true;

    try {
        const { data } = await apiToggleFavorite(props.set.firstOrderId);
        const next = Boolean(data?.data?.favorited);

        fav.value = next;
        // 목록 갱신은 행 단위 — 셋트 행의 id(묶음 id)를 그대로 올려 부모가 이 행을 찾게 한다
        emit('favorite-change', props.set.id, next);
    } catch {
        // 마켓에서 빠진 운행 등은 조용히 무시 — 다음 목록 갱신에서 정리된다
    } finally {
        favBusy.value = false;
    }
};

// 상태별 배지 색상 — 중앙 팔레트에서 참조 (mixed=회색)
// 예약(가져오기 수락) 배지는 오렌지색 — 테마 대응은 거래중 색상 재사용
const statusColor = computed(() => {
    if (props.set.status === 'accepted') return 'var(--status-trading)';

    return statusColorVar[props.set.status] ?? 'var(--status-draft)';
});

// 공개 상태 — 배지는 정보 가치가 낮아 생략하고 배지 자리에 금액을 노출한다
const isPublished = computed(() => props.set.status === 'published');

// 라우트별 일시 — 오늘/내일/모레는 상대 라벨, 이후 날짜는 "9/2(수)" 유지
const routeDateLabel = (route) => relativeDateLabel(route.date, route.sortDate);

// 헤더 요약에 쓰는 대표 다리(첫 운행) — 시간·항공편·차량을 여기서 읽는다
const firstRoute = computed(() => props.set.routes?.[0] ?? null);

// 일정 목록은 두 줄까지만 — 나머지는 마지막 도착지 뒤 '+N'으로 접는다 (3개 이상일 때만 보인다)
const visibleRoutes = computed(() => (props.set.routes ?? []).slice(0, 2));

const hiddenRouteCount = computed(() => Math.max(0, (props.set.routes?.length ?? 0) - visibleRoutes.value.length));

// ── 운행 조건 점수 — 대표 다리(첫 다리) 기준. 별 5개로 요약하고 클릭하면 근거 모달 ──
const scoreOpen = ref(false);

const matchScore = computed(() => {
    const score = Number(props.set.match_score ?? props.set.matchScore ?? null);

    return Number.isFinite(score) ? score : null;
});

const matchReasons = computed(() => props.set.match_reasons ?? props.set.matchReasons ?? []);

// 100점 만점을 5점 만점으로 환산 (예: 70 → 3.5 → 별 3개 + 반별 1개)
const ratingValue = computed(() => {
    if (matchScore.value === null) {
        return null;
    }

    return Math.min(5, Math.max(0, matchScore.value / 20));
});

// 조건 버튼 노출 — 추천 근거/점수가 있는 카드(마켓·홈 추천)에서만
const condOpenable = computed(() => matchScore.value !== null || matchReasons.value.length > 0);
</script>

<template>
    <article class="set-card" :class="{ 'set-card--highlight': highlight }" role="button" tabindex="0" @click="open" @keydown.enter="open">
        <div class="set-card__head">
            <div class="set-card__header">
                <div class="set-card__title">
                    <!-- 앱에서 지은 이름은 노선 위에 작게 — 대표 노선(첫 다리)을 단일 카드처럼 크게 보여준다 -->
                    <span v-if="!set.isNameGenerated" class="set-card__name">{{ set.name }}</span>
                    <div class="set-card__route-top">
                        <!-- 배지는 모두 출발지 앞에 모아 둔다 — 상태 → 긴급 → 임박 → 신규 순 -->
                        <span v-if="!isPublished" class="status-badge" :class="`status-badge--${set.status}`" :style="{ background: statusColor, borderColor: statusColor }">
                            {{ set.statusLabel }}
                        </span>
                        <span v-if="set.isPriority" class="set-card__priority" title="긴급 운행">긴급</span>
                        <span v-if="set.isUrgent" class="set-card__urgent" title="곧 운행 시작">임박</span>
                        <span v-if="set.isNew" class="set-card__new" title="새로 등록된 셋트">N</span>
                        <strong class="set-card__route-main">{{ firstRoute?.route }}</strong>
                    </div>
                </div>
            </div>
            <div class="set-card__flags">
                <!-- 배지·별점·차량은 아래로 내렸다 — 우측 상단은 금액 자리 -->
                <span class="set-card__amount">
                    <BaseIcon name="coin" :size="15" />
                    {{ set.totalAmount }}
                </span>
            </div>
        </div>

        <!-- 일정 목록 — 두 줄까지만 보여주고, 더 있으면 마지막 도착지 뒤에 +N -->
        <div class="set-card__routes">
            <div
                v-for="(route, index) in visibleRoutes"
                :key="index"
                class="set-card__route-row"
            >
                <span class="set-card__route-time">{{ routeDateLabel(route) }} {{ route.time }}</span>
                <span v-if="route.flightNumber" class="set-card__route-flight" title="항공편">
                    <BaseIcon name="airplane" :size="11" />
                    {{ route.flightNumber }}
                </span>
                <strong class="set-card__route-name">{{ route.route }}</strong>
                <!-- 다리별 거리 — 단일 카드와 같은 기준(좌표를 아는 지명끼리만) -->
                <span v-if="route.distanceKm" class="set-card__route-distance" :title="`${route.route} 약 ${route.distanceKm}km`">
                    약 {{ route.distanceKm }}km
                </span>
                <!-- 도착지 뒤 — 더 남은 일정 수 (3개 이상일 때만) -->
                <span
                    v-if="hiddenRouteCount > 0 && index === visibleRoutes.length - 1"
                    class="set-card__route-more"
                    :title="`일정 ${hiddenRouteCount}개 더 있음`"
                >+{{ hiddenRouteCount }}</span>
                <!-- 다리별 인원 — 줄 오른쪽 끝 -->
                <span v-if="route.passengerCount" class="set-card__route-pax" title="인원">
                    <BaseIcon name="people" :size="11" />
                    <span>{{ route.passengerCount }}</span>
                </span>
            </div>
        </div>

        <!-- 조건 점수(별)·차량 — 일정 목록 아래 한 줄 (별점 왼쪽 / 차량 오른쪽) -->
        <div class="set-card__summary">
            <button
                v-if="condOpenable"
                type="button"
                class="set-card__cond"
                aria-label="운행 조건 보기"
                title="조건 0~5점 · 클릭해 근거 보기"
                @click.stop="scoreOpen = true"
            >
                <ScoreStars v-if="matchScore !== null" :value="ratingValue" :size="14" />
                <span v-else class="set-card__cond-hint">조건 보기</span>
            </button>
            <!-- 차량 — 첫 다리 기준. 차종 미입력이면 자리를 비우지 않고 - 로 둔다 -->
            <span class="set-card__side-line">
                <BaseIcon name="car" :size="12" />
                {{ firstRoute?.vehicle || '-' }}
            </span>
        </div>

        <!-- 태그·찜 바 — 점선 구분 아래 한 줄. 왼쪽 태그 칩 / 오른쪽 하트 (단일 카드와 같은 구성) -->
        <div v-if="set.tags?.length || favoriteable" class="set-card__favbar">
            <div v-if="set.tags?.length" class="set-card__tags">
                <button
                    v-for="tag in set.tags"
                    :key="tag"
                    type="button"
                    class="set-card__tag"
                    :title="`#${tag} 운행 검색`"
                    @click.stop.prevent="goTagSearch(tag)"
                >
                    #{{ tag }}
                </button>
            </div>
            <div class="set-card__actions">
                <button
                    v-if="favoriteable"
                    type="button"
                    class="set-card__fav"
                    :class="{ 'set-card__fav--on': fav }"
                    :aria-label="fav ? '찜 해제' : '찜하기'"
                    :title="fav ? '찜 해제' : '찜하기'"
                    @click.stop.prevent="toggleFavorite"
                >
                    <BaseIcon :name="fav ? 'heart-filled' : 'heart'" :size="17" />
                </button>
            </div>
        </div>
    </article>

    <!-- 운행 조건 상세 모달 — 별(점수)을 누르면 추천 근거 체크리스트가 열린다 -->
    <n-modal v-model:show="scoreOpen" preset="card" title="운행 조건" :style="{ maxWidth: '340px' }">
        <div class="cond-modal">
            <ScoreStars v-if="ratingValue !== null" :value="ratingValue" :size="24" />
            <div v-if="matchReasons.length" class="cond-modal__reasons">
                <span v-for="reason in matchReasons" :key="reason" class="cond-modal__reason">
                    <BaseIcon class="cond-modal__check" name="check" :size="12" />
                    {{ reason }}
                </span>
            </div>
            <p v-else class="cond-modal__empty">아직 조건 점수에 따른 상세 근거가 없습니다.</p>
        </div>
    </n-modal>
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

.set-card__title {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

/* 앱에서 지은 이름 — 노선 위에 작게 */
.set-card__name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-muted);
    font-size: 11px;
}

/* 대표 노선(첫 다리) — 단일 카드 노선과 같은 크기·굵기. 긴급 배지가 앞에 붙는다 */
.set-card__route-top {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.set-card__route-main {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

/* 조건 점수(별)·차량 줄 — 일정 목록 아래 (별점 왼쪽 / 차량 오른쪽) */
.set-card__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 9px;
    min-height: 16px;
}

/* 조건 별점 버튼 — 별 0~5점 요약. 클릭하면 근거 체크리스트 모달 */
.set-card__cond {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 16px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text);
    font-family: inherit;
    line-height: 1;
    cursor: pointer;
    transition: opacity 0.15s ease;
}

.set-card__cond:hover {
    opacity: 0.75;
}

.set-card__cond-hint {
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 500;
}

/* 긴급 배지 — 단일 카드(order-card__priority)와 같은 모양 */
.set-card__priority {
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

/* 모션 감소 설정 — 임박 배지·신규 하이라이트의 반복 깜빡임 정지 (base.css 스켈레톤과 동일 규칙) */
@media (prefers-reduced-motion: reduce) {
    .set-card--highlight,
    .set-card__urgent {
        animation: none;
    }
}

/* 임박 배지 — 다크 모드에서 danger가 밝은 빨강으로 톤 다운되어 흰 글자 대비가 약해지므로 어두운 글자로 전환 */
html.dark .set-card__urgent {
    color: #101418;
}

/* 상태 배지 스타일은 base.css 공용(.status-badge) — OrderCard 와 같은 배지를 쓴다 */

/* 일정 목록 — 헤더(노선·배지·금액·차량·인원) 바로 아래. 카드에 구분선은 두지 않는다 */
.set-card__routes {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
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

/* 다리별 노선 — 시간·편명 뒤에 붙고, 인원은 줄 오른쪽 끝으로 밀린다 */
.set-card__route-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 0 1 auto;
}

/* 더 남은 일정 수 — 마지막 도착지 뒤에 붙는 +N */
.set-card__route-more {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}

/* 다리별 거리 — 노선 뒤 보조 정보 */
.set-card__route-distance {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 500;
}

/* 다리별 인원 — 오른쪽 끝 정렬 */
.set-card__route-pax {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: auto;
    color: var(--text-muted);
    font-size: 11px;
    flex-shrink: 0;
}

.set-card__side-line {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-left: auto;
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

/* 태그·찜 바 — 점선 구분 한 줄. 왼쪽 태그 칩 / 오른쪽 하트 (단일 카드와 같은 구성) */
.set-card__favbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
    padding-top: 11px;
    border-top: 1px dashed var(--border);
}

/* 어필 태그 — 한 줄(찜 바)의 왼쪽 칩 */
.set-card__tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    flex: 1 1 auto;
    min-width: 0;
    line-height: 1;
}

.set-card__tag {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 400;
    line-height: 1;
    white-space: nowrap;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.set-card__tag:hover,
.set-card__tag:focus-visible {
    color: var(--brand);
    border-color: color-mix(in srgb, var(--brand) 55%, transparent);
    background: color-mix(in srgb, var(--brand) 6%, transparent);
    outline: none;
}

/* 카드 우측 액션 묶음 — 찜을 카드 클릭 영역과 분리해 한 덩어리로 둔다.
   태그가 없어도 하트는 항상 오른쪽 끝 (margin-left: auto) */
.set-card__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: auto;
    flex-shrink: 0;
}

/* 찜 하트 — 테두리 없는 심플 아이콘. 터치 영역은 24×24(WCAG 2.5.8)로 둔다 */
.set-card__fav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    line-height: 1;
    transition: color 0.15s ease, transform 0.1s ease;
    flex-shrink: 0;
}

.set-card__fav:hover {
    color: var(--danger);
}

.set-card__fav:active {
    transform: scale(0.88);
}

.set-card__fav--on {
    color: var(--danger);
}

/* 조건 상세 모달 — 별점 + 추천 근거 체크리스트 스타일은 base.css 공용(.cond-modal__*) */
</style>
