<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { apiToggleFavorite } from '../../api/orders';
import { statusColorVar } from '../../utils/colors';
import { relativeDateLabel } from '../../utils/dateText';
import { trackClick, trackImpression } from '../../utils/tracking';
import BaseIcon from '../common/BaseIcon.vue';
import ScoreStars from './ScoreStars.vue';

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
    // 찜(하트) 표시 — 마켓·찜한 운행 목록에서만 켠다
    favoriteable: {
        type: Boolean,
        default: false,
    },
    // 찜 여부 — 목록 행의 is_favorited 값 (백엔드가 함께 내려준다)
    favorited: {
        type: Boolean,
        default: false,
    },
    // 공유(공개 링크) — 등록자 목록에서만 켠다. 공개 상태 카드에만 노출된다
    shareable: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle', 'favorite-change', 'tag-search', 'share']);

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

// 태그 검색 — 태그 칩을 누르면 마켓에서 그 태그로 운행을 검색한다.
// 이미 마켓에 있으면 화면에 직접 반영하고, 다른 화면에서는 마켓으로 이동해 태그를 적용한다.
const goTagSearch = (tag) => {
    if (props.selectable) {
        return;
    }

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

// ── 찜(즐겨찾기) — 하트를 눌러 마켓 운행을 보관/해제한다 ──
const fav = ref(props.favorited);
const favBusy = ref(false);

// 목록이 새로고침되면 서버 상태(행의 is_favorited)로 초기화한다
watch(
    () => props.favorited,
    (value) => {
        fav.value = value;
    },
);

// ── 운행 조건 점수 — 카드 아래 점선 바에서 별 5개(0~5점)로 요약하고, 클릭하면 근거 모달 ──
const scoreOpen = ref(false);

const matchScore = computed(() => {
    const score = Number(props.order.match_score ?? props.order.matchScore ?? null);

    return Number.isFinite(score) ? score : null;
});

// 100점 만점을 5점 만점으로 환산 (예: 70 → 3.5 → 별 3개 + 반별 1개)
const ratingValue = computed(() => {
    if (matchScore.value === null) {
        return null;
    }

    return Math.min(5, Math.max(0, matchScore.value / 20));
});

// 조건 버튼 노출 — 추천 근거/점수가 있는 카드(마켓·홈 추천)에서만
const condOpenable = computed(() => props.showMatchReasons && (matchScore.value !== null || matchReasons.value.length > 0));

const toggleFavorite = async () => {
    if (favBusy.value || props.selectable) {
        return;
    }

    favBusy.value = true;

    try {
        const { data } = await apiToggleFavorite(props.order.id);
        const next = Boolean(data?.data?.favorited);

        fav.value = next;
        emit('favorite-change', props.order.id, next);
    } catch {
        // 마켓에서 빠진 운행 등은 조용히 무시 — 다음 목록 갱신에서 정리된다
    } finally {
        favBusy.value = false;
    }
};

// 상태별 배지 색상 — 중앙 팔레트(utils/colors.js)에서 참조 (테마 자동 적용)
// 재정의(statusOverride)가 있으면 그 라벨·색상을 우선 사용한다 (휴지통 '요청취소' 등)
const statusText = computed(() => {
    if (props.statusOverride?.label) {
        return props.statusOverride.label;
    }

    // 승인 대기 상태 — 요청을 보낸 기사(claimant)와 등록자에게만 '수락 대기'로 보여준다.
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

// 공유 버튼 — 내가 등록해 공개 중인 운행만. 상세(2탭) 대신 목록에서 바로 링크를 만든다.
// 남에게 넘긴 운행(original_owner_id)은 공유 API 권한이 없으므로 소유자 id까지 확인한다.
const showShare = computed(
    () => props.shareable
        && !props.selectable
        && isPublished.value
        && props.order.userId === auth.user?.id,
);

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

const startRemainIcon = computed(() => START_REMAIN_ICONS[startRemain.value?.level] ?? 'history');
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
                    <!-- 배지는 모두 출발지 앞에 모아 둔다 — 상태 → 긴급 → 임박 순 -->
                    <span v-if="!isPublished" class="status-badge" :class="statusBadgeClass" :style="{ background: statusColor, borderColor: statusColor }">
                        {{ statusText }}
                    </span>
                    <span v-if="order.isPriority" class="order-card__priority" title="긴급 운행">긴급</span>
                    <span v-if="order.isUrgent" class="order-card__urgent" title="곧 운행 시작">임박</span>
                    <strong>{{ order.route }}</strong>
                </div>
                <div class="order-card__route-bottom">
                    <span class="order-card__datetime">
                        {{ dateLabel }} {{ order.time }}
                    </span>
                    <span v-if="order.flightNumber" class="order-card__flight" title="항공편">
                        <BaseIcon name="airplane" :size="12" />
                        {{ order.flightNumber }}
                    </span>
                    <!-- 출발지 → 도착지 대략 거리 — 좌표를 아는 지명끼리만 표시된다 -->
                    <span v-if="order.distanceKm" class="order-card__distance" :title="`${order.route} 약 ${order.distanceKm}km`">
                        약 {{ order.distanceKm }}km
                    </span>
                </div>
                <!-- 운행 조건 점수 별 — 항공편(시간) 줄 아래. 클릭하면 근거 모달 -->
                <div v-if="condOpenable" class="order-card__route-score">
                    <button
                        type="button"
                        class="order-card__cond"
                        aria-label="운행 조건 보기"
                        :title="'조건 0~5점 · 클릭해 근거 보기'"
                        @click.stop="scoreOpen = true"
                    >
                        <ScoreStars v-if="matchScore !== null" :value="ratingValue" :size="14" />
                        <span v-else class="order-card__cond-hint">조건 보기</span>
                    </button>
                </div>
            </div>
            <div class="order-card__side">
                <div class="order-card__side-top">
                    <n-checkbox
                        v-if="selectable"
                        :checked="selected"
                        class="order-card__check"
                        @click.stop
                        @update:checked="emit('toggle', order.id)"
                    />
                    <!-- 상태 배지는 출발지 앞으로 옮겼다 — 우측 상단은 금액 자리 -->
                    <span v-else class="order-card__amount">
                        <BaseIcon class="order-card__coin-icon" name="coin" :size="16" />
                        {{ order.amount }}
                    </span>
                    <span v-if="statusExtra" class="order-card__status-extra">{{ statusExtra }}</span>
                </div>
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
        <!-- 태그·찜 바 — 한 줄: 왼쪽 태그 칩 / 오른쪽 공유·하트 -->
        <div v-if="(favoriteable && !selectable) || order.tags?.length || showShare" class="order-card__favbar">
            <!-- 어필 태그 — 누르면 해당 태그로 마켓 검색 -->
            <div v-if="order.tags?.length" class="order-card__route-tags">
                <button
                    v-for="tag in order.tags"
                    :key="tag"
                    type="button"
                    class="order-card__route-tag"
                    :title="`#${tag} 운행 검색`"
                    @click.stop.prevent="goTagSearch(tag)"
                >
                    #{{ tag }}
                </button>
            </div>
            <!-- 공유·찜 — 오른쪽에 모아 카드 클릭(상세 이동)과 구분한다 -->
            <div class="order-card__actions">
                <!-- 공유 — 상세로 들어가지 않고 목록에서 바로 공개 링크를 만든다 -->
                <button
                    v-if="showShare"
                    type="button"
                    class="order-card__share"
                    aria-label="공유"
                    title="공유 링크 만들기"
                    @click.stop.prevent="emit('share', order.id)"
                >
                    <BaseIcon name="share" :size="16" />
                    공유
                </button>
                <!-- 찜 — 마켓에서 나중에 다시 볼 운행을 보관한다 -->
                <button
                    v-if="favoriteable && !selectable"
                    type="button"
                    class="order-card__fav"
                    :class="{ 'order-card__fav--on': fav }"
                    :aria-label="fav ? '찜 해제' : '찜하기'"
                    :title="fav ? '찜 해제' : '찜하기'"
                    @click.stop.prevent="toggleFavorite"
                >
                    <BaseIcon :name="fav ? 'heart-filled' : 'heart'" :size="17" />
                </button>
            </div>
        </div>
        <!-- 금액 행 — 선택 모드에서만 (체크박스가 우측 상단 금액 자리를 쓴다) -->
        <div v-if="selectable" class="order-card__meta">
            <span class="order-card__amount">
                <BaseIcon class="order-card__coin-icon" name="coin" :size="17" />
                {{ order.amount }}
            </span>
        </div>
    </article>
    </div>

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

/* 찜 하트 — 테두리 없는 심플 아이콘. 태그 칩과 같은 위선(높이 18px)에 맞춘다 */
.order-card__fav {
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
.order-card__fav:hover {
    color: var(--danger);
}
.order-card__fav:active {
    transform: scale(0.88);
}
.order-card__fav--on {
    color: var(--danger);
}

/* 카드 우측 액션 묶음 — 공유·찜을 카드 클릭 영역과 분리해 한 덩어리로 둔다.
   태그가 없어도 하트는 항상 오른쪽 끝 (margin-left: auto) */
.order-card__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: auto;
    flex-shrink: 0;
}

/* 공유 — 태그 칩과 같은 높이(18px)에 맞춘 텍스트형 버튼. 카드 클릭(상세 이동)과 분리한다 */
.order-card__share {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 18px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-muted);
    font-family: inherit;
    font-size: 10px;
    font-weight: 400;
    line-height: 1;
    cursor: pointer;
    transition: color 0.15s ease;
    flex-shrink: 0;
    white-space: nowrap;
}
.order-card__share:hover {
    color: var(--brand);
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

/* 거리 — 시간·편명 줄 끝에 붙는 보조 정보 */
.order-card__distance {
    display: inline-flex;
    align-items: center;
    margin-left: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
}

.order-card__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}

/* 금액·배지 오른쪽 정렬 — 상단 우측 열의 첫 줄을 한 줄로 묶는다 */
.order-card__side-top {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    max-width: 100%;
    flex-shrink: 0;
}
.order-card__side-top .order-card__status-extra {
    margin-left: 2px;
}

/* 상태 배지 옆 추가 텍스트 — 요청보냄 남은 시간 등 */
.order-card__status-extra {
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
    /* 요청보냄 남은 초가 매초 바뀌어도 숫자 폭이 고정되도록 — 배지 옆 흔들림 방지 */
    font-variant-numeric: tabular-nums;
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

/* 어필 태그 — 한 줄(찜 바)의 왼쪽 칩. 카드 좌측 라인에 맞춘다 */
.order-card__route-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    flex: 1 1 auto;
    min-width: 0;
    padding-left: 0;
    line-height: 1;
}
.order-card__route-tag:first-child {
    margin-left: 0;
}
.order-card__route-tag {
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
.order-card__route-tag:hover,
.order-card__route-tag:focus-visible {
    color: var(--brand);
    border-color: color-mix(in srgb, var(--brand) 55%, transparent);
    background: color-mix(in srgb, var(--brand) 6%, transparent);
    outline: none;
}

/* 운행 조건 점수 별 줄 — 항공편(시간) 줄 아래. 살짝 숨 쉬는 간격 */
.order-card__route-score {
    display: flex;
    align-items: center;
    margin-top: 3px;
    padding-left: 0;
    min-height: 16px;
}
.order-card__route-score .order-card__cond {
    height: 16px;
}

/* 태그·찜 바 — 점선 구분 한 줄. 왼쪽 태그 칩 / 오른쪽 하트 */
.order-card__favbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
    padding-top: 11px;
    border-top: 1px dashed var(--border);
}

/* 조건 별점 버튼 — 별 0~5점 요약. 클릭하면 근거 체크리스트 모달 */
.order-card__cond {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 20px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text);
    font-family: inherit;
    line-height: 1;
    cursor: pointer;
    transition: opacity 0.15s ease;
}
.order-card__cond:hover {
    opacity: 0.75;
}
.order-card__cond-hint {
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 500;
}

/* 조건 상세 모달 — 별점 + 추천 근거 체크리스트 */
.cond-modal__reasons {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--border);
}
.cond-modal__reason {
    display: inline-flex;
    align-items: flex-start;
    gap: 6px;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 500;
    line-height: 1.45;
}
.cond-modal__check {
    flex-shrink: 0;
    color: var(--brand);
    margin-top: 1px;
}
.cond-modal__empty {
    margin: 12px 0 0;
    color: var(--text-muted);
    font-size: 11px;
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
    /* 시작까지 남은 시간이 갱신될 때 카드 좌측 라벨 폭이 흔들리지 않도록 */
    font-variant-numeric: tabular-nums;
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

/* 모션 감소 설정 — 임박 배지·신규 하이라이트의 반복 깜빡임 정지 (base.css 스켈레톤과 동일 규칙) */
@media (prefers-reduced-motion: reduce) {
    .order-card--highlight,
    .order-card__urgent {
        animation: none;
    }
}

/* 임박 배지 — 다크 모드에서 danger가 밝은 빨강으로 톤 다운되어 흰 글자 대비가 약해지므로 어두운 글자로 전환 */
html.dark .order-card__urgent {
    color: #101418;
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
