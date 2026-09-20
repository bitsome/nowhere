<script setup>
import { computed, h, onActivated, onBeforeUnmount, onDeactivated, onMounted, ref, watch } from 'vue';
import { useNotification } from 'naive-ui';
import { useRouter } from 'vue-router';
import { apiOrders, apiReturnRoutes } from '../api/orders';
import { apiMatchPreferences } from '../api/match';
import { apiMyVehicles } from '../api/driver';
import { getApiErrorMessage } from '../api/client';
import { useUiStore } from '../stores/ui';
import { useAuthStore } from '../stores/auth';
import OrderCard from '../components/orders/OrderCard.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import QuickMatchPanel from '../components/orders/QuickMatchPanel.vue';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';
import {
    CITY_OPTIONS,
    detailSelectOptions,
    districtSelectOptions,
    locationParam,
} from '../utils/locations';

defineOptions({ name: 'MarketView' });

const ui = useUiStore();
const notification = useNotification();
const router = useRouter();
const auth = useAuthStore();

// 빠른매칭 퀵 — 기사만 사용하며, 하단 네비 위 플로팅 버튼으로 패널을 연다
const isDriver = computed(() => auth.user?.role === 'Driver');
const quickOpen = ref(false);

// '내 조건' 보기 — 빠른매칭(간단 필터) 조건에 맞는 운행만 마켓 목록에 보여준다.
// (빠른매칭 패널에는 카드를 띄우지 않고, 이 목록이 결과를 담당한다)
const matchedOnly = ref(false);

const orders = ref([]);
const pagination = ref(null);

// 필터 적용 줄 우측 건수 — 현재 조건에 맞는 전체 운행 수 (빠른매칭 보기에서는 매칭 전체 수)
const resultCountText = computed(() => {
    const total = pagination.value?.total;

    return total === undefined || total === null ? '' : `${total.toLocaleString()}건`;
});

// 빠른매칭 보기 칩 문구 — '빠른매칭'이라는 말 대신 실제로 고른 조건(시간대·지역·차량 등)을 그대로 보여준다
const matchedPrefs = ref([]);
const matchedVehicles = ref([]);
const SERVICE_NAME = { pickup: '픽업', sending: '샌딩', point: '시내', landing: '랜딩' };

const refreshMatchedLabel = async () => {
    if (!isDriver.value) {
        return;
    }
    const [prefs, cars] = await Promise.allSettled([apiMatchPreferences(), apiMyVehicles()]);

    if (prefs.status === 'fulfilled') {
        matchedPrefs.value = prefs.value.data?.data ?? [];
    }
    if (cars.status === 'fulfilled') {
        matchedVehicles.value = cars.value.data?.data ?? [];
    }
};

const matchedSummaryText = computed(() => {
    const actives = matchedPrefs.value.filter((p) => p.is_active);

    if (actives.length === 0) {
        return '빠른매칭';
    }
    // 차량은 등록 '이름'이 아니라 차종으로 보여준다 (예: '내 그랜저' → '그랜저')
    const carType = new Map((matchedVehicles.value ?? []).map((car) => [car.id, car.type || car.name || '']));

    return actives.map((p) => {
        const parts = [];
        if (p.service_type) {
            parts.push(SERVICE_NAME[p.service_type] ?? p.service_type);
        }
        const s = p.start_time;
        const e = p.end_time;

        if (s && e) {
            parts.push(s > e ? `야간 ${s}–${e}` : `${s}–${e}`);
        } else if (!s && !e) {
            parts.push('종일');
        }
        if (p.area) {
            parts.push(p.area);
        }
        const car = p.vehicle_id ? carType.get(p.vehicle_id) : '';

        if (car) {
            parts.push(car);
        }

        return parts.length ? parts.join(' · ') : '전체 운행';
    }).join(' / ');
});
const loading = ref(true);
const error = ref('');
const filterOpen = ref(false);
const searchOpen = ref(false);

// 노선 검색 적용/해제 — 고객명은 검색하지 않고 출발/도착 노선만 매칭한다
const submitSearch = () => {
    searchOpen.value = false;
    handleFilterChange();
};

const clearSearch = () => {
    search.value = '';
    searchOpen.value = false;
    handleFilterChange();
};

// 왕복 노선 추천 — 내가 맡은 운행의 하차지 근처에서 시작하는 운행 (CJ 더운반/uber Freight 리턴 로드 개념)
const returnRoutes = ref([]);
const returnRouteKeys = computed(() => new Set(returnRoutes.value.map((o) => o.key)));

const applyFilter = () => {
    filterOpen.value = false;
    handleFilterChange();
};

// kind에 따라 분류 — 셋트·왕복 추천 운행은 전용 섹션에 이미 노출되므로 전체 목록에서 제외(중복 방지)
const singleRows = computed(() => orders.value.filter((o) => o.kind !== 'set' && !returnRouteKeys.value.has(o.key)));
const setRows = computed(() => orders.value.filter((o) => o.kind === 'set'));

// 찜 토글 반영 — 목록 행의 상태를 서버 응답값으로 맞춘다 (다음 조회 전까지 유지)
const onFavoriteChanged = (orderId, favorited) => {
    const row = orders.value.find((o) => o.id === orderId);

    if (row) {
        row.is_favorited = favorited;
    }
};
const serviceType = ref('');
const date = ref('');
const departureCity = ref('');
const departureDistrict = ref('');
const departureDetail = ref('');
const arrivalCity = ref('');
const arrivalDistrict = ref('');
const arrivalDetail = ref('');
const vehicleType = ref('');
const vehicleCapacity = ref('');
const timeRange = ref('');
const minAmount = ref(null);
const maxAmount = ref(null);
const minPassengers = ref(null);
const sort = ref('latest');
const quick = ref('');
const search = ref('');
const page = ref(1);

// '더보기' 한 번에 붙는 운행 수 — 목록은 20건씩만 노출하고, 더보기로 이어 붙인다
const PAGE_SIZE = 20;

// 더보기 진행 중 여부 (버튼 로딩 표시)
const moreLoading = ref(false);

// 더 볼 운행이 남았는지 — 전체 건수보다 불러온 개수가 적으면 '더보기' 버튼을 보여준다
const hasMore = computed(() => {
    const total = pagination.value?.total;

    return total !== undefined && total !== null && orders.value.length < total;
});

// 필터·검색 상태 기억 (재방문 시 유지)
const MARKET_STATE_KEY = 'nowhere:market:filter';
const savedState = (() => {
    try {
        return JSON.parse(localStorage.getItem(MARKET_STATE_KEY) ?? '{}');
    } catch {
        return {};
    }
})();

serviceType.value = savedState.serviceType ?? '';
date.value = savedState.date ? savedState.date.replace(' ', 'T') : '';
departureCity.value = savedState.departureCity ?? '';
departureDistrict.value = savedState.departureDistrict ?? '';
departureDetail.value = savedState.departureDetail ?? '';
arrivalCity.value = savedState.arrivalCity ?? '';
arrivalDistrict.value = savedState.arrivalDistrict ?? '';
arrivalDetail.value = savedState.arrivalDetail ?? '';
vehicleType.value = savedState.vehicleType ?? '';
vehicleCapacity.value = savedState.vehicleCapacity ?? '';
timeRange.value = savedState.timeRange ?? '';
minAmount.value = savedState.minAmount ?? null;
maxAmount.value = savedState.maxAmount ?? null;
minPassengers.value = savedState.minPassengers ?? null;
sort.value = savedState.sort ?? 'latest';
quick.value = savedState.quick ?? '';
search.value = savedState.search ?? '';

// '금액' 퀵 보기는 정렬의 '금액 높은순'과 동일 동작 — 기존 저장값을 정렬로 이전한다
if (savedState.quick === 'amount' && sort.value === 'latest') {
    sort.value = 'amount';
    quick.value = '';
}

const persistState = () => {
    try {
        localStorage.setItem(MARKET_STATE_KEY, JSON.stringify({
            serviceType: serviceType.value,
            date: date.value,
            departureCity: departureCity.value,
            departureDistrict: departureDistrict.value,
            departureDetail: departureDetail.value,
            arrivalCity: arrivalCity.value,
            arrivalDistrict: arrivalDistrict.value,
            arrivalDetail: arrivalDetail.value,
            vehicleType: vehicleType.value,
            vehicleCapacity: vehicleCapacity.value,
            timeRange: timeRange.value,
            minAmount: minAmount.value,
            maxAmount: maxAmount.value,
            minPassengers: minPassengers.value,
            sort: sort.value,
            quick: quick.value,
            search: search.value,
        }));
    } catch {
        /* 저장 실패 무시 */
    }
};

// 모든 필터 초기화
const resetFilters = () => {
    matchedOnly.value = false;
    serviceType.value = '';
    date.value = '';
    departureCity.value = '';
    departureDistrict.value = '';
    departureDetail.value = '';
    arrivalCity.value = '';
    arrivalDistrict.value = '';
    arrivalDetail.value = '';
    vehicleType.value = '';
    vehicleCapacity.value = '';
    timeRange.value = '';
    minAmount.value = null;
    maxAmount.value = null;
    minPassengers.value = null;
    sort.value = 'latest';
    quick.value = '';
    search.value = '';
};

// 빠른 보기 칩 — 신규/임박/긴급/찜 (금액 정렬은 '정렬'에서 선택하므로 중복 제거)
const QUICK_OPTIONS = [
    { label: '신규', value: 'new' },
    { label: '임박', value: 'urgent' },
    { label: '긴급', value: 'priority' },
    { label: '찜한 운행', value: 'favorites' },
];

// 빠른 날짜 칩 — 오늘/내일 (빠른 보기 위에 별도 행)
const DATE_QUICK_OPTIONS = [
    { label: '오늘', value: 'today' },
    { label: '내일', value: 'tomorrow' },
];

// 오늘/내일 퀵 칩은 날짜시간 필드도 함께 맞춰 준다 (오늘=현재 시각, 내일=자정)
const localDateStr = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const handleQuick = (opt) => {
    const next = quick.value === opt.value ? '' : opt.value;
    const wasDateQuick = quick.value === 'today' || quick.value === 'tomorrow';
    quick.value = next;

    if (next === 'today') {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        date.value = `${localDateStr(now)}T${hh}:${mm}`;
    } else if (next === 'tomorrow') {
        date.value = `${localDateStr(new Date(Date.now() + 86400000))}T00:00`;
    } else if (wasDateQuick) {
        // 날짜형 퀵(오늘/내일)을 해제하면 함께 채웠던 날짜시간도 되돌린다
        date.value = '';
    }
};

// 빠른 날짜 '전체' — 날짜시간을 비우고, 날짜형 퀵(오늘/내일)도 함께 해제한다
const handleDateAll = () => {
    if (quick.value === 'today' || quick.value === 'tomorrow') {
        quick.value = '';
    }
    date.value = '';
};

// 빠른 금액 칩 — 5~10만원 범위 선택 (원 단위)
const AMOUNT_QUICK_OPTIONS = [5, 6, 7, 8, 9, 10].map((v) => ({ label: `${v}만원`, value: v * 10000 }));

// 범위 선택: 첫 클릭 = 시작(최소), 두 번째 클릭 = 끝(최대), 같은 칩 재클릭 = 해제
const handleAmountQuick = (v) => {
    const curMin = Number(minAmount.value) || 0;
    const curMax = Number(maxAmount.value) || 0;

    if (curMin === 0 && curMax === 0) {
        minAmount.value = v;

        return;
    }

    if (curMin > 0 && curMax === 0) {
        if (v === curMin) {
            minAmount.value = null;

            return;
        }
        if (v < curMin) {
            minAmount.value = v;
        } else {
            maxAmount.value = v;
        }

        return;
    }

    // 기존 범위 해제 후 새 시작점으로
    minAmount.value = v;
    maxAmount.value = null;
};

// 칩 활성화 — 현재 범위(최소~최대) 안에 들어오면 강조
const isAmountInRange = (v) => {
    const curMin = Number(minAmount.value) || 0;
    const curMax = Number(maxAmount.value) || 0;

    return curMin > 0 && v >= curMin && (curMax === 0 || v <= curMax);
};

// 상단 서비스 유형 칩 — 픽업/샌딩/랜딩 (필터 모달의 '서비스 유형'과 동일 옵션·라벨)
const SERVICE_QUICK_OPTIONS = [
    { label: '픽업', value: 'pickup' },
    { label: '샌딩', value: 'sending' },
    { label: '랜딩', value: 'landing' },
];

const toggleServiceType = (value) => {
    serviceType.value = serviceType.value === value ? '' : value;
    page.value = 1;
    load();
};

// 빠른매칭 패널의 '마켓에서 내 조건 보기' — 드로어를 닫고 마켓을 내 조건에 맞는 운행만 보여준다.
// (상단 '내 조건' 칩은 제거됐으므로, 진입은 이 버튼으로만 · 해제는 적용 중 필터 칩으로)
const showMatchedFromPanel = () => {
    quickOpen.value = false;
    matchedOnly.value = true;
    page.value = 1;
    load();
};

// 활성 필터 개수 (헤더 필터 점·필터 모달 초기화 버튼 표시용)
const activeFilterCount = computed(() =>
    [serviceType.value, date.value, departureCity.value, departureDistrict.value, departureDetail.value, arrivalCity.value, arrivalDistrict.value, arrivalDetail.value, vehicleType.value, vehicleCapacity.value, timeRange.value, minAmount.value, maxAmount.value, minPassengers.value, sort.value !== 'latest' ? sort.value : '', quick.value !== '' ? quick.value : '', search.value, matchedOnly.value ? 'matched' : '']
        .filter(Boolean).length,
);

// 필터 활성 여부를 헤더 필터 버튼 점에 반영
watch(
    activeFilterCount,
    (count) => {
        ui.filterActive = count > 0;
    },
    { immediate: true },
);

// 시(서울/인천 등)가 바뀌면 구·세부 선택을 초기화한다
watch(departureCity, () => {
    departureDistrict.value = '';
    departureDetail.value = '';
});
watch(arrivalCity, () => {
    arrivalDistrict.value = '';
    arrivalDetail.value = '';
});
// 공항 3단 — 공항이 바뀌면 세부(T1/T2, 국내/국제)를 초기화한다
watch(departureDistrict, () => {
    departureDetail.value = '';
});
watch(arrivalDistrict, () => {
    arrivalDetail.value = '';
});
// 차량 2단 — 차종이 바뀌면 인승 선택을 초기화한다
watch(vehicleType, () => {
    vehicleCapacity.value = '';
});

const SERVICE_FILTER_OPTIONS = [
    { label: '전체', value: '' },
    { label: '픽업', value: 'pickup' },
    { label: '샌딩', value: 'sending' },
    { label: '랜딩', value: 'landing' },
];

const SORT_OPTIONS = [
    { label: '등록순', value: 'latest' },
    { label: '서비스순', value: 'date' },
    { label: '금액 높은순', value: 'amount' },
    { label: '금액 낮은순', value: 'amount_asc' },
];

const VEHICLE_OPTIONS = [
    { label: '전체', value: '' },
    { label: '스타리아', value: '스타리아' },
    { label: '카니발', value: '카니발' },
    { label: '그랜드 스타렉스', value: '스타렉스' },
];

// 차량 2단 — 차종 선택 시 인승(7/9/11인승)을 한 번 더 고른다 (실데이터 기준)
const VEHICLE_CAPACITY_OPTIONS = [
    { label: '전체', value: '' },
    { label: '7인승', value: '7인승' },
    { label: '9인승', value: '9인승' },
    { label: '11인승', value: '11인승' },
];

// 시간대 필터 — 오전/오후/야간 (서버 time_range 파라미터와 1:1)
const TIME_RANGE_OPTIONS = [
    { label: '전체', value: '' },
    { label: '오전', value: 'morning' },
    { label: '오후', value: 'afternoon' },
    { label: '야간', value: 'night' },
];

const PASSENGER_OPTIONS = [
    { label: '전체', value: null },
    { label: '1명 이상', value: 1 },
    { label: '4명 이상', value: 4 },
    { label: '7명 이상', value: 7 },
    { label: '9명 이상', value: 9 },
];

// 출발지/도착지 — 시(서울/인천/경기도/공항) → 구 상세 선택
// (CITY_OPTIONS·구 목록·locationParam은 utils/locations.js에서 공용으로 사용)

// 즐겨찾기 도시 — 별표(★)로 토글, 즐겨찾기된 시가 셀렉트 상단에 온다 (기본: 공항)
const FAV_CITY_KEY = 'nowhere:market:favCities';
const favoriteCities = ref([]);
try {
    const savedFav = JSON.parse(localStorage.getItem(FAV_CITY_KEY) ?? 'null');
    favoriteCities.value = Array.isArray(savedFav) ? savedFav : ['airport'];
} catch {
    favoriteCities.value = ['airport'];
}

const persistFavorites = () => {
    try {
        localStorage.setItem(FAV_CITY_KEY, JSON.stringify(favoriteCities.value));
    } catch {
        /* 저장 실패 무시 */
    }
};

const toggleFavorite = (value) => {
    const i = favoriteCities.value.indexOf(value);
    if (i >= 0) {
        favoriteCities.value.splice(i, 1);
    } else {
        favoriteCities.value.push(value);
    }
    persistFavorites();
};

// 즐겨찾기된 시를 앞에, 나머지는 기본 순서로
const cityOptions = computed(() => {
    const favs = CITY_OPTIONS.filter((o) => o.value && favoriteCities.value.includes(o.value));
    const rest = CITY_OPTIONS.filter((o) => !favs.includes(o));
    return [...favs, ...rest];
});

// 셀렉트 옵션 앞 별표 — 클릭 시 즐겨찾기 토글 (옵션 선택은 하지 않음)
const renderCityLabel = (option) => {
    if (!option.value) {
        return option.label;
    }
    const fav = favoriteCities.value.includes(option.value);
    return h('span', { style: 'display:inline-flex;align-items:center;gap:6px' }, [
        h('span', {
            style: `cursor:pointer;color:${fav ? '#f0a500' : 'var(--text-muted)'};font-size: 11px;line-height:1;`,
            title: fav ? '즐겨찾기 해제' : '즐겨찾기 추가',
            onClick: (e) => {
                e.stopPropagation();
                toggleFavorite(option.value);
            },
        }, fav ? '★' : '☆'),
        h('span', {}, option.label),
    ]);
};

// 마켓 목록 조회 파라미터 — 한 번에 20건씩 페이지로 나눠 받는다
const buildListParams = (pageNumber) => {
    const params = { scope: 'market', page: pageNumber, per_page: PAGE_SIZE };

    if (serviceType.value) {
        params.service_type = serviceType.value;
    }
    if (date.value) {
        params.date = date.value.replace('T', ' ');
    }
    const departure = locationParam(departureCity.value, departureDistrict.value, departureDetail.value);
    const arrival = locationParam(arrivalCity.value, arrivalDistrict.value, arrivalDetail.value);
    if (departure) {
        params.departure = departure;
    }
    if (arrival) {
        params.arrival = arrival;
    }
    if (vehicleType.value) {
        params.vehicle_type = vehicleType.value;
    }
    if (vehicleCapacity.value) {
        params.vehicle_capacity = vehicleCapacity.value;
    }
    if (timeRange.value) {
        params.time_range = timeRange.value;
    }
    if (minAmount.value) {
        params.min_amount = minAmount.value;
    }
    if (maxAmount.value) {
        params.max_amount = maxAmount.value;
    }
    if (minPassengers.value) {
        params.min_passengers = minPassengers.value;
    }
    if (sort.value !== 'latest') {
        params.sort = sort.value;
    }
    if (quick.value) {
        params.quick = quick.value;
    }
    if (matchedOnly.value) {
        // '내 조건' — 빠른매칭 조건에 맞는 운행만. 추천(왕복 노선)은 섞지 않는다.
        params.matched = 1;
    }
    if (search.value) {
        params.search = search.value;
    }

    return params;
};

// 중복 행 제거 — 더보기로 이어 붙일 때 셋트가 페이지 경계로 나뉘면 같은 카드가 두 번 보이지 않게 한다
const dedupeRows = (rows) => {
    const seen = new Set();
    const unique = [];

    for (const row of rows) {
        if (!row?.key || seen.has(row.key)) {
            continue;
        }
        seen.add(row.key);
        unique.push(row);
    }

    return unique;
};

const load = async (silent = false) => {
    persistState();

    if (!silent) {
        loading.value = true;
    }

    error.value = '';

    try {
        // 확장(더보기) 중이면 지금까지 본 페이지까지 한 번에 다시 불러와
        // 탭 복귀·폴링 후에도 목록이 20건으로 줄지 않게 스냅샷을 유지한다
        const loadedPages = Math.max(1, page.value);
        const pageTasks = [];

        for (let p = 1; p <= loadedPages; p++) {
            pageTasks.push(apiOrders(buildListParams(p)));
        }

        // 왕복 추천은 참고용이라 목록과 병렬 호출 (빠른매칭·찜 보기에서는 호출하지 않음)
        if (!matchedOnly.value && quick.value !== 'favorites') {
            pageTasks.push(apiReturnRoutes(buildListParams(1)));
        }

        const results = await Promise.allSettled(pageTasks);

        const pageRows = [];
        let meta = null;
        let listFailed = false;

        results.forEach((result, index) => {
            if (result.status === 'rejected') {
                if (index === 0) {
                    listFailed = true;
                }

                return;
            }

            const { data } = result.value;

            if (index < loadedPages) {
                if (index === 0) {
                    notifyNewOrders(data.data);
                    meta = data.meta?.pagination ?? null;
                }
                pageRows.push(...(data.data ?? []));
            } else {
                // 마지막 결과 = 왕복 추천 (빠른매칭이 아닐 때만 존재)
                returnRoutes.value = data.data ?? [];
            }
        });

        if (listFailed) {
            error.value = getApiErrorMessage(results[0].reason, '운행 목록을 불러오지 못했습니다.');
        }

        if (meta) {
            pagination.value = meta;

            // 전체 건수가 줄어 마지막 페이지가 앞으로 당겨지면 확장 범위도 맞춘다
            if (meta.last_page && page.value > meta.last_page) {
                page.value = Math.max(1, meta.last_page);
            }
        }

        orders.value = dedupeRows(pageRows);

        // 빠른매칭 보기 중이면 칩 문구(선택한 조건 요약)도 최신으로 맞춘다
        if (matchedOnly.value && isDriver.value) {
            refreshMatchedLabel();
        }
    } catch (e) {
        error.value = getApiErrorMessage(e, '운행 목록을 불러오지 못했습니다.');
    } finally {
        lastLoadedAt = Date.now();
        loading.value = false;
    }
};

// '더보기' — 다음 20건을 이어서 불러와 목록 아래에 붙인다 (남은 게 없으면 버튼이 사라진다)
const loadMore = async () => {
    if (moreLoading.value || !hasMore.value) {
        return;
    }

    moreLoading.value = true;

    try {
        const nextPage = page.value + 1;
        const { data } = await apiOrders(buildListParams(nextPage));

        pagination.value = data.meta?.pagination ?? pagination.value;
        page.value = nextPage;

        const existing = new Set(orders.value.map((o) => o.key));
        const fresh = (data.data ?? []).filter((row) => row?.key && !existing.has(row.key));

        orders.value = [...orders.value, ...fresh];
    } catch (e) {
        error.value = getApiErrorMessage(e, '더 많은 운행을 불러오지 못했습니다.');
    } finally {
        moreLoading.value = false;
    }
};

// 새 운행 감지 — 첫 페이지, 필터 없이 보는 상태에서만 토스트로 알린다.
let knownTopKey = null;
let baselineDone = false;
let prevKeys = new Set();
const highlightKeys = ref(new Set());

const clearHighlight = (key) => {
    const next = new Set(highlightKeys.value);
    next.delete(key);
    highlightKeys.value = next;
};

const notifyNewOrders = (rows) => {
    if (!baselineDone) {
        baselineDone = true;
        prevKeys = new Set(rows.map((r) => r.key));
        knownTopKey = rows[0]?.key ?? null;
        return;
    }

    if (serviceType.value || date.value || departureCity.value || departureDistrict.value || departureDetail.value || arrivalCity.value || arrivalDistrict.value || arrivalDetail.value || vehicleType.value || minAmount.value || maxAmount.value || minPassengers.value || search.value || page.value > 1 || rows.length === 0) {
        prevKeys = new Set(rows.map((r) => r.key));
        knownTopKey = rows[0]?.key ?? null;
        return;
    }

    const topKey = rows[0]?.key ?? null;
    if (topKey && topKey !== knownTopKey) {
        notification.info({
            title: '새 운행이 도착했어요',
            content: '마켓에 새로운 운행이 등록되었습니다.',
            duration: 4000,
        });
    }
    knownTopKey = topKey;

    // 신규 운행 카드 하이라이트 (이전 목록에 없던 key)
    const newKeys = rows.filter((r) => !prevKeys.has(r.key)).map((r) => r.key);
    if (newKeys.length) {
        const next = new Set(highlightKeys.value);
        newKeys.forEach((key) => next.add(key));
        highlightKeys.value = next;
        newKeys.forEach((key) => setTimeout(() => clearHighlight(key), 6000));
    }
    prevKeys = new Set(rows.map((r) => r.key));
};

const handleFilterChange = () => {
    page.value = 1;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// 태그 검색 — 카드의 태그 칩을 누르면 그 태그로 목록을 다시 검색한다.
const applyTagSearch = (tag) => {
    if (!tag) {
        return;
    }
    matchedOnly.value = false;
    quick.value = '';
    search.value = tag;
    searchOpen.value = false;
    handleFilterChange();
};

// 다른 화면(홈·찜 등)의 태그 칩 클릭 — 마켓은 keep-alive 캐시라 화면 이동만으로 필터가
// 바뀌지 않으므로, 마켓 진입 시점(처음/복귀)에 대기 요청을 소비해 적용한다.
const PENDING_TAG_KEY = 'nowhere:market:pendingTag';
const consumePendingTag = () => {
    let tag = '';

    try {
        tag = localStorage.getItem(PENDING_TAG_KEY) ?? '';
        localStorage.removeItem(PENDING_TAG_KEY);
    } catch {
        /* 저장 실패 무시 */
    }

    applyTagSearch(tag);
};

// 적용 중인 필터 요약 — 칩 클릭 시 해당 필터만 해제하고 목록을 갱신한다
const activeFilterChips = computed(() => {
    const chips = [];
    const push = (label, clear) => chips.push({ label, clear });

    if (matchedOnly.value) {
        push(matchedSummaryText.value, () => {
            matchedOnly.value = false;
            handleFilterChange();
        });
    }
    if (serviceType.value) {
        push(SERVICE_FILTER_OPTIONS.find((o) => o.value === serviceType.value)?.label ?? serviceType.value, () => {
            serviceType.value = '';
            handleFilterChange();
        });
    }
    if (date.value) {
        push(date.value.replace('T', ' '), () => {
            date.value = '';
            handleFilterChange();
        });
    }
    const departure = locationParam(departureCity.value, departureDistrict.value, departureDetail.value);
    if (departure) {
        // API용 와일드카드('%')는 칩 문구에 노출하지 않는다 (예: '출발 인천%공항' → '출발 인천공항')
        push(`출발 ${departure.replace('%', '')}`, () => {
            departureCity.value = '';
            departureDistrict.value = '';
            departureDetail.value = '';
            handleFilterChange();
        });
    }
    const arrival = locationParam(arrivalCity.value, arrivalDistrict.value, arrivalDetail.value);
    if (arrival) {
        push(`도착 ${arrival.replace('%', '')}`, () => {
            arrivalCity.value = '';
            arrivalDistrict.value = '';
            arrivalDetail.value = '';
            handleFilterChange();
        });
    }
    if (vehicleType.value) {
        push(vehicleType.value, () => {
            vehicleType.value = '';
            vehicleCapacity.value = '';
            handleFilterChange();
        });
    }
    if (vehicleCapacity.value) {
        push(vehicleCapacity.value, () => {
            vehicleCapacity.value = '';
            handleFilterChange();
        });
    }
    if (timeRange.value) {
        push(TIME_RANGE_OPTIONS.find((o) => o.value === timeRange.value)?.label ?? timeRange.value, () => {
            timeRange.value = '';
            handleFilterChange();
        });
    }
    if (minAmount.value || maxAmount.value) {
        const v = (n) => Math.round((n ?? 0) / 10000);
        const label = minAmount.value && maxAmount.value
            ? `${v(minAmount.value)}~${v(maxAmount.value)}만원`
            : minAmount.value ? `${v(minAmount.value)}만원~` : `~${v(maxAmount.value)}만원`;
        push(label, () => {
            minAmount.value = null;
            maxAmount.value = null;
            handleFilterChange();
        });
    }
    if (minPassengers.value) {
        push(`${minPassengers.value}명 이상`, () => {
            minPassengers.value = null;
            handleFilterChange();
        });
    }
    if (quick.value && quick.value !== 'today' && quick.value !== 'tomorrow') {
        push(QUICK_OPTIONS.find((o) => o.value === quick.value)?.label ?? quick.value, () => {
            quick.value = '';
            handleFilterChange();
        });
    }
    if (search.value) {
        push(`"${search.value}"`, () => {
            search.value = '';
            handleFilterChange();
        });
    }

    return chips;
});

// 실시간 반영 — 새 운행이 등록되면 화면을 조용히 갱신한다 (30초 폴링, 백그라운드 시 중지)
let pollTimer = null;
// 마지막 목록 조회 시각 — 폴링·탭 복귀·SSE 갱신이 서로 겹쳐 중복 호출되는 것을 막는다
let lastLoadedAt = 0;
const SILENT_REFRESH_MIN_GAP_MS = 10000;
// 조용한 갱신 진행 중 플래그 — 중복 실행을 직렬화한다
let silentRefreshing = false;

const silentRefresh = () => {
    // 탭이 숨겨져 있거나 전체 로딩 중이면 생략 (keep-alive 첫 진입 시 onMounted와 onActivated가 겹치는 것 포함)
    if (document.visibilityState === 'hidden' || loading.value || silentRefreshing) {
        return;
    }
    // 방금 조회했다면 건너뛴다 (30초 폴링 시점 직후 탭 복귀 등 중복 방지)
    if (Date.now() - lastLoadedAt < SILENT_REFRESH_MIN_GAP_MS) {
        return;
    }

    silentRefreshing = true;
    load(true)
        .catch(() => {})
        .finally(() => {
            silentRefreshing = false;
        });
};

// 화면이 보이는 동안에만 30초 폴링을 돌린다 (탭 이동 시 중지·복귀 시 재개)
const startPolling = () => {
    if (pollTimer) {
        return;
    }
    pollTimer = setInterval(silentRefresh, 30000);
};
const stopPolling = () => {
    clearInterval(pollTimer);
    pollTimer = null;
};

const onVisibility = () => {
    if (document.visibilityState === 'visible') {
        silentRefresh();
    }
};

const onSseRefresh = () => {
    silentRefresh();
};

onMounted(() => {
    consumePendingTag();
    load();
    startPolling();
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('app:sse-refresh', onSseRefresh);
});

// keep-alive 복귀 시 조용히 새로고침 (화면 깜빡임 없이 최신 목록 유지 — 직전 조회와 10초 미만이면 생략)
onActivated(() => {
    consumePendingTag();
    startPolling();
    silentRefresh();
});

// 다른 탭으로 벗어나 있으면 폴링을 멈춰 불필요한 재조회를 막는다 (keep-alive 캐시 상태)
onDeactivated(() => {
    stopPolling();
});

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('app:sse-refresh', onSseRefresh);
});

// 헤더 '···' 메뉴의 새로고침 수신
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'refresh') {
            load();
        }
        if (ui.actionName === 'filter') {
            filterOpen.value = true;
        }
    },
);

</script>

<template>
    <div class="page-shell">
        <!-- 운행 유형 칩 — 샌딩/랜딩/픽업 빠른 선택 -->
        <div class="market-filters">
            <button
                v-for="opt in SERVICE_QUICK_OPTIONS"
                :key="opt.value"
                type="button"
                class="market-filters__chip"
                :class="{ 'market-filters__chip--active': serviceType === opt.value }"
                @click="toggleServiceType(opt.value)"
            >
                {{ opt.label }}
            </button>
            <button
                type="button"
                class="market-filters__myorders"
                aria-label="내가 등록한 운행 관리"
                title="내가 등록한 운행"
                @click="router.push({ name: 'my-market' })"
            >
                <BaseIcon name="cart" :size="18" />
            </button>
            <button
                type="button"
                class="market-filters__search"
                aria-label="노선 검색"
                :class="{ 'market-filters__search--active': search }"
                @click="searchOpen = true"
            >
                <BaseIcon name="search" :size="18" />
                <span v-if="search" class="market-filters__search-text">{{ search }}</span>
            </button>
            <!-- 찜한 운행 — 내가 보관한 운행만 모아 보는 별도 화면 -->
            <button
                type="button"
                class="market-filters__favs"
                aria-label="찜한 운행"
                title="찜한 운행"
                @click="router.push({ name: 'order-favorites' })"
            >
                <BaseIcon name="heart" :size="18" />
            </button>
        </div>

        <!-- 적용 중인 필터 요약 — 개별 칩 클릭으로 해제, 우측에 결과 건수·전체 초기화 -->
        <div v-if="activeFilterChips.length" class="market-active">
            <div class="market-active__chips">
                <button
                    v-for="(chip, index) in activeFilterChips"
                    :key="index"
                    type="button"
                    class="market-active__chip"
                    @click="chip.clear()"
                >
                    {{ chip.label }}
                    <BaseIcon name="close" :size="11" />
                </button>
            </div>
            <div class="market-active__side">
                <span v-if="resultCountText" class="market-active__count">{{ resultCountText }}</span>
                <button
                    type="button"
                    class="market-active__reset"
                    aria-label="필터 초기화"
                    title="선택한 필터 모두 해제"
                    @click="resetFilters(); load()"
                >
                    <BaseIcon name="refresh" :size="13" />
                </button>
            </div>
        </div>

        <!-- 필터 모달 -->
        <n-modal v-model:show="filterOpen" preset="card" title="필터" :style="{ maxWidth: '400px' }">
            <div class="filter-body">
                <label class="filter-label">날짜</label>
                <div class="filter-quick">
                    <button
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': !date }"
                        @click="handleDateAll"
                    >
                        전체
                    </button>
                    <button
                        v-for="opt in DATE_QUICK_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': quick === opt.value }"
                        @click="handleQuick(opt)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <input v-model="date" type="datetime-local" class="filter-date" />
                <label class="filter-label">시간대</label>
                <div class="filter-quick">
                    <button
                        v-for="opt in TIME_RANGE_OPTIONS"
                        :key="opt.value || 'all'"
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': timeRange === opt.value }"
                        @click="timeRange = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <label class="filter-label">서비스 유형</label>
                <div class="filter-quick">
                    <button
                        v-for="opt in SERVICE_FILTER_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': serviceType === opt.value }"
                        @click="serviceType = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <label class="filter-label">특별 보기</label>
                <div class="filter-quick">
                    <button
                        v-for="opt in QUICK_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': quick === opt.value }"
                        @click="handleQuick(opt)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <label class="filter-label">출발지</label>
                <div class="filter-loc-row">
                    <n-select v-model:value="departureCity" :options="cityOptions" :render-label="renderCityLabel" size="large" />
                    <n-select
                        v-if="departureCity"
                        v-model:value="departureDistrict"
                        :options="districtSelectOptions(departureCity)"
                        size="large"
                    />
                    <n-select
                        v-if="departureCity === 'airport' && departureDistrict"
                        v-model:value="departureDetail"
                        :options="detailSelectOptions(departureCity, departureDistrict)"
                        size="large"
                    />
                </div>
                <label class="filter-label">도착지</label>
                <div class="filter-loc-row">
                    <n-select v-model:value="arrivalCity" :options="cityOptions" :render-label="renderCityLabel" size="large" />
                    <n-select
                        v-if="arrivalCity"
                        v-model:value="arrivalDistrict"
                        :options="districtSelectOptions(arrivalCity)"
                        size="large"
                    />
                    <n-select
                        v-if="arrivalCity === 'airport' && arrivalDistrict"
                        v-model:value="arrivalDetail"
                        :options="detailSelectOptions(arrivalCity, arrivalDistrict)"
                        size="large"
                    />
                </div>
                <label class="filter-label">차량</label>
                <div class="filter-loc-row">
                    <n-select
                        v-model:value="vehicleType"
                        :options="VEHICLE_OPTIONS"
                        size="large"
                    />
                    <n-select
                        v-if="vehicleType"
                        v-model:value="vehicleCapacity"
                        :options="VEHICLE_CAPACITY_OPTIONS"
                        size="large"
                    />
                </div>
                <label class="filter-label">최소 탑승 인원</label>
                <n-select
                    v-model:value="minPassengers"
                    :options="PASSENGER_OPTIONS"
                    size="large"
                />
                <label class="filter-label">금액</label>
                <div class="filter-quick">
                    <button
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': !minAmount && !maxAmount }"
                        @click="minAmount = null; maxAmount = null"
                    >
                        전체
                    </button>
                    <button
                        v-for="opt in AMOUNT_QUICK_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="filter-quick__chip"
                        :class="{ 'filter-quick__chip--active': isAmountInRange(opt.value) }"
                        @click="handleAmountQuick(opt.value)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <div class="filter-amount-row">
                    <n-input v-model:value="minAmount" type="number" placeholder="최소" clearable size="large" />
                    <span class="filter-amount-sep">~</span>
                    <n-input v-model:value="maxAmount" type="number" placeholder="최대" clearable size="large" />
                </div>
                <label class="filter-label">정렬</label>
                <n-select
                    v-model:value="sort"
                    :options="SORT_OPTIONS"
                    size="large"
                />
            </div>
            <template #footer>
                <div class="filter-footer">
                    <n-button @click="filterOpen = false">취소</n-button>
                    <n-button v-if="activeFilterCount > 0" @click="resetFilters()">초기화</n-button>
                    <n-button type="primary" @click="applyFilter">적용</n-button>
                </div>
            </template>
        </n-modal>

        <!-- 노선 검색 모달 — 출발/도착 노선만 검색 -->
        <n-modal v-model:show="searchOpen" preset="card" title="노선 검색" :style="{ maxWidth: '480px' }">
            <div class="search-modal">
                <n-input
                    v-model:value="search"
                    type="text"
                    placeholder="출발지 · 도착지 노선으로 검색 (예: 인천공항, 강남)"
                    clearable
                    size="large"
                    @keyup.enter="submitSearch"
                    @clear="clearSearch"
                />
                <div class="search-modal__footer">
                    <n-button quaternary @click="clearSearch">초기화</n-button>
                    <n-button type="primary" @click="submitSearch">검색</n-button>
                </div>
            </div>
        </n-modal>

        <n-alert v-if="error" type="error" :show-icon="true" class="market-alert">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="load">다시 시도</n-button>
            </template>
        </n-alert>

        <n-spin :show="false" class="market-body">
            <!-- 로딩 스켈레톤 -->
            <div v-if="loading" class="order-grid">
                <OrderCardSkeleton v-for="n in 6" :key="n" />
            </div>

            <EmptyState
                v-else-if="orders.length === 0"
                icon="search"
                :title="quick === 'favorites'
                    ? '찜한 운행이 없습니다'
                    : matchedOnly ? '조건에 맞는 운행이 아직 없습니다' : '가져올 수 있는 운행이 없습니다'"
                :hint="quick === 'favorites'
                    ? '마음에 드는 운행의 하트를 눌러 모아두면 여기서 다시 확인할 수 있어요'
                    : matchedOnly
                        ? '매칭 조건을 넓히거나 잠시 후 다시 확인해 주세요'
                        : '필터를 줄이거나 잠시 후 다시 확인해 주세요'"
            />
            <template v-else>
                <!-- 연결 운행 — 내가 맡은 운행의 하차지에서 이어지는 운행 (공차 복귀 절감) -->
                <div v-if="quick !== 'favorites' && returnRoutes.length" class="market-section">
                    <div class="order-grid">
                        <OrderCard
                            v-for="(order, ri) in returnRoutes"
                            :key="order.key"
                            :order="order"
                            :highlight="highlightKeys.has(order.key)"
                            :favoriteable="true"
                            :favorited="Boolean(order.is_favorited)"
                            :tracking="{ scope: 'market', section: 'return', rank: ri + 1 }"
                            @favorite-change="onFavoriteChanged"
                            @tag-search="applyTagSearch"
                        />
                    </div>
                </div>

                <!-- 전체 운행 -->
                <div class="market-section">
                    <div class="order-grid">
                        <SetGroupCard
                            v-for="(order, si) in setRows"
                            :key="order.key"
                            :set="order"
                            :highlight="highlightKeys.has(order.key)"
                            :favoriteable="true"
                            :favorited="Boolean(order.is_favorited)"
                            :tracking="{ scope: 'market', section: 'set', rank: si + 1 }"
                            @favorite-change="onFavoriteChanged"
                            @tag-search="applyTagSearch"
                        />
                        <OrderCard
                            v-for="(order, oi) in singleRows"
                            :key="order.key"
                            :order="order"
                            :highlight="highlightKeys.has(order.key)"
                            :show-match-reasons="true"
                            :favoriteable="true"
                            :favorited="Boolean(order.is_favorited)"
                            :tracking="{ scope: 'market', section: 'list', rank: oi + 1 }"
                            @favorite-change="onFavoriteChanged"
                            @tag-search="applyTagSearch"
                        />
                    </div>
                </div>
            </template>

            <div class="market-more-wrap">
                <button
                    v-if="hasMore"
                    type="button"
                    class="market-more"
                    :disabled="moreLoading"
                    @click="loadMore"
                >
                    {{ moreLoading ? '불러오는 중…' : '더보기' }}
                </button>
                <p v-else-if="orders.length" class="market-more__end">모든 운행을 확인했어요</p>
            </div>
        </n-spin>

        <!-- 빠른매칭 퀵 — 하단 네비 바로 위 우측에 떠 있는 원형 버튼 -->
        <button
            v-if="isDriver"
            type="button"
            class="market-fab"
            aria-label="빠른매칭"
            title="빠른매칭"
            @click="quickOpen = true"
        >
            <BaseIcon name="flash" :size="22" />
        </button>

        <!-- 빠른매칭 패널 — 상태·조건 관리·나에게 매칭된 운행 -->
        <n-drawer v-model:show="quickOpen" placement="bottom" :height="'80vh'" :auto-focus="false">
            <div class="qm-drawer">
                <div class="qm-drawer__head">
                    <strong>빠른매칭</strong>
                    <button type="button" class="qm-drawer__close" aria-label="닫기" @click="quickOpen = false">
                        <BaseIcon name="close" :size="16" />
                    </button>
                </div>
                <div class="qm-drawer__body">
                    <QuickMatchPanel @changed="load(true)" @apply="showMatchedFromPanel" />
                </div>
            </div>
        </n-drawer>
    </div>
</template>
<style scoped>
.page-head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.page-head__desc {
    margin: 0;
    color: var(--text-muted);
    font-size: 11px;
}

/* 필터 모달 — 빠른 보기 칩 */
.filter-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.filter-quick__chip {
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

/* hover — 데스크톱에서만. 터치 기기는 탭 후 남는 포커스로 hover가 고정돼 '선택된 것처럼' 보이므로 제외 */
@media (hover: hover) {
    .filter-quick__chip:hover {
        border-color: var(--brand);
    }
}

.filter-quick__chip--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
}

.filter-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.filter-amount-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-loc-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-loc-row .n-select {
    flex: 1;
    min-width: 100px;
}

.filter-amount-row .n-input {
    flex: 1;
    min-width: 0;
}

.filter-amount-sep {
    color: var(--text-muted);
    font-size: 11px;
    flex-shrink: 0;
}

.filter-date {
    flex: 1;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    font-size: 11px;
    outline: none;
}

.filter-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}

.filter-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.market-alert {
    margin-bottom: 16px;
}

.market-body {
    display: block;
    min-height: 200px;
}

.order-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--card-gap);
}

.market-more-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin-top: 24px;
}
.market-more {
    min-width: 160px;
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
.market-more:disabled {
    opacity: 0.6;
    cursor: default;
}
@media (hover: hover) {
    .market-more:hover:not(:disabled) {
        border-color: var(--brand);
        color: var(--brand);
    }
}
.market-more__end {
    margin: 0;
    color: var(--text-muted);
    font-size: 11px;
}

/* ── 빠른매칭 플로팅 버튼 — 하단 네비 바로 위 우측 ── */
.market-fab {
    position: fixed;
    right: 16px;
    bottom: calc(76px + env(safe-area-inset-bottom));
    z-index: 90;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border: 0;
    border-radius: 50%;
    background: var(--brand);
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.market-fab:active {
    transform: scale(0.94);
}
html.dark .market-fab {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
}

/* ── 필터 칩 행 ── */
.market-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    /* 칩 행 아래 여백 — 홈/채팅 등과 동일하게 공용 토큰 사용 */
    margin-bottom: var(--chips-gap);
}
.market-filters__chip {
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}
.market-filters__chip--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

/* 필터 버튼의 활성 필터 개수 배지 — brand 채움에는 어두운 글자(#07120e) 표준 적용 */
.market-filters__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    margin-left: 5px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--brand);
    color: #07120e;
    font-size: 10px;
    font-weight: 400;
    line-height: 1;
}

.market-filters__chip--reset {
    color: var(--text-muted);
    border-color: var(--border);
}

.market-filters__myorders {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-left: auto;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

@media (hover: hover) {
    .market-filters__myorders:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}

.market-filters__search {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

@media (hover: hover) {
    .market-filters__search:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}

.market-filters__search--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

.market-filters__search-text {
    font-size: 11.5px;
    font-weight: 600;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.market-filters__favs {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

@media (hover: hover) {
    .market-filters__favs:hover {
        border-color: color-mix(in srgb, var(--danger) 45%, var(--border));
        color: var(--danger);
    }
}

/* 적용 중인 필터 요약 — 개별 칩 해제 가능, 우측 결과 건수·전체 초기화 */
.market-active {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 var(--chips-gap);
}
/* 필터 칩 행 아래 요약 칩 — 위로 끌어올려 여백을 작게 */
.market-filters + .market-active {
    margin-top: -4px;
}
.market-active__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1 1 auto;
    min-width: 0;
}
.market-active__side {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    margin-left: auto;
    padding-left: 8px;
}
.market-active__count {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.market-active__reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
}
.market-active__reset:hover {
    color: var(--brand);
    border-color: var(--brand);
}

.market-active__chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border: 1px solid color-mix(in srgb, var(--brand) 32%, transparent);
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    color: var(--brand);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.12s ease, border-color 0.12s ease;
}

@media (hover: hover) {
    .market-active__chip:hover {
        background: color-mix(in srgb, var(--brand) 14%, transparent);
        border-color: var(--brand);
    }
}

.market-active__chip svg {
    width: 11px;
    height: 11px;
    opacity: 0.75;
}

/* ── 노선 검색 모달 ── */
.search-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.search-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* ── 섹션 — 그룹 사이 간격만 관리 (헤더 라벨은 두지 않는다) ── */
.market-section {
    margin-bottom: 16px;
}

</style>

<!-- 빠른매칭 드로어 — body에 텔레포트되므로 전역 스타일 (scoped 적용 안 됨) -->
<style>
.qm-drawer {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.qm-drawer__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px 0;
    flex-shrink: 0;
}
.qm-drawer__head strong {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: -0.3px;
}
.qm-drawer__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
}
.qm-drawer__body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    width: 100%;
    max-width: var(--page-max-width);
    margin: 0 auto;
    padding: 10px 20px 30px;
    box-sizing: border-box;
}
</style>