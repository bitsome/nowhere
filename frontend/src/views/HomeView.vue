<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiMyDriver, apiMyVehicles, apiSetDriverMatchEnabled } from '../api/driver';
import { apiMatchPreferences } from '../api/match';
import { apiOrders, apiRecommendations } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import OrderCard from '../components/orders/OrderCard.vue';
import UiCard from '../components/ui/UiCard.vue';
import UiSection from '../components/ui/UiSection.vue';
import UiChip from '../components/ui/UiChip.vue';
import { matchTimeInfo } from '../utils/matchTime';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'HomeView' });

const router = useRouter();
const message = useMessage();

const driver = ref(null);
const vehicle = ref(null);
const preference = ref(null);
const preferences = ref([]);
const recommendations = ref([]);
const currentTrips = ref([]);
const loading = ref(true);

const matchEnabled = computed(() => Boolean(driver.value?.match_enabled));
// 활성 매칭 설정이 없으면 추천 조건이 없는 상태 — 홈에서 설정을 유도하는 힌트를 띄운다
const hasActivePreference = computed(() => preferences.value.some((p) => p.is_active));

// 매칭 설정 시간대 — 날짜(오늘/내일)를 앞에 붙이고, 자정을 넘기면 '다음날' 배지를 단다
const preferenceTime = computed(() => matchTimeInfo(preference.value || {}));

// 강력추천 — 하차지와 같은 구·같은 공항 터미널에서 이어지는 연결 운행 전용.
// 연결1 뒤에 이어지는 연결2를 같은 체인으로 묶어 노출하고,
// 연결1 없이 고립된 연결2는 표시하지 않는다 (공운행 없는 연속 체인만).
const strongChains = computed(() => {
    const chains = [];

    for (const r of recommendations.value) {
        if (r.recommend_level !== 'strong') {
            continue;
        }

        const last = chains[chains.length - 1];

        if (r.chain_leg === 3 && last && last.legs[last.legs.length - 1].id === r.chain_prev_id) {
            last.legs.push(r);
        } else if (r.chain_leg === 2) {
            chains.push({ legs: [r] });
        }
    }

    return chains.slice(0, 3);
});
// 추천일정 — 같은 시/도 연결 운행(일반) + 매칭 설정·이력 추천
const regularRecs = computed(() =>
    recommendations.value.filter((r) => r.recommend_level !== 'strong').slice(0, 3),
);
const hasStrong = computed(() => strongChains.value.length > 0);
const strongSlot = computed(() => (hasStrong.value ? strongChains.value : regularRecs.value));
const hasRegular = computed(() => regularRecs.value.length > 0);

// 실제 하차 시각 — 출발 + 랜딩 대기(공항 픽업이면) + 소요시간. 소요시간이 없으면 빈 문자열.
const arrivalTime = (row) => {
    const t = row?.time;
    const mins = row?.estimatedDurationMinutes;
    if (!t || !mins) return '';
    const [h, m] = t.split(':').map(Number);
    const wait = row?.landingWaitMinutes ?? 0;
    const total = h * 60 + m + wait + mins;
    return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
};

// 연결 대기 시간 — 이전 운행 도착 시각 ~ 다음 운행 출발 시각 (예: "2시간 후 출발")
const waitLabel = (prev, next) => {
    const arr = arrivalTime(prev);
    const nt = next?.time;
    if (!arr || !nt) return '';
    const [ah, am] = arr.split(':').map(Number);
    const [nh, nm] = nt.split(':').map(Number);
    const diffMin = nh * 60 + nm - (ah * 60 + am);
    if (diffMin < 60) return `${diffMin}분 후 출발`;
    return `${Math.round(diffMin / 60)}시간 후 출발`;
};

// 샌딩/랜딩 구분 — serviceIcon(sending/landing/pickup) 기준.
// service_type 누락 데이터는 방향(공항 포함 여부)으로 판별한다.
const serviceTypeLabel = (row) => {
    const labels = { sending: '샌딩', landing: '랜딩', pickup: '픽업' };

    if (labels[row.serviceIcon]) {
        return labels[row.serviceIcon];
    }

    const [pickup, dropoff] = (row.route ?? '').split('→').map((part) => part.trim());

    if (pickup?.includes('공항')) {
        return '랜딩';
    }

    if (dropoff?.includes('공항')) {
        return '샌딩';
    }

    return '운행';
};

const openOrder = (order) => router.push({ name: 'order-detail', params: { id: order.id } });

// silent=true면 기존 내용을 유지한 채 조용히 갱신한다 (탭 복귀 시 깜빡임 방지).
const load = async (silent = false) => {
    if (!silent) {
        loading.value = true;
    }

    // 드라이버·차량·매칭설정·추천일정·현재 운행을 병렬로 호출해 대기 시간을 줄인다.
    // (실패한 항목만 조용히 건너뛴다)
    const [me, vehicles, prefs, recs, trips] = await Promise.allSettled([
        apiMyDriver(),
        apiMyVehicles(),
        apiMatchPreferences(),
        apiRecommendations(),
        apiOrders({ scope: 'mine', source: 'all', tab: '진행중' }),
    ]);

    if (me.status === 'fulfilled') {
        driver.value = me.value.data.data;
    }

    if (vehicles.status === 'fulfilled') {
        vehicle.value = (vehicles.value.data.data ?? [])[0] ?? null;
    }

    if (prefs.status === 'fulfilled') {
        const list = prefs.value.data.data ?? [];
        preferences.value = list;
        preference.value = list.find((p) => p.is_active) ?? list[0] ?? null;
    }

    if (recs.status === 'fulfilled') {
        recommendations.value = recs.value.data.data ?? [];
    }

    if (trips.status === 'fulfilled') {
        currentTrips.value = trips.value.data.data ?? [];
    }

    if (!silent) {
        loading.value = false;
    }
};

const toggleMatch = async (enabled) => {
    const prev = driver.value?.match_enabled ?? false;

    if (driver.value) {
        driver.value.match_enabled = enabled;
    }

    try {
        await apiSetDriverMatchEnabled(enabled);
        message.success(enabled ? '빠른 매칭을 켰습니다.' : '빠른 매칭을 껐습니다.');
    } catch (e) {
        if (driver.value) {
            driver.value.match_enabled = prev;
        }
        message.error(getApiErrorMessage(e, '매칭 설정을 변경하지 못했습니다.'));
    }
};

const quickMenus = [
    { name: 'market', icon: 'market', label: '마켓' },
    { name: 'match', icon: 'match', label: '매칭 찾기' },
    { name: 'my-market', icon: 'my-market', label: '내 마켓' },
    { name: 'community', icon: 'community', label: '커뮤니티' },
    { name: 'notifications', icon: 'notifications', label: '알림' },
    { name: 'profile', icon: 'profile', label: '프로필' },
];

onMounted(load);

// keep-alive 복귀 시 조용히 갱신 — "불러오는 중" 화면 없이 기존 내용을 유지한다
onActivated(() => {
    if (!loading.value) {
        load(true);
    }
});
</script>

<template>
    <div class="home-page">
        <!-- 로딩 스켈레톤 — 실제 레이아웃과 동일한 골격을 그려 레이아웃 밀림(CLS)을 막는다 -->
        <div v-if="loading" class="home-skeleton" aria-hidden="true">
            <div class="home-top">
                <div class="sk-card home-skeleton__match">
                    <div class="sk-line sk-line--md" style="width: 55%;" />
                    <div class="sk-line sk-line--sm" style="width: 85%;" />
                    <div class="home-skeleton__chips">
                        <div class="sk-chip" />
                        <div class="sk-chip" />
                        <div class="sk-chip" />
                    </div>
                    <div class="sk-line sk-line--cta" />
                </div>
            </div>

            <div class="home-skeleton__section">
                <div class="sk-line sk-line--title" />
                <div class="home-menu">
                    <div v-for="n in 6" :key="`menu-${n}`" class="sk-card home-skeleton__menu">
                        <div class="sk-line sk-line--sm" style="width: 24px;" />
                        <div class="sk-line sk-line--sm" style="width: 52px;" />
                    </div>
                </div>
            </div>
        </div>

        <template v-else>
            <!-- 빠른 매칭 -->
            <div class="home-top">
                <UiCard tone="accent" class="home-match">
                    <div class="home-match__head">
                        <div>
                            <div class="home-match__title">
                                <BaseIcon class="home-match__flash" name="flash" :size="16" />
                                빠른 매칭 설정
                            </div>
                            <div class="home-match__sub">조건에 맞는 운행을 자동으로 추천받습니다.</div>
                        </div>
                        <button
                            type="button"
                            class="home-switch"
                            :class="{ 'home-switch--on': matchEnabled }"
                            aria-label="빠른 매칭"
                            @click="toggleMatch(!matchEnabled)"
                        >
                            <span></span>
                        </button>
                    </div>

                    <div v-if="vehicle || preference" class="home-match__chips">
                        <UiChip v-if="vehicle"><b>차량</b>{{ vehicle.title || vehicle.vehicle_type || '등록 차량' }}</UiChip>
                        <UiChip v-if="preference?.area"><b>지역</b>{{ preference.area }}</UiChip>
                        <UiChip v-if="preference?.start_time">
                            <b>시간</b>
                            <template v-if="preferenceTime.date">{{ preferenceTime.date }}&nbsp;</template>
                            {{ preferenceTime.start }}–
                            <span v-if="preferenceTime.overnight" class="home-match__nextday">다음날</span>
                            {{ preferenceTime.end }}
                        </UiChip>
                        <UiChip v-if="preference?.min_revenue"><b>최소금액</b>{{ preference.min_revenue.toLocaleString() }}원</UiChip>
                    </div>

                    <button type="button" class="home-match__cta" @click="router.push({ name: 'match' })">
                        <span class="home-match__cta-label">매칭 조건 빠르게 설정하기</span>
                        <span class="home-match__cta-arrow"><BaseIcon name="arrow-forward" :size="14" /></span>
                    </button>
                </UiCard>
            </div>

            <!-- 강력추천 — 하차지와 같은 구·같은 공항 터미널에서 이어지는 연결 운행 전용 -->
            <UiSection v-if="strongSlot.length || !hasActivePreference" title="강력추천">
                <p v-if="strongSlot.length" class="home-rec__hint">
                    {{ hasStrong ? '하차지와 같은 구·같은 공항 터미널에서 이어지는 복귀 노선이에요' : '지금 조건에 맞는 운행이에요' }}
                </p>
                <!-- 연결 체인 — 현재 운행 → 연결1 → 연결2 (공운행 없이 이어지는 연속 체인) -->
                <div v-if="hasStrong" class="home-chain">
                    <div v-if="currentTrips.length" class="home-chain__row">
                        <span class="home-chain__label">현재 운행</span>
                        <span class="home-chain__type">{{ serviceTypeLabel(currentTrips[0]) }}</span>
                        <span class="home-chain__when">
                            {{ currentTrips[0].time }}<template v-if="arrivalTime(currentTrips[0])"> → {{ arrivalTime(currentTrips[0]) }}</template>
                        </span>
                        <strong class="home-chain__route">{{ currentTrips[0].route }}</strong>
                    </div>
                    <template v-for="(chain, ci) in strongChains" :key="`chain-${ci}`">
                        <div v-if="ci > 0" class="home-chain__divider">
                            <span class="home-chain__divider-line"></span>
                            <span class="home-chain__divider-label">다른 연결 경로</span>
                            <span class="home-chain__divider-line"></span>
                        </div>
                        <template v-for="(leg, li) in chain.legs" :key="leg.key">
                            <div class="home-chain__connector">
                                <BaseIcon name="chevron-down" :size="14" />
                                <span
                                    v-if="li === 0 && currentTrips.length"
                                    class="home-chain__wait"
                                >{{ waitLabel(currentTrips[0], leg) }}</span>
                                <span v-else-if="li > 0" class="home-chain__wait">{{ waitLabel(chain.legs[li - 1], leg) }}</span>
                            </div>
                            <button type="button" class="home-chain__leg" @click="openOrder(leg)">
                                <span class="home-chain__type">{{ serviceTypeLabel(leg) }}</span>
                                <span class="home-chain__when">
                                    {{ leg.time }}<template v-if="arrivalTime(leg)"> → {{ arrivalTime(leg) }}</template>
                                </span>
                                <span class="home-chain__leg-route">{{ leg.route }}</span>
                            </button>
                        </template>
                    </template>
                </div>
                <div v-else-if="strongSlot.length" class="order-grid home-rec">
                    <OrderCard
                        v-for="order in regularRecs"
                        :key="order.key"
                        :order="order"
                        :recommend-reason="order.recommend_reason"
                    />
                </div>
                <EmptyState
                    v-else
                    icon="match"
                    title="아직 추천할 운행이 없어요"
                    hint="매칭 설정을 등록해 두면 조건에 맞는 운행을 추천해 드려요"
                >
                    <template #action>
                        <button type="button" class="home-rec__cta" @click="router.push({ name: 'match' })">
                            <BaseIcon name="settings" :size="14" />
                            매칭 설정하기
                        </button>
                    </template>
                </EmptyState>
            </UiSection>

            <!-- 추천일정 — 같은 시/도 연결 운행(일반)·매칭 설정·자주 다니는 노선·자주 운행한 시간 -->
            <UiSection v-if="hasStrong && hasRegular" title="추천일정">
                <p class="home-rec__hint">지금 조건에 맞는 운행이에요</p>
                <div class="order-grid home-rec">
                    <OrderCard
                        v-for="order in regularRecs"
                        :key="order.key"
                        :order="order"
                        :recommend-reason="order.recommend_reason"
                    />
                </div>
            </UiSection>

            <!-- 빠른 메뉴 -->
            <UiSection title="빠른 메뉴">
                <div class="home-menu">
                    <UiCard
                        v-for="menu in quickMenus"
                        :key="menu.name"
                        tag="a"
                        hover
                        class="home-menu__item"
                        @click.prevent="router.push({ name: menu.name })"
                    >
                        <b class="home-menu__icon"><BaseIcon :name="menu.icon" :size="24" /></b>
                        <span>{{ menu.label }}</span>
                    </UiCard>
                </div>
            </UiSection>
        </template>
    </div>
</template>

<style scoped>
.home-page {
    width: 100%;
    max-width: 880px;
    margin: 0 auto;
    padding: 0 20px 24px;
}

/* 모바일 — 좌우 여백 없이 화면 폭을 꽉 채운다 */
@media (max-width: 480px) {
    .home-page {
        width: calc(100% + 40px);
        margin: 0 -20px;
        padding: 0 14px 24px;
        max-width: none;
    }
}

/* ── 홈 로딩 스켈레톤 — 실제 레이아웃과 동일한 골격(CLS 방지) ── */
.home-skeleton {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: 16px;
}

/* 공용 스켈레톤 — 카드/라인/칩 (OrderCardSkeleton과 동일한 shimmer 패턴) */
.sk-card {
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    padding: 16px;
}

.sk-line {
    height: 14px;
    border-radius: 7px;
    background: linear-gradient(90deg, var(--border) 25%, rgba(128, 128, 128, 0.12) 50%, var(--border) 75%);
    background-size: 200% 100%;
    animation: sk-shimmer 1.4s infinite;
}

.sk-line--sm { height: 11px; }
.sk-line--md { height: 16px; }
.sk-line--lg { height: 22px; }
.sk-line--title { width: 90px; height: 16px; margin-bottom: 10px; }
.sk-line--cta { height: 46px; border-radius: 13px; }

.sk-chip {
    width: 56px;
    height: 16px;
    border-radius: 8px;
    background: var(--border);
    animation: sk-shimmer 1.4s infinite;
}

@keyframes sk-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.home-skeleton__match {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.home-skeleton__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.home-skeleton__section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.home-skeleton__menu {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
    height: 66px;
}

@media (prefers-reduced-motion: reduce) {
    .sk-line,
    .sk-chip {
        animation: none;
    }
}

/* 상단 — 빠른 매칭 */
.home-top {
    display: block;
}

/* 빠른 매칭 */
.home-match {
    margin-top: 16px;
}
.home-match__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.home-match__title {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 800;
}

.home-match__flash {
    color: #f7b731;
}
.home-match__sub {
    margin-top: 3px;
    font-size: 11px;
    color: var(--text-muted);
}
.home-switch {
    flex-shrink: 0;
    width: 46px;
    height: 27px;
    border-radius: 20px;
    border: 0;
    background: #d3d5da;
    position: relative;
    cursor: pointer;
    transition: background 0.18s ease;
}
.home-switch span {
    position: absolute;
    width: 21px;
    height: 21px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.28);
    left: 3px;
    top: 3px;
    transition: transform 0.18s ease, background 0.18s ease;
}
.home-switch--on {
    background: var(--brand);
}
.home-switch--on span {
    background: #ffffff;
    transform: translateX(19px);
}
html.dark .home-switch {
    background: #3a4146;
}
html.dark .home-switch span {
    background: #8b9490;
    box-shadow: none;
}
html.dark .home-switch--on span {
    background: #07120e;
}
.home-match__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}
.home-match__chips b {
    color: var(--brand);
    margin-right: 4px;
}
/* 자정을 넘기는 시간대 — '다음날' 배지 */
.home-match__nextday {
    margin: 0 3px;
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}
/* 큼직한 CTA 버튼 — 매칭 설정으로 이동 */
.home-match__cta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    margin-top: 14px;
    padding: 14px 16px;
    border: 0;
    border-radius: 13px;
    background: var(--brand);
    color: #ffffff;
    font-family: inherit;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 8px 22px color-mix(in srgb, var(--brand) 35%, transparent);
    transition: transform 0.12s ease, box-shadow 0.12s ease;
}
html.dark .home-match__cta {
    color: #07120e;
}
.home-match__cta:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 26px color-mix(in srgb, var(--brand) 45%, transparent);
}
.home-match__cta:active {
    transform: translateY(0);
}
.home-match__cta-arrow {
    font-size: 16px;
    font-weight: 400;
    line-height: 1;
}

/* 추천일정 */
.home-rec {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 4px;
}
.home-rec__hint {
    margin: -4px 0 10px;
    font-size: 11px;
    color: var(--text-muted);
}

/* 연결 체인 — 현재 운행 → 연결1 → 연결2 */
.home-chain {
    margin: 0 0 12px;
}
.home-chain__row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    font-size: 12px;
}
.home-chain__label {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}
/* 샌딩/랜딩 구분 배지 */
.home-chain__type {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}
/* 시간 — 출발 → 도착 (노선보다 앞에 표시) */
.home-chain__when {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
}
.home-chain__route {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text);
    font-weight: 400;
}
/* 연결 운행 행 — 시간 우선 + 샌딩/랜딩 구분 (카드 클릭 시 상세) */
.home-chain__leg {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    font-family: inherit;
    font-size: 12px;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.12s ease;
}
.home-chain__leg:hover {
    border-color: var(--brand);
}
.home-chain__leg-route {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text);
}
.home-chain__connector {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 4px 0;
    color: var(--text-muted);
}
/* 연결 대기 시간 — 이전 운행 도착 ~ 다음 운행 출발 사이 간격 */
.home-chain__wait {
    font-size: 10px;
    color: var(--brand);
    white-space: nowrap;
}
.home-chain__divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 14px 0 10px;
}
.home-chain__divider-line {
    flex: 1;
    height: 1px;
    background: var(--border);
}
.home-chain__divider-label {
    flex-shrink: 0;
    font-size: 10px;
    color: var(--text-muted);
}
.home-rec__cta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    border: 1px solid var(--brand);
    border-radius: 999px;
    background: transparent;
    color: var(--brand);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.12s ease, color 0.12s ease;
}
.home-rec__cta:hover {
    background: var(--brand);
    color: #ffffff;
}
html.dark .home-rec__cta:hover {
    color: #07120e;
}

/* 빠른 메뉴 */
.home-menu {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 9px;
}
.home-menu__item {
    display: flex;
    flex-direction: column;
    justify-content: center;
    height: 66px;
    padding: 0 13px;
}
.home-menu__item b {
    color: var(--brand);
    font-size: 12px;
}
.home-menu__item span {
    font-size: 11px;
    margin-top: 5px;
    color: var(--text-muted);
}
</style>
