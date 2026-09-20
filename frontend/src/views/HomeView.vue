<script setup>
import { computed, onActivated, onBeforeUnmount, onDeactivated, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { apiMatchPreferences } from '../api/match';
import { apiOrders, apiRecommendations, apiBatchClaim, apiClaimSummary, apiWithdrawExpiredClaim } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import OrderCard from '../components/orders/OrderCard.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import UiCard from '../components/ui/UiCard.vue';
import UiSection from '../components/ui/UiSection.vue';
import CustomerHome from './home/CustomerHome.vue';
import { relativeDateLabel } from '../utils/dateText';
import { trackClick, trackImpression } from '../utils/tracking';
import { ROLE_DRIVER } from '../data/roles';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'HomeView' });

const router = useRouter();
const message = useMessage();
const auth = useAuthStore();

// 기사 홈만 추천·가져오기를 로드한다 — 등록자(Customer)는 등록자용 홈을 보여준다
const isDriver = computed(() => auth.user?.role === ROLE_DRIVER);

const preferences = ref([]);
const recommendations = ref([]);
const currentTrips = ref([]);
const loading = ref(true);
// 마지막 홈 데이터 조회 시각 — 탭 복귀 시 짧은 간격의 중복 재조회를 막는다
let lastLoadedAt = 0;
const HOME_REFRESH_MIN_GAP_MS = 10000;

// 활성 매칭 설정이 없으면 추천 조건이 없는 상태 — 홈에서 설정을 유도하는 힌트를 띄운다
const hasActivePreference = computed(() => preferences.value.some((p) => p.is_active));

// 강력추천 — 하차지와 같은 구·같은 공항에서 이어지는 양방향(왕복) 연결 운행 전용.
// 연결1(강력)로 시작하는 체인의 연결2~연결4를 같은 체인으로 묶어 노출하고,
// 연결1 없이 고립된 연결은 표시하지 않는다 (공운행 없는 연속 체인만).
// 체인은 백엔드가 시간대가 맞는(매칭 설정) 체인부터 정렬해 주므로,
// 표시 순서대로 추천1 → 추천2 → 추천3으로 번호를 붙인다.
const strongChains = computed(() => {
    const chains = [];

    for (const r of recommendations.value) {
        const last = chains[chains.length - 1];

        if (last && last.legs[last.legs.length - 1].id === r.chain_prev_id) {
            last.legs.push(r);
        } else if (r.chain_leg === 2 && r.recommend_level === 'strong') {
            chains.push({
                legs: [r],
            });
        }
    }

    return chains;
});
// 표시 체인이 있는지 — 일괄요청중(보낸) 체인도 포함한다
const hasStrong = computed(() => displayChains.value.length > 0);

// 단일 추천 — 연결고리(체인)가 아닌 개별 운행 (매칭 설정/자주 다니는 노선/시간대 기반)
const singleRecs = computed(() =>
    recommendations.value.filter((r) => r.kind !== 'set' && r.recommend_reason !== '연결 운행')
);

// 셋트 추천 — 묶음 운행 카드 (한 다리라도 매칭되면 전체 일정이 추천에 포함된다)
const setRecs = computed(() => recommendations.value.filter((r) => r.kind === 'set'));

// 일괄요청 보낸 추천1 체인 — 요청 후에도 홈에서 사라지지 않고 '일괄요청중'으로 남는다.
// (추천 목록은 요청한 운행을 제외하므로 보낸 체인을 로컬에 보관해 다시 붙인다)
const CLAIMED_CHAINS_KEY = 'nowhere:home:claimed-chains';

const readClaimedChains = () => {
    try {
        return JSON.parse(localStorage.getItem(CLAIMED_CHAINS_KEY) ?? '[]');
    } catch {
        return [];
    }
};

const claimedChains = ref(readClaimedChains());

const saveClaimedChains = () => {
    try {
        localStorage.setItem(CLAIMED_CHAINS_KEY, JSON.stringify(claimedChains.value));
    } catch {
        // 저장 실패는 무시 — 세션 내에서만 유지
    }
};

// 가져오기 요청 자동 만료 시간 — 백엔드 OrderClaimService::CLAIM_EXPIRE_SECONDS(30분)와 일치
const CLAIM_EXPIRE_MS = 30 * 60 * 1000;

// 1초 틱 — 일괄요청중 체인의 남은 시간을 카운트다운한다
const nowTick = ref(Date.now());

// 일괄요청중 체인의 다리(운행)별 현재 상태 — id → { status, claimedAt }
// (요약 API 폴링으로 갱신: acceptance_pending=대기, accepted=승인, published=거절)
const claimStates = ref({});

// 보낸 요청들의 id 모음 — 요약 폴링 대상
const claimedOrderIds = computed(() => {
    const ids = [];

    for (const chain of claimedChains.value) {
        for (const leg of chain.legs) ids.push(leg.id);
    }

    return ids;
});

// 체인별 집계 — 남은 시간(가장 빨리 만료되는 다리 기준)·승인 수·거절 수
const chainStatuses = computed(() => {
    const map = {};

    for (const chain of claimedChains.value) {
        const key = chain.legs[0]?.id;
        if (!key) continue;

        let approved = 0;
        let rejected = 0;
        let minRemain = Infinity;

        for (const leg of chain.legs) {
            const state = claimStates.value[leg.id];
            if (!state) continue;

            if (state.status === 'accepted') approved++;
            else if (state.status === 'published') rejected++;

            // 대기 중인 다리의 남은 시간 — 만료된(음수) 건은 제외
            if (state.claimedAt) {
                const remain = CLAIM_EXPIRE_MS - (nowTick.value - Date.parse(state.claimedAt));
                if (remain > 0) minRemain = Math.min(minRemain, remain);
            }
        }

        map[key] = {
            approved,
            rejected,
            remainSeconds: minRemain === Infinity ? null : Math.max(1, Math.ceil(minRemain / 1000)),
        };
    }

    return map;
});

// 템플릿용 조회 헬퍼 — 체인의 집계 결과(없으면 null)
const chainStatus = (chain) => chainStatuses.value[chain.legs[0]?.id] ?? null;

// 만료된 요청 자동 철회가 실행 중인지 — 폴링이 겹쳐 이중 호출되지 않게 막는다
let withdrawingExpired = false;

// 요약 폴링 — 서버의 현재 상태를 받아 집계를 갱신하고,
// 만료된 대기 요청은 자동 철회한 뒤 남은 다리가 없으면(모두 승인·거절) 홈에서 정리한다.
// (철회된 운행은 마켓으로 돌아가므로 다시 일괄요청 버튼이 활성화된다)
const syncClaimSummary = async () => {
    const ids = claimedOrderIds.value;
    if (!ids.length) return;

    // 탭이 숨겨진 동안에는 요청을 보내지 않는다 (복귀 시 SSE/다음 폴링이 갱신 — 부하 절감)
    if (document.visibilityState === 'hidden') {
        return;
    }

    // 마지막 틱 이후 오래 지났을 수 있으므로 현재 시각으로 되돌린다
    nowTick.value = Date.now();

    try {
        const { data } = await apiClaimSummary(ids);
        const next = {};

        for (const row of data.data ?? []) {
            next[row.id] = { status: row.status, claimedAt: row.claimedAt };
        }

        claimStates.value = next;

        // 만료된 대기 요청을 찾아 자동 철회 — 재신청 잠금이 없어 30분 후 바로 다시 요청할 수 있다
        const expiredIds = [];

        for (const chain of claimedChains.value) {
            for (const leg of chain.legs) {
                const state = next[leg.id];

                if (state?.status === 'acceptance_pending' && state.claimedAt) {
                    const remain = CLAIM_EXPIRE_MS - (nowTick.value - Date.parse(state.claimedAt));

                    if (remain <= 0) expiredIds.push(leg.id);
                }
            }
        }

        if (expiredIds.length && !withdrawingExpired) {
            withdrawingExpired = true;

            const results = await Promise.allSettled(expiredIds.map((id) => apiWithdrawExpiredClaim(id)));

            withdrawingExpired = false;

            // 철회에 성공한 운행은 마켓으로 돌아갔으므로 집계에서 '거절'과 동일하게 처리한다
            let withdrawnCount = 0;

            results.forEach((result, i) => {
                if (result.status === 'fulfilled') {
                    const id = expiredIds[i];

                    if (next[id]) next[id].status = 'published';
                    withdrawnCount++;
                }
            });

            if (withdrawnCount > 0) {
                message.info(`${withdrawnCount}건 요청이 만료되어 자동 취소되었습니다. 다시 요청할 수 있습니다.`);
            }
        }

        // 남은 다리가 하나도 없으면(모두 승인·거절·철회) 홈에서 정리한다
        const before = claimedChains.value.length;
        claimedChains.value = claimedChains.value.filter((chain) =>
            chain.legs.some((leg) => {
                const state = next[leg.id];

                return state && state.status === 'acceptance_pending';
            })
        );

        if (claimedChains.value.length !== before) {
            saveClaimedChains();

            // 철회된 운행이 마켓 추천에 다시 나타나도록 갱신 — 일괄요청 버튼이 되살아난다
            load(true);
        }
    } catch {
        // 조용히 건너뜀 — 다음 폴링에서 재시도
    }
};

let claimTickTimer = null;
let summaryPollTimer = null;

const startClaimTimers = () => {
    if (claimTickTimer || !claimedOrderIds.value.length) return;

    claimTickTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);

    summaryPollTimer = setInterval(syncClaimSummary, 3000);
    syncClaimSummary();
};

const stopClaimTimers = () => {
    if (claimTickTimer) {
        clearInterval(claimTickTimer);
        claimTickTimer = null;
    }

    if (summaryPollTimer) {
        clearInterval(summaryPollTimer);
        summaryPollTimer = null;
    }
};

// 일괄요청중 체인이 생기면 타이머를 켜고, 모두 정리되면 끈다
watch(claimedOrderIds, (ids) => {
    if (ids.length) startClaimTimers();
    else stopClaimTimers();
}, { immediate: true });

// 한 번에 보여주는 추천 수 — 처음 20개만 노출하고 '더보기'로 20개씩 이어 붙인다
const HOME_PAGE_SIZE = 20;
const singleShow = ref(HOME_PAGE_SIZE);
const chainShow = ref(HOME_PAGE_SIZE);
const setShow = ref(HOME_PAGE_SIZE);

// 아직 더 볼 추천이 있는지 (전체 수 > 현재 노출 수)
const singleHasMore = computed(() => singleRecs.value.length > singleShow.value);
const setHasMore = computed(() => setRecs.value.length > setShow.value);

const showMoreSingles = () => {
    singleShow.value += HOME_PAGE_SIZE;
};
const showMoreChains = () => {
    chainShow.value += HOME_PAGE_SIZE;
};
const showMoreSets = () => {
    setShow.value += HOME_PAGE_SIZE;
};

// 홈 표시용 체인 — 아직 요청 안 한 추천 체인 + 일괄요청 보낸 체인(일괄요청중)을 합쳐 번호를 다시 붙인다
const displayChains = computed(() => {
    const claimed = claimedChains.value.map((chain) => ({ ...chain, claimed: true }));
    const current = strongChains.value
        .filter((chain) => !claimed.some((c) => c.legs[0].id === chain.legs[0].id))
        .map((chain) => ({ ...chain, claimed: false }));

    return [...claimed, ...current].slice(0, chainShow.value).map((chain, index) => ({ ...chain, rankLabel: `추천${index + 1}` }));
});

// 왕복 체인 전체 수 — 더보기 버튼 표시와 탭 건수에 사용
const chainPoolCount = computed(() => {
    const claimed = claimedChains.value;
    const current = strongChains.value.filter((chain) => !claimed.some((c) => c.legs[0].id === chain.legs[0].id));

    return claimed.length + current.length;
});
const chainHasMore = computed(() => chainPoolCount.value > chainShow.value);

// 홈 추천 탭 — 단일(기본) / 왕복 / 셋트.
// '오늘 받은 추천' 퀘스트 아래에서 한 종류씩 골라 보게 해 판단 피로도를 낮춘다.
// (연결고리 추천은 '왕복 추천'으로 이름을 바꿨다)
const HOME_TABS = [
    { key: 'single', label: '단일' },
    { key: 'chain', label: '왕복' },
    { key: 'set', label: '셋트' },
];
const homeTab = ref('single');

// 탭 전환 — 해당 추천만 보여주고 노출 수는 처음 20개로 되돌린다
const pickHomeTab = (tabKey) => {
    homeTab.value = tabKey;
    singleShow.value = HOME_PAGE_SIZE;
    chainShow.value = HOME_PAGE_SIZE;
    setShow.value = HOME_PAGE_SIZE;
};

// 탭별 섹션 가시성 — 활성 탭의 그룹만 보인다
const showChains = computed(() => homeTab.value === 'chain' && hasStrong.value);
const showSingles = computed(() => homeTab.value === 'single' && singleRecs.value.length > 0);
const showSets = computed(() => homeTab.value === 'set' && setRecs.value.length > 0);

// 탭 칩 건수 — 전체 추천 수를 칩에 함께 보여준다 ('더보기'로 모두 확인할 수 있다)
const tabCounts = computed(() => ({
    single: singleRecs.value.length,
    chain: chainPoolCount.value,
    set: setRecs.value.length,
}));

// '일괄요청중' 버튼 클릭 — 요청 중인 내 마켓(요청·보냄)으로 이동
const goSentRequests = () => {
    router.push({ name: 'my-market', query: { tab: '요청', cat: 'sent' } });
};

// 추천1 체인 — 체인에 포함된 운행들을 등록자들에게 한 번에 가져오기 요청한다.
const batchClaiming = ref(false);
const batchClaimChain = async (chain) => {
    if (batchClaiming.value) return;

    batchClaiming.value = true;
    try {
        const ids = chain.legs.map((leg) => leg.id);
        const { data } = await apiBatchClaim(ids);
        const { succeeded, failed } = data.summary;

        message.success(
            failed > 0
                ? `일괄요청 완료: ${succeeded}건 성공, ${failed}건 실패`
                : `일괄요청 완료: ${succeeded}건 모두 요청했습니다.`
        );

        // 요청한 체인은 홈에 '일괄요청중'으로 남긴다 — 추천 목록에서 빠져도 사라지지 않는다
        claimedChains.value.push(chain);
        saveClaimedChains();

        // 요청한 운행은 마켓에서 빠졌으므로 추천 목록을 다시 불러온다
        load(true);
    } catch (e) {
        message.error(getApiErrorMessage(e, '일괄요청에 실패했습니다.'));
    } finally {
        batchClaiming.value = false;
    }
};

// silent=true면 기존 내용을 유지한 채 조용히 갱신한다 (탭 복귀 시 깜빡임 방지).
const load = async (silent = false) => {
    if (!silent) {
        loading.value = true;
    }

    // 매칭설정·추천(왕복)·현재 운행·보낸 요청을 병렬로 호출해 대기 시간을 줄인다.
    // (실패한 항목만 조용히 건너뛴다)
    const [prefs, recs, trips, sent] = await Promise.allSettled([
        apiMatchPreferences(),
        apiRecommendations(),
        apiOrders({ scope: 'mine', source: 'all', tab: '진행중' }),
        apiOrders({ scope: 'mine', source: 'all', tab: '요청', request_category: 'sent', per_page: 50 }),
    ]);

    if (prefs.status === 'fulfilled') {
        preferences.value = prefs.value.data.data ?? [];
    }

    if (recs.status === 'fulfilled') {
        recommendations.value = recs.value.data.data ?? [];
    }

    if (trips.status === 'fulfilled') {
        currentTrips.value = trips.value.data.data ?? [];
    }

    // 일괄요청 보낸 체인 중, 요청이 모두 해결(승인·거절·만료)되면 홈에서 정리한다
    if (sent.status === 'fulfilled') {
        const pendingIds = new Set((sent.value.data.data ?? []).map((o) => o.id));
        const before = claimedChains.value.length;
        claimedChains.value = claimedChains.value.filter((chain) => chain.legs.some((leg) => pendingIds.has(leg.id)));

        if (claimedChains.value.length !== before) {
            saveClaimedChains();
        }
    }

    // 조회 완료 시각 기록 — 짧은 탭 왕복 시 재조회 생략 기준
    lastLoadedAt = Date.now();

    if (!silent) {
        loading.value = false;
    }
};

const quickMenus = [
    { name: 'market', icon: 'market', label: '마켓' },
    { name: 'order-create', icon: 'order-create', label: '받은 운행' },
    { name: 'reviews', icon: 'reviews', label: '받은 리뷰' },
    { name: 'community', icon: 'community', label: '커뮤니티' },
    { name: 'notifications', icon: 'notifications', label: '알림' },
    { name: 'profile', icon: 'profile', label: '프로필' },
];

// ── 홈 히어로 — 오늘 받을 운행 (내 다음 운행이 있으면 그 운행, 없으면 최우선 추천) ──
// 히어로 카운트다운 갱신 주기 — 1분에 한 번
const heroNow = ref(Date.now());
let heroTimer = null;
const startHeroTimer = () => {
    if (heroTimer) {
        return;
    }
    heroTimer = setInterval(() => {
        heroNow.value = Date.now();
    }, 60000);
};
const stopHeroTimer = () => {
    if (heroTimer) {
        clearInterval(heroTimer);
        heroTimer = null;
    }
};

// 내 다음 운행 — 확정된 운행 중 날짜·시간순으로 가장 가까운 것
const todayTrip = computed(() =>
    [...currentTrips.value]
        .filter((t) => t.sortDate)
        .sort((a, b) => `${a.sortDate} ${a.sortTime || ''}`.localeCompare(`${b.sortDate} ${b.sortTime || ''}`))[0] ?? null
);

// 내 다음 운행이 없을 때 히어로로 대체할 최우선 추천 (단일 → 연결고리 첫 다리 → 셋트)
const topRec = computed(() => {
    if (todayTrip.value) {
        return null;
    }
    if (singleRecs.value.length) {
        return { kind: 'single', order: singleRecs.value[0] };
    }
    const chain = displayChains.value[0];
    if (chain?.legs?.length) {
        return { kind: 'chain', order: chain.legs[0] };
    }
    const set = setRecs.value[0];
    if (set) {
        return { kind: 'set', set };
    }
    return null;
});

// 히어로 표시 대상 — 내 다음 운행 또는 최우선 추천 (셋트는 첫 일정 정보로 정규화)
const heroOrder = computed(() => {
    const trip = todayTrip.value;
    if (trip) {
        return trip;
    }
    const rec = topRec.value;
    if (!rec) {
        return null;
    }
    if (rec.kind === 'set') {
        const first = rec.set.routes?.[0] ?? {};
        return {
            id: rec.set.firstOrderId,
            route: rec.set.name,
            time: first.time || '',
            date: first.date,
            sortDate: first.sortDate,
            flightNumber: first.flightNumber,
            tags: rec.set.tags || [],
        };
    }
    return rec.order;
});
const heroEyebrow = computed(() => (todayTrip.value ? '오늘의 운행' : '오늘 받을 추천'));
const heroRoute = computed(() => heroOrder.value?.route ?? '');
const heroDateText = computed(() => (heroOrder.value ? relativeDateLabel(heroOrder.value.date, heroOrder.value.sortDate) : ''));
const heroAmount = computed(() => (topRec.value?.kind === 'set' ? topRec.value.set.totalAmount : heroOrder.value?.amount ?? ''));
const heroCtaLabel = computed(() => {
    if (todayTrip.value) {
        return '운행 보기';
    }
    if (topRec.value?.kind === 'set') {
        return '셋트 확인하기';
    }
    return '운행 확인하고 신청하기';
});

// 운행 시작까지 남은 시간 — 확정(수락·운행중) 운행만, 하루 안으로 다가온 경우만 (1분마다 갱신)
const heroRemainText = computed(() => {
    const t = todayTrip.value;
    if (!t || (t.status !== 'accepted' && t.status !== 'driving') || !t.sortDate || !t.sortTime) {
        return '';
    }
    const start = new Date(`${t.sortDate}T${t.sortTime}:00`).getTime();
    const remainMs = start - heroNow.value;
    if (remainMs <= 0 || remainMs > 36 * 60 * 60 * 1000) {
        return '';
    }
    const minutes = Math.floor(remainMs / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    if (days > 0) {
        return `시작 ${days}일 ${hours % 24}시간 전`;
    }
    if (hours > 0) {
        return `시작 ${hours}시간 ${minutes % 60}분 전`;
    }
    return `시작 ${minutes}분 전`;
});

// 히어로 클릭 — 내 운행/추천 상세로 이동
const openHero = () => {
    const trip = todayTrip.value;
    if (trip) {
        router.push({ name: 'order-detail', params: { id: trip.id } });
        return;
    }
    const rec = topRec.value;
    if (!rec) {
        return;
    }

    // 행동 신호 — 추천 히어로 클릭 (내 다음 운행이 아닌 '오늘 받을 추천'만)
    if (rec.kind === 'set') {
        trackClick({
            orderId: rec.set.firstOrderId,
            scope: 'home',
            section: 'hero',
            meta: { group: true },
        });
    } else {
        trackClick({
            orderId: rec.order.id,
            scope: 'home',
            section: 'hero',
        });
    }

    if (rec.kind === 'set') {
        if (rec.set.firstOrderId) {
            router.push({ name: 'order-detail', params: { id: rec.set.firstOrderId } });
        }
        return;
    }
    router.push({ name: 'order-detail', params: { id: rec.order.id } });
};

// 히어로가 '추천'으로 채워지면 노출 신호 — 오늘의 운행(확정)은 추천이 아니므로 제외
watch([todayTrip, topRec], ([trip, rec]) => {
    if (trip || !rec) {
        return;
    }
    if (rec.kind === 'set') {
        trackImpression({
            orderId: rec.set.firstOrderId,
            scope: 'home',
            section: 'hero',
            meta: { group: true },
        });
    } else {
        trackImpression({
            orderId: rec.order.id,
            scope: 'home',
            section: 'hero',
        });
    }
}, { immediate: true });

// 드라이버 홈 데이터를 아직 안 불렀고 조회 간격이 지났을 때만 조용히 갱신한다
// (로딩 중·10초 내 재조회는 생략해 중복 호출을 막는다)
const maybeRefreshDriverData = () => {
    if (isDriver.value && !loading.value && Date.now() - lastLoadedAt > HOME_REFRESH_MIN_GAP_MS) {
        load(true);
    }
};

// 사용자 정보가 늦게 채워져(첫 실행 fetchMe 지연 등) 마운트 시점엔 isDriver가 false였다면,
// 하이드레이션이 끝나 isDriver가 true가 되는 순간 카운트다운·히어로 타이머와 데이터 로드를 시작한다.
watch(isDriver, (isDriverNow) => {
    if (!isDriverNow) {
        return;
    }
    startClaimTimers();
    startHeroTimer();
    maybeRefreshDriverData();
});

onMounted(() => {
    if (!isDriver.value) {
        // 등록자 홈은 기사 데이터를 로드하지 않는다
        loading.value = false;
        return;
    }

    load();
    startClaimTimers();
    startHeroTimer();
});

// keep-alive 복귀 시 조용히 갱신 — "불러오는 중" 화면 없이 기존 내용을 유지한다
// (짧은 탭 왕복에서는 직전 조회 10초 안이면 재조회를 생략해 불필요한 호출을 줄인다)
onActivated(() => {
    maybeRefreshDriverData();
    startClaimTimers();
    startHeroTimer();
});

// 화면을 벗어나면 불필요한 폴링·카운트다운을 멈춘다
onDeactivated(() => {
    stopClaimTimers();
    stopHeroTimer();
});

onBeforeUnmount(() => {
    stopClaimTimers();
    stopHeroTimer();
});
</script>

<template>
    <div class="home-page page-shell">
        <!-- 등록자(Customer) 홈 — 운행 등록·승인·정산 관리.
             인증 사용자 확인 전에는 아무 홈도 그리지 않는다 — 기사 계정에 등록자 홈이
             잠깐 노출되는 플래시를 막고, 확인된 뒤에만 역할에 맞는 홈을 보여준다. -->
        <CustomerHome v-if="auth.user && !isDriver" />
        <div v-else-if="!auth.user" aria-hidden="true" />

        <!-- 로딩 스켈레톤 — 실제 레이아웃과 동일한 골격을 그려 레이아웃 밀림(CLS)을 막는다 -->
        <div v-else-if="loading" class="home-skeleton" aria-hidden="true">
            <div class="home-skeleton__hero">
                <div class="sk-line sk-line--sm" style="width: 84px;" />
                <div class="sk-line sk-line--lg" style="width: 72%;" />
                <div class="sk-line sk-line--md" style="width: 48%;" />
                <div class="sk-line sk-line--cta" />
            </div>

            <div class="home-skeleton__section">
                <div class="sk-line sk-line--title" />
                <div class="sk-line sk-line--md" style="width: 100%;" />
                <div class="sk-line sk-line--md" style="width: 100%;" />
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
            <!-- 히어로 — 오늘 받을 운행 퀘스트 (내 다음 운행이 있으면 그 운행, 없으면 최우선 추천) -->
            <section
                v-if="heroOrder"
                class="home-hero"
                role="button"
                tabindex="0"
                @click="openHero"
                @keydown.enter="openHero"
            >
                <div class="home-hero__head">
                    <span class="home-hero__eyebrow">
                        <BaseIcon class="home-hero__quest-icon" name="flash" :size="11" />
                        {{ heroEyebrow }}
                    </span>
                    <span v-if="heroRemainText" class="home-hero__remain">{{ heroRemainText }}</span>
                </div>
                <h1 class="home-hero__route">{{ heroRoute }}</h1>
                <div class="home-hero__meta">
                    <span>{{ heroDateText }} {{ heroOrder.time }}</span>
                    <span v-if="heroOrder.flightNumber" class="home-hero__flight">
                        <BaseIcon name="airplane" :size="12" />
                        {{ heroOrder.flightNumber }}
                    </span>
                </div>
                <div v-if="heroOrder.tags?.length" class="home-hero__tags">
                    <span v-for="tag in heroOrder.tags" :key="tag" class="home-hero__tag">{{ tag }}</span>
                </div>
                <div class="home-hero__foot">
                    <span class="home-hero__amount">
                        <span class="home-hero__reward">보상</span>
                        <BaseIcon class="home-hero__coin" name="coin" :size="18" />
                        {{ heroAmount }}
                    </span>
                    <span class="home-hero__cta">
                        {{ heroCtaLabel }}
                        <BaseIcon name="arrow-forward" :size="15" />
                    </span>
                </div>
            </section>

            <!-- 추천이 없으면 — 매칭 설정 유도 -->
            <section v-else class="home-hero home-hero--empty">
                <span class="home-hero__eyebrow">오늘 받을 운행</span>
                <h1 class="home-hero__route">오늘 받을 운행이<br />아직 없어요</h1>
                <p class="home-hero__desc">
                    {{ hasActivePreference ? '운행이 등록되면 여기에 추천해 드려요' : '매칭 설정을 등록해 두면 조건에 맞는 운행을 추천해 드려요' }}
                </p>
                <button
                    v-if="!hasActivePreference"
                    type="button"
                    class="home-hero__cta home-hero__cta--btn"
                    @click="router.push({ name: 'market' })"
                >
                    매칭 설정하기
                    <BaseIcon name="arrow-forward" :size="15" />
                </button>
            </section>

            <!-- 추천 탭 — 단일(기본) / 왕복 / 셋트 -->
            <div class="home-tabs" role="tablist" aria-label="추천 목록 보기">
                <button
                    v-for="tab in HOME_TABS"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    class="home-tab-chip"
                    :class="{ 'home-tab-chip--active': homeTab === tab.key }"
                    :aria-selected="homeTab === tab.key"
                    @click="pickHomeTab(tab.key)"
                >
                    {{ tab.label }}
                    <em class="home-tab-chip__count">{{ tabCounts[tab.key] }}</em>
                </button>
            </div>

            <!-- 왕복 추천 — 하차지와 같은 구·같은 공항에서 이어지는 양방향(왕복) 연결 운행 -->
            <UiSection v-if="showChains" class="home-block--rec">
                <p class="home-rec__hint">
                    {{ currentTrips.length ? '하차지와 같은 구·같은 공항에서 이어지는 복귀 노선이에요' : '공항으로 가고 오는 왕복 노선이에요' }}
                </p>
                <!-- 왕복 노선별 그룹 — 표시 순서대로 추천1 → 추천2 → 추천3 번호를 붙인다 -->
                <div class="home-rec-list">
                    <div v-for="(chain, ci) in displayChains" :key="`chain-${ci}`" class="home-rec-group">
                        <div class="home-rec-group__head">
                            <div v-if="chain.rankLabel" class="home-rec-group__rank">{{ chain.rankLabel }}</div>
                            <!-- 일괄요청중 — 남은 시간과 승인/거절 개수 집계 (일괄요청 버튼 앞) -->
                            <div v-if="chain.claimed && chainStatus(chain)" class="home-rec-group__meta">
                                <span v-if="chainStatus(chain).remainSeconds != null" class="home-rec-group__stat home-rec-group__stat--remain">
                                    <BaseIcon name="history" :size="12" />
                                    남은 {{ chainStatus(chain).remainSeconds }}초
                                </span>
                                <span class="home-rec-group__stat home-rec-group__stat--ok">
                                    <BaseIcon name="check" :size="12" />
                                    승인 {{ chainStatus(chain).approved }}
                                </span>
                                <span class="home-rec-group__stat home-rec-group__stat--no">
                                    <BaseIcon name="close" :size="12" />
                                    거절 {{ chainStatus(chain).rejected }}
                                </span>
                            </div>
                            <!-- 일괄요청 버튼 — 보냈으면 '일괄요청중'으로 남고, 클릭 시 내 마켓 요청(보냄)으로 이동 -->
                            <button
                                v-if="chain.rankLabel === '추천1' || chain.claimed"
                                type="button"
                                class="home-rec-group__claim"
                                :class="{ 'home-rec-group__claim--sent': chain.claimed }"
                                :disabled="batchClaiming"
                                @click="chain.claimed ? goSentRequests() : batchClaimChain(chain)"
                            >
                                <BaseIcon name="send" :size="14" />
                                {{ chain.claimed ? '일괄요청중' : '일괄요청' }}
                            </button>
                        </div>
                        <div class="home-rec order-grid">
                            <OrderCard
                                v-for="order in chain.legs"
                                :key="order.key"
                                :order="order"
                                :show-match-reasons
                                :tracking="{ scope: 'home', section: 'chain', rank: ci + 1 }"
                            />
                        </div>
                    </div>
                </div>
                <button
                    v-if="chainHasMore"
                    type="button"
                    class="home-more"
                    @click="showMoreChains"
                >
                    더보기
                </button>
            </UiSection>

            <!-- 단일 추천 — 연결고리가 아닌 개별 운행 (매칭 설정·운행 이력 기반) -->
            <UiSection v-if="showSingles" class="home-block--rec">
                <div class="home-rec-list">
                    <OrderCard
                        v-for="(order, si) in singleRecs.slice(0, singleShow)"
                        :key="order.key"
                        :order="order"
                        :show-match-reasons
                        :tracking="{ scope: 'home', section: 'single', rank: si + 1 }"
                    />
                </div>
                <button
                    v-if="singleHasMore"
                    type="button"
                    class="home-more"
                    @click="showMoreSingles"
                >
                    더보기
                </button>
            </UiSection>

            <!-- 셋트 추천 — 묶음 운행 카드 (한 다리라도 매칭되면 전체 일정이 포함된다) -->
            <UiSection v-if="showSets" class="home-block--rec">
                <div class="home-rec-list">
                    <SetGroupCard
                        v-for="(set, si) in setRecs.slice(0, setShow)"
                        :key="set.key"
                        :set="set"
                        :tracking="{ scope: 'home', section: 'set', rank: si + 1 }"
                    />
                </div>
                <button
                    v-if="setHasMore"
                    type="button"
                    class="home-more"
                    @click="showMoreSets"
                >
                    더보기
                </button>
            </UiSection>

            <!-- 단일 탭 — 단일 추천이 아직 없으면 안내 -->
            <EmptyState
                v-if="homeTab === 'single' && !showSingles"
                class="home-block--rec"
                icon="inbox"
                title="단일 추천이 아직 없어요"
                hint="조건에 맞는 운행이 등록되면 여기에 추천해 드려요"
            />
            <!-- 셋트 탭 — 셋트 추천이 아직 없으면 안내 -->
            <EmptyState
                v-if="homeTab === 'set' && !showSets"
                class="home-block--rec"
                icon="bag"
                title="셋트 추천이 아직 없어요"
                hint="묶음 일정이 등록되면 여기에 추천해 드려요"
            />
            <!-- 왕복 탭 — 왕복 추천이 아직 없으면 안내 -->
            <EmptyState
                v-if="homeTab === 'chain' && !showChains"
                class="home-block--rec"
                icon="options"
                title="왕복 추천이 아직 없어요"
                hint="하차지와 이어지는 복귀 노선이 등록되면 여기에 추천해 드려요"
            />

            <!-- 빠른 메뉴 -->
            <UiSection class="home-block--menu" title="빠른 메뉴">
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
    /* 폭·여백은 공용 .page-shell이 담당 — 페이지 고유 레이아웃만 남긴다 */
    padding-bottom: 24px;
    display: flex;
    flex-direction: column;
}
/* 홈 세로 순서 — 히어로 → 탭 → 추천 목록 → 빠른 메뉴 */
.home-hero {
    order: 1;
}
.home-tabs {
    order: 2;
}
.home-block--rec {
    order: 3;
}
.home-block--menu {
    order: 4;
}

/* ── 홈 로딩 스켈레톤 — 실제 레이아웃과 동일한 골격(CLS 방지) ── */
.home-skeleton {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* 홈 전용 스켈레톤 변형 — 카드/라인/칩 기본·shimmer는 base.css 전역 정의를 사용한다 */
.sk-line--title { width: 90px; height: 16px; margin-bottom: 10px; }
.sk-line--cta { height: 46px; border-radius: 13px; }

.home-skeleton__hero {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 24px 20px;
    border: 1px solid var(--border);
    border-radius: 18px;
    background: var(--surface);
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

/* ── 히어로 — 오늘 받을 운행 (내 다음 운행 또는 최우선 추천, 상단 시작은 공용 기준) ── */
.home-hero {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 24px 22px 20px;
    border: 1px solid var(--border);
    border-radius: 18px;
    background: var(--surface);
    cursor: pointer;
}
.home-hero__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.home-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    color: var(--brand);
    letter-spacing: 0.04em;
}
/* 퀘스트 느낌 — 눈썹의 깃발·번개 아이콘 */
.home-hero__quest-icon {
    color: var(--brand);
}
/* 보상 — 금액 앞의 작은 라벨 (퀘스트 보상처럼) */
.home-hero__reward {
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.02em;
    margin-right: 1px;
}
.home-hero__remain {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    /* 운행 시작까지 남은 시간이 갱신될 때 우측 라벨 폭이 흔들리지 않도록 */
    font-variant-numeric: tabular-nums;
}
.home-hero__route {
    margin: 6px 0 0;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.32;
    letter-spacing: -0.4px;
    color: var(--text);
    word-break: keep-all;
}
.home-hero__meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 12px;
}
.home-hero__flight {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-muted);
    font-weight: 600;
}
.home-hero__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}
.home-hero__tag {
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 10px;
    white-space: nowrap;
}
.home-hero__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
    padding-top: 13px;
    border-top: 1px solid var(--border);
}
.home-hero__amount {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -0.3px;
    color: var(--text);
}
.home-hero__coin {
    flex-shrink: 0;
    filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.18));
}
.home-hero__cta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 10px 16px;
    border-radius: 10px;
    background: var(--text);
    color: var(--bg);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
html.dark .home-hero__cta {
    background: var(--brand);
    color: #07120e;
}
/* 빈 상태 — 추천이 없을 때 */
.home-hero--empty {
    cursor: default;
}
.home-hero--empty .home-hero__route {
    font-size: 18px;
    font-weight: 700;
}
.home-hero__desc {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 12px;
}
.home-hero--empty .home-hero__cta {
    align-self: flex-start;
    margin-top: 12px;
    border: 0;
    font-family: inherit;
    cursor: pointer;
}

.home-rec__hint {
    margin: -4px 0 10px;
    font-size: 11px;
    color: var(--text-muted);
}

/* 강력추천 왕복 노선 그룹 — 각 체인을 나눠 보여준다 */
.home-rec-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

/* 더보기 — 추천이 20개 넘으면 목록 아래에서 이어서 본다 */
.home-more {
    display: block;
    min-width: 160px;
    margin: 16px auto 0;
    padding: 11px 18px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text);
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}
@media (hover: hover) {
    .home-more:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}
.home-rec-group__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
/* 추천N 랭크 — 단순 라벨 (조건%·근거 팝오버는 사용하지 않음) */
.home-rec-group__rank {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}
/* 추천1 체인 — 등록자들에게 일괄로 가져오기 요청 */
.home-rec-group__claim {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border: 0;
    border-radius: 999px;
    background: var(--brand);
    /* 라이트 brand(#36adff)·다크 brand(#63e2b7) 모두 밝은 색 — 흰 글자 대비가 약해
       코드베이스의 다른 커스텀 brand 버튼(settle-cta·actions-btn--ok 등)과 동일한 어두운 글자를 쓴다 */
    color: #07120e;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.12s ease, transform 0.12s ease;
}
.home-rec-group__claim:disabled {
    opacity: 0.5;
    cursor: default;
}
.home-rec-group__claim:hover:not(:disabled) {
    transform: translateY(-1px);
}
.home-rec-group__claim:active:not(:disabled) {
    transform: translateY(0);
}

/* 일괄요청중 — 요청을 보낸 체인 (비활성 느낌 + 클릭 시 내 마켓으로 이동) */
.home-rec-group__claim--sent {
    background: color-mix(in srgb, var(--status-acceptance-pending) 14%, transparent);
    color: var(--status-acceptance-pending);
}

html.dark .home-rec-group__claim--sent {
    color: var(--status-acceptance-pending);
}

.home-rec-group__claim--sent:disabled {
    opacity: 1;
    cursor: pointer;
}

.home-rec-group__claim--sent:hover:not(:disabled) {
    transform: translateY(-1px);
}

/* 일괄요청중 — 남은 시간/승인·거절 집계 칩 (헤드 행에서 일괄요청 버튼 앞) */
.home-rec-group__meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
    margin-left: auto;
}
.home-rec-group__stat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    /* 남은 초가 매초 바뀌어도 숫자 폭이 고정되도록 — 칩이 좌우로 흔들리지 않게 */
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.home-rec-group__stat--remain {
    background: color-mix(in srgb, var(--status-acceptance-pending) 14%, transparent);
    color: var(--status-acceptance-pending);
}
.home-rec-group__stat--ok {
    background: color-mix(in srgb, var(--status-accepted) 12%, transparent);
    color: var(--status-accepted);
}
.home-rec-group__stat--no {
    background: color-mix(in srgb, var(--status-cancelled) 12%, transparent);
    color: var(--status-cancelled);
}

/* 강력추천 카드 그리드 — 다른 운행 목록과 동일한 카드 */
.home-rec {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--card-gap);
    margin-top: 4px;
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

/* ── 가독성 — 섹션 제목·간격 (UiSection 공통 스타일 재정의) ── */
.home-page :deep(.ui-section) {
    margin-top: 26px;
}
/* 추천 블록은 칩 탭 바로 아래에서 시작 — 간격은 공용 --chips-gap(칩 탭 margin-bottom)이 담당한다 */
.home-page :deep(.home-block--rec) {
    margin-top: 0;
}
.home-page :deep(.ui-section__title) {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: -0.2px;
}
</style>

<!-- 홈 추천 탭(단일/왕복/셋트) 칩 — 추천 탭은 scoped 적용이 안 되는 전역 스타일 사용 -->
<style>
/* ── 추천 탭 — 단일(기본) / 왕복 / 셋트 ── */
.home-tabs {
    display: flex;
    gap: 6px;
    margin-top: 16px;
    margin-bottom: var(--chips-gap);
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.home-tabs::-webkit-scrollbar {
    display: none;
}
.home-tab-chip {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}
/* 건수 — 칩 안 작은 숫자 배지 */
.home-tab-chip__count {
    font-style: normal;
    padding: 1px 5px;
    min-width: 18px;
    text-align: center;
    border-radius: 999px;
    background: color-mix(in srgb, var(--text-muted) 12%, transparent);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 600;
    line-height: 16px;
}
.home-tab-chip:hover {
    border-color: color-mix(in srgb, var(--brand) 40%, transparent);
    color: var(--brand);
}
.home-tab-chip--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
    font-weight: 700;
}
/* 활성 칩 건수 — brand 채움에는 어두운 글자 표준 적용 */
.home-tab-chip--active .home-tab-chip__count {
    background: var(--brand);
    color: #07120e;
}
</style>
