<script setup>
import { computed, h, onActivated, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useNotification } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { useUiStore } from '../stores/ui';
import OrderCard from '../components/orders/OrderCard.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

defineOptions({ name: 'MarketView' });

const ui = useUiStore();
const notification = useNotification();

const orders = ref([]);
const pagination = ref(null);
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

// 추천(매칭) 운행 — is_matched_to_me 플래그 기준
const recommended = computed(() => orders.value.filter((o) => o.is_matched_to_me && o.kind !== 'set'));

const applyFilter = () => {
    filterOpen.value = false;
    handleFilterChange();
};

// kind에 따라 분류 — 추천(매칭) 운행은 추천 섹션에 이미 노출되므로 전체 목록에서 제외(중복 방지)
const singleRows = computed(() => orders.value.filter((o) => o.kind !== 'set' && !o.is_matched_to_me));
const setRows = computed(() => orders.value.filter((o) => o.kind === 'set'));
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
const minAmount = ref(null);
const maxAmount = ref(null);
const minPassengers = ref(null);
const sort = ref('latest');
const quick = ref('');
const search = ref('');
const page = ref(1);

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
minAmount.value = savedState.minAmount ?? null;
maxAmount.value = savedState.maxAmount ?? null;
minPassengers.value = savedState.minPassengers ?? null;
sort.value = savedState.sort ?? 'latest';
quick.value = savedState.quick ?? '';
search.value = savedState.search ?? '';

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
    minAmount.value = null;
    maxAmount.value = null;
    minPassengers.value = null;
    sort.value = 'latest';
    quick.value = '';
    search.value = '';
};

// 빠른 보기 칩 — 최신/임박/금액/긴급 (서버 quick 파라미터와 1:1)
const QUICK_OPTIONS = [
    { label: '최신', value: 'new' },
    { label: '임박', value: 'urgent' },
    { label: '금액', value: 'amount' },
    { label: '긴급', value: 'priority' },
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

// 상단 서비스 유형 칩 — 샌딩/랜딩/픽업만 상단에서 바로 선택, 나머지는 필터 모달에서
const SERVICE_QUICK_OPTIONS = [
    { label: '샌딩', value: 'sending' },
    { label: '랜딩', value: 'landing' },
    { label: '픽업', value: 'pickup' },
];

const toggleServiceType = (value) => {
    serviceType.value = serviceType.value === value ? '' : value;
    page.value = 1;
    load();
};

// 필터 모달에만 있는 필터(서비스 유형 제외)가 활성화됐는지 — '필터' 칩 하이라이트용
const hasModalFilters = computed(() =>
    [date.value, departureCity.value, departureDistrict.value, departureDetail.value, arrivalCity.value, arrivalDistrict.value, arrivalDetail.value, vehicleType.value, vehicleCapacity.value, minAmount.value, maxAmount.value, minPassengers.value, quick.value !== '' ? quick.value : '', search.value]
        .filter(Boolean).length > 0,
);

// 활성 필터 개수 (초기화 버튼 표시용)
const activeFilterCount = computed(() =>
    [serviceType.value, date.value, departureCity.value, departureDistrict.value, departureDetail.value, arrivalCity.value, arrivalDistrict.value, arrivalDetail.value, vehicleType.value, vehicleCapacity.value, minAmount.value, maxAmount.value, minPassengers.value, sort.value !== 'latest' ? sort.value : '', quick.value !== '' ? quick.value : '', search.value]
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
    { label: '공항샌딩', value: 'sending' },
    { label: '공항랜딩', value: 'landing' },
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

const PASSENGER_OPTIONS = [
    { label: '전체', value: null },
    { label: '1명 이상', value: 1 },
    { label: '4명 이상', value: 4 },
    { label: '7명 이상', value: 7 },
    { label: '9명 이상', value: 9 },
];

// 출발지/도착지 — 시(서울/인천/경기도/공항) → 구 상세 선택
const CITY_OPTIONS = [
    { label: '전체', value: '' },
    { label: '공항', value: 'airport' },
    { label: '서울', value: 'seoul' },
    { label: '인천', value: 'incheon' },
    { label: '경기도', value: 'gyeonggi' },
];

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
            style: `cursor:pointer;color:${fav ? '#f0a500' : 'var(--text-muted)'};font-size:14px;line-height:1;`,
            title: fav ? '즐겨찾기 해제' : '즐겨찾기 추가',
            onClick: (e) => {
                e.stopPropagation();
                toggleFavorite(option.value);
            },
        }, fav ? '★' : '☆'),
        h('span', {}, option.label),
    ]);
};

const CITY_LABELS = { seoul: '서울', incheon: '인천', gyeonggi: '경기도', airport: '공항' };

const SEOUL_DISTRICTS = ['강남구', '강동구', '강북구', '강서구', '관악구', '광진구', '구로구', '금천구', '노원구', '도봉구', '동대문구', '동작구', '마포구', '서대문구', '서초구', '성동구', '성북구', '송파구', '양천구', '영등포구', '용산구', '은평구', '종로구', '중구', '중랑구'];
const INCHEON_DISTRICTS = ['중구', '미추홀구', '연수구', '남동구', '부평구', '계양구', '서구', '동구'];
const GYEONGGI_DISTRICTS = ['수원', '성남', '분당', '판교', '고양', '일산', '부천', '안양', '용인', '화성', '평택', '남양주', '김포', '파주', '의정부', '시흥', '구리', '하남'];
const AIRPORTS = ['인천공항', '김포공항'];

// 시 선택 시 표시할 구 목록 (전체 포함)
const districtSelectOptions = (city) => {
    const list = city === 'seoul' ? SEOUL_DISTRICTS
        : city === 'incheon' ? INCHEON_DISTRICTS
        : city === 'gyeonggi' ? GYEONGGI_DISTRICTS
        : city === 'airport' ? AIRPORTS
        : [];
    return [
        { label: '전체', value: '' },
        ...list.map((d) => ({ label: d, value: d })),
    ];
};

// 공항 3단 세부(구) 목록 — 인천공항: T1/T2, 김포공항: 국내/국제
const detailSelectOptions = (city, district) => {
    if (city !== 'airport') {
        return [];
    }
    const details = district === '인천공항' ? ['T1', 'T2'] : district === '김포공항' ? ['국내', '국제'] : [];
    return [
        { label: '전체', value: '' },
        ...details.map((d) => ({ label: d, value: d })),
    ];
};

// 출발/도착 API 파라미터 — 시/구(세부) 선택에 따라 매칭 문자열 생성
const locationParam = (city, district, detail = '') => {
    if (city === 'airport') {
        if (!district) {
            return '공항';
        }
        if (district === '인천공항') {
            // 인천공항 전체는 '인천공항 T1/T2'·'인천국제공항' 모두 잡도록 와일드카드 사용
            return detail ? `인천공항 ${detail}` : '인천%공항';
        }
        return detail ? `김포공항 ${detail}` : '김포공항';
    }
    if (district) {
        return district;
    }
    return CITY_LABELS[city] ?? '';
};

const load = async (silent = false) => {
    persistState();

    if (!silent) {
        loading.value = true;
    }

    error.value = '';

    try {
        const params = { scope: 'market', page: page.value };

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
        if (search.value) {
            params.search = search.value;
        }

        const { data } = await apiOrders(params);
        notifyNewOrders(data.data);
        orders.value = data.data;
        pagination.value = data.meta.pagination;
    } catch (e) {
        error.value = getApiErrorMessage(e, '운행 목록을 불러오지 못했습니다.');
    } finally {
        loading.value = false;
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

const handlePage = (nextPage) => {
    page.value = nextPage;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// 적용 중인 필터 요약 — 칩 클릭 시 해당 필터만 해제하고 목록을 갱신한다
const activeFilterChips = computed(() => {
    const chips = [];
    const push = (label, clear) => chips.push({ label, clear });

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
        push(`출발 ${departure}`, () => {
            departureCity.value = '';
            departureDistrict.value = '';
            departureDetail.value = '';
            handleFilterChange();
        });
    }
    const arrival = locationParam(arrivalCity.value, arrivalDistrict.value, arrivalDetail.value);
    if (arrival) {
        push(`도착 ${arrival}`, () => {
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

const silentRefresh = () => {
    // 탭이 숨겨져 있는 동안에는 API 호출을 하지 않는다 (SSE/재진입 시 갱신)
    if (document.visibilityState === 'hidden') {
        return;
    }

    load(true).catch(() => {});
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
    load();
    pollTimer = setInterval(silentRefresh, 30000);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('app:sse-refresh', onSseRefresh);
});

// keep-alive 복귀 시 조용히 새로고침 (화면 깜빡임 없이 최신 목록 유지)
onActivated(() => {
    load(true);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
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
    <div>
        <!-- 필터 칩 — 샌딩/랜딩/픽업은 상단에서 바로, 나머지 필터는 모달로 -->
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
                class="market-filters__chip"
                :class="{ 'market-filters__chip--active': hasModalFilters }"
                @click="filterOpen = true"
            >
                필터 {{ hasModalFilters ? '▾' : '' }}
            </button>
            <button
                v-if="activeFilterCount > 0"
                type="button"
                class="market-filters__chip market-filters__chip--reset"
                @click="resetFilters(); load()"
            >
                초기화
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
        </div>

        <!-- 적용 중인 필터 요약 — 개별 칩 클릭으로 해당 필터만 해제 -->
        <div v-if="activeFilterChips.length" class="market-active">
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

        <!-- 필터 모달 -->
        <n-modal v-model:show="filterOpen" preset="card" title="필터" :style="{ maxWidth: '400px' }">
            <div class="filter-body">
                <label class="filter-label">빠른 날짜</label>
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
                <label class="filter-label">날짜시간</label>
                <div class="filter-date-row">
                    <input v-model="date" type="datetime-local" class="filter-date" />
                </div>
                <label class="filter-label">빠른 보기</label>
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
                <label class="filter-label">서비스 구분</label>
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
                <label class="filter-label">인원</label>
                <n-select
                    v-model:value="minPassengers"
                    :options="PASSENGER_OPTIONS"
                    size="large"
                />
                <label class="filter-label">빠른 금액</label>
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
                <label class="filter-label">금액 범위 (원)</label>
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
                title="가져올 수 있는 운행이 없습니다"
                hint="필터를 줄이거나 잠시 후 다시 확인해 주세요"
            />
            <template v-else>
                <!-- 추천 운행 — 내 매칭 조건에 맞는 운행 -->
                <div v-if="recommended.length" class="market-section">
                    <div class="market-section__head">
                        <b>추천 운행</b>
                        <span class="market-section__count">전체 {{ recommended.length }}건 <BaseIcon name="arrow-forward" :size="12" /></span>
                    </div>
                    <div class="order-grid">
                        <OrderCard
                            v-for="order in recommended"
                            :key="order.key"
                            :order="order"
                            :highlight="highlightKeys.has(order.key)"
                        />
                    </div>
                </div>

                <!-- 전체 운행 -->
                <div class="market-section">
                    <div class="market-section__head">
                        <b>전체 운행</b>
                        <span class="market-section__count">{{ singleRows.length + setRows.length }}건</span>
                    </div>
                    <div class="order-grid">
                        <SetGroupCard
                            v-for="order in setRows"
                            :key="order.key"
                            :set="order"
                            :highlight="highlightKeys.has(order.key)"
                        />
                        <OrderCard
                            v-for="order in singleRows"
                            :key="order.key"
                            :order="order"
                            :highlight="highlightKeys.has(order.key)"
                        />
                    </div>
                </div>
            </template>

            <div v-if="pagination && pagination.last_page > 1" class="market-pagination">
                <n-pagination
                    :page="page"
                    :page-size="pagination.per_page"
                    :item-count="pagination.total"
                    @update:page="handlePage"
                />
            </div>
        </n-spin>
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
    font-size: 13px;
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
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.filter-quick__chip:hover {
    border-color: var(--brand);
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

.filter-date-row {
    display: flex;
    align-items: center;
    gap: 8px;
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
    font-size: 14px;
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
    font-size: 14px;
    outline: none;
}

.filter-label {
    font-size: 13px;
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
    gap: 16px;
}

.market-pagination {
    display: flex;
    justify-content: center;
    margin-top: 24px;
}

/* ── 필터 칩 행 ── */
.market-filters {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    scrollbar-width: none;
    margin-bottom: 10px;
}
.market-filters::-webkit-scrollbar {
    display: none;
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
.market-filters__chip--reset {
    color: var(--danger);
    border-color: var(--danger);
}

.market-filters__search {
    display: inline-flex;
    align-items: center;
    gap: 6px;
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

.market-filters__search:hover {
    border-color: var(--brand);
    color: var(--brand);
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

/* 적용 중인 필터 요약 칩 — 개별 해제 가능 */
.market-active {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin: 0 0 12px;
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

.market-active__chip:hover {
    background: color-mix(in srgb, var(--brand) 14%, transparent);
    border-color: var(--brand);
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

/* ── 섹션 ── */
.market-section {
    margin-bottom: 16px;
}
.market-section__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.market-section__head b {
    font-size: 14px;
    font-weight: 800;
}
.market-section__count {
    font-size: 11px;
    color: var(--text-muted);
}

</style>