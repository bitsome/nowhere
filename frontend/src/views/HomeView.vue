<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiMyDriver, apiMyVehicles, apiSetDriverMatchEnabled } from '../api/driver';
import { apiMatchPreferences } from '../api/match';
import { apiRecommendations } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import OrderCard from '../components/orders/OrderCard.vue';
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
const recommendations = ref([]);
const loading = ref(true);

const matchEnabled = computed(() => Boolean(driver.value?.match_enabled));

// silent=true면 기존 내용을 유지한 채 조용히 갱신한다 (탭 복귀 시 깜빡임 방지).
const load = async (silent = false) => {
    if (!silent) {
        loading.value = true;
    }

    // 드라이버·차량·매칭설정·추천일정을 병렬로 호출해 대기 시간을 줄인다.
    // (실패한 항목만 조용히 건너뛴다)
    const [me, vehicles, prefs, recs] = await Promise.allSettled([
        apiMyDriver(),
        apiMyVehicles(),
        apiMatchPreferences(),
        apiRecommendations(),
    ]);

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

    if (recs.status === 'fulfilled') {
        recommendations.value = recs.value.data.data ?? [];
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

            <!-- 추천일정 — 일정이 없어도 매칭 설정·자주 다니는 노선 기준으로 마켓 운행 추천 -->
            <UiSection v-if="recommendations.length" title="추천일정">
                <p class="home-rec__hint">지금 조건에 맞는 운행이에요</p>
                <div class="order-grid home-rec">
                    <OrderCard
                        v-for="order in recommendations"
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
    margin-top: 4px;
}
.home-rec__hint {
    margin: -4px 0 10px;
    font-size: 11px;
    color: var(--text-muted);
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
