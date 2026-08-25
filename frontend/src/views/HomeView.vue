<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { apiMyDriver, apiMyVehicles, apiSetDriverMatchEnabled } from '../api/driver';
import { apiMatchPreferences } from '../api/match';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import UiCard from '../components/ui/UiCard.vue';
import UiSection from '../components/ui/UiSection.vue';
import UiChip from '../components/ui/UiChip.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'HomeView' });

const router = useRouter();
const message = useMessage();

const driver = ref(null);
const vehicle = ref(null);
const preference = ref(null);
const myOrders = ref([]);
const loading = ref(true);

// 오늘 날짜 키 (YYYY-MM-DD)
const now = new Date();
const todayKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

// 오늘 예정 운행 (받은 운행 중 오늘 서비스)
const todayRides = computed(() => myOrders.value.filter((o) => o.sortDate === todayKey));

const matchEnabled = computed(() => Boolean(driver.value?.match_enabled));

const isDriving = (ride) => ride.status === 'driving';

const metaText = (ride) =>
    [ride.passengerCount ? `${ride.passengerCount}명` : '', ride.vehicle ? ride.vehicle : '', ride.serviceLabel || '']
        .filter(Boolean)
        .join(' · ');

// silent=true면 기존 내용을 유지한 채 조용히 갱신한다 (탭 복귀 시 깜빡임 방지).
const load = async (silent = false) => {
    if (!silent) {
        loading.value = true;
    }

    // 운행·드라이버·차량·매칭설정을 병렬로 호출해 대기 시간을 줄인다.
    // (순차 4회 왕복 → 최대 1회 왕복) 실패한 항목만 조용히 건너뛴다.
    const [orders, me, vehicles, prefs] = await Promise.allSettled([
        apiOrders({
            scope: 'mine',
            source: 'received',
            tab: '진행중',
            sort: 'date',
            date: todayKey, // 오늘 예정 운행만 내려받아 응답을 가볍게
            per_page: 100,
        }),
        apiMyDriver(),
        apiMyVehicles(),
        apiMatchPreferences(),
    ]);

    if (orders.status === 'fulfilled') {
        myOrders.value = orders.value.data.data ?? [];
    } else {
        message.error(getApiErrorMessage(orders.reason, '내 운행을 불러오지 못했습니다.'));
    }

    if (me.status === 'fulfilled') {
        driver.value = me.value.data.data;
    }

    if (vehicles.status === 'fulfilled') {
        vehicle.value = (vehicles.value.data.data ?? [])[0] ?? null;
    }

    if (prefs.status === 'fulfilled') {
        const list = prefs.value.data.data ?? [];
        preference.value = list.find((p) => p.is_active) ?? list[0] ?? null;
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
    { name: 'order-create', icon: 'order-create', label: '내 운행' },
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
                <div class="home-rides__grid">
                    <div v-for="n in 3" :key="`ride-${n}`" class="sk-card home-skeleton__ride">
                        <div class="home-skeleton__ride-top">
                            <div class="sk-chip" />
                            <div class="sk-line sk-line--sm" style="width: 46px;" />
                        </div>
                        <div class="sk-line sk-line--md" />
                        <div class="sk-line sk-line--sm" style="width: 70%;" />
                        <div class="sk-line sk-line--sm" style="width: 40%;" />
                    </div>
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
                            <b>시간</b>{{ preference.start_time }}–{{ preference.end_time || '24:00' }}
                        </UiChip>
                        <UiChip v-if="preference?.min_revenue"><b>최소금액</b>{{ preference.min_revenue.toLocaleString() }}원</UiChip>
                    </div>

                    <button type="button" class="home-match__cta" @click="router.push({ name: 'match' })">
                        <span class="home-match__cta-label">매칭 조건 빠르게 설정하기</span>
                        <span class="home-match__cta-arrow"><BaseIcon name="arrow-forward" :size="14" /></span>
                    </button>
                </UiCard>
            </div>

            <!-- 내 운행 -->
            <UiSection title="내 운행">
                <template #action>
                    <a class="home-rides__all" @click.prevent="router.push({ name: 'order-create' })">전체 보기 <BaseIcon name="arrow-forward" :size="13" /></a>
                </template>

                <div v-if="todayRides.length === 0" class="home-rides__empty">
                    <EmptyState icon="car" title="오늘 예정된 운행이 없습니다" hint="마켓에서 운행을 찾아보세요" />
                </div>

                <div class="home-rides__grid">
                    <UiCard
                        v-for="ride in todayRides.slice(0, 3)"
                        :key="ride.id"
                        tag="a"
                        hover
                        :tone="isDriving(ride) ? 'accent' : 'surface'"
                        class="home-ride"
                        @click.prevent="router.push({ name: 'order-detail', params: { id: ride.id } })"
                    >
                        <div class="home-ride__top">
                            <UiChip :variant="isDriving(ride) ? 'green' : 'yellow'">
                                <template v-if="isDriving(ride)"><BaseIcon name="ellipse" :size="8" /> 운행중</template>
                                <template v-else>예정</template>
                            </UiChip>
                            <span class="home-ride__time">{{ ride.time || ride.service_time || '' }}</span>
                        </div>
                        <div class="home-ride__route">{{ ride.route }}</div>
                        <div v-if="metaText(ride)" class="home-ride__meta">{{ metaText(ride) }}</div>
                        <div class="home-ride__bottom">
                            <span class="home-ride__when">오늘</span>
                            <span class="home-ride__price">{{ ride.amount }}</span>
                        </div>
                    </UiCard>
                </div>

                <div v-if="todayRides.length > 0" class="home-rides__more" @click="router.push({ name: 'order-create' })">
                    내 운행 전체 보기 <BaseIcon name="arrow-forward" :size="13" />
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

.home-skeleton__ride {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.home-skeleton__ride-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
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
    font-size: 14px;
    font-weight: 800;
}

.home-match__flash {
    color: #f7b731;
}
.home-match__sub {
    margin-top: 3px;
    font-size: 10px;
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
    font-size: 14px;
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
    font-size: 20px;
    font-weight: 400;
    line-height: 1;
}

/* 내 운행 */
.home-rides__all {
    font-size: 10px;
    color: var(--text-muted);
    text-decoration: none;
    cursor: pointer;
}
.home-rides__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
}
.home-ride {
    display: flex;
    flex-direction: column;
    padding: 12px 13px;
}
.home-ride__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.home-ride__time {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 700;
}
.home-ride__route {
    margin-top: 6px;
    font-size: 14px;
    font-weight: 800;
}
.home-ride__meta {
    margin-top: 3px;
    font-size: 11px;
    color: var(--text-muted);
}
.home-ride__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
}
.home-ride__when {
    font-size: 11px;
    color: var(--text-muted);
}
.home-ride__price {
    font-size: 14px;
    font-weight: 800;
    color: var(--brand);
}
.home-rides__more {
    padding: 10px 0;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    color: var(--brand);
    cursor: pointer;
}
.home-rides__empty {
    padding: 8px 0 4px;
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
    font-size: 16px;
}
.home-menu__item span {
    font-size: 10px;
    margin-top: 5px;
    color: var(--text-muted);
}
</style>
