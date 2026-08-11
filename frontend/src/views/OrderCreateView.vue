<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiCreateOrder, apiCreateSetOrders, apiOrder, apiOrders, apiStructureOrder, apiTransitionOrder, apiUpdateOrder } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { useUiStore } from '../stores/ui';
import OrderCard from '../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();

// 헤더 필터 버튼 → 필터 모달 열기 (마켓과 동일한 신호)
const filterOpen = ref(false);
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'filter') {
            filterOpen.value = true;
        }
    },
);

// 화면 모드: 'list'(내 오더 목록) / 'form'(등록·수정 폼)
const screen = ref(route.params.id ? 'form' : 'list');
const myOrders = ref([]);
const myOrdersLoading = ref(false);

// 목록 탭: 'received'(받은 오더) / 'registered'(등록 오더)
const listSource = ref('received');
const SOURCE_TABS = [
    { label: '받은 오더', value: 'received' },
    { label: '등록 오더', value: 'registered' },
];

// 운행 단계 필터 (백엔드 tab 파라미터)
const listTab = ref('진행중');
const STATUS_TABS = [
    { label: '진행중', value: '진행중' },
    { label: '완료', value: '완료' },
    { label: '취소', value: '취소' },
    { label: '초안', value: '초안' },
];

const loadMyOrders = async () => {
    myOrdersLoading.value = true;

    try {
        const params = { scope: 'mine', source: listSource.value, tab: listTab.value, per_page: 30 };

        if (region.value) {
            params.region = region.value;
        }
        if (vehicleType.value) {
            params.vehicle_type = vehicleType.value;
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

        const { data } = await apiOrders(params);
        myOrders.value = data.data;
    } catch (e) {
        error.value = getApiErrorMessage(e, '오더 목록을 불러오지 못했습니다.');
    } finally {
        myOrdersLoading.value = false;
    }
};

const switchSource = (source) => {
    listSource.value = source;
    loadMyOrders();
};

const switchTab = (tab) => {
    listTab.value = tab;
    loadMyOrders();
};

// ── 목록 필터 (마켓과 동일) ──
const region = ref('');
const vehicleType = ref('');
const minAmount = ref(null);
const maxAmount = ref(null);
const minPassengers = ref(null);

const VEHICLE_OPTIONS = [
    { label: '전체', value: '' },
    { label: '스타리아', value: '스타리아' },
    { label: '카니발', value: '카니발' },
    { label: '그랜드 스타렉스', value: '스타렉스' },
];

const PASSENGER_OPTIONS = [
    { label: '전체', value: null },
    { label: '1명 이상', value: 1 },
    { label: '4명 이상', value: 4 },
    { label: '7명 이상', value: 7 },
    { label: '9명 이상', value: 9 },
];

const activeFilterCount = computed(() =>
    [region.value, vehicleType.value, minAmount.value, maxAmount.value, minPassengers.value]
        .filter((v) => v !== null && v !== '').length,
);

const applyFilter = () => {
    filterOpen.value = false;
    loadMyOrders();
};

const resetFilters = () => {
    region.value = '';
    vehicleType.value = '';
    minAmount.value = null;
    maxAmount.value = null;
    minPassengers.value = null;
};

const goCreate = () => {
    screen.value = 'form';
};

// 셋트/단일 오더 분리
const setRows = computed(() => myOrders.value.filter((o) => o.kind === 'set'));
const singleRows = computed(() => myOrders.value.filter((o) => o.kind !== 'set'));

// 수정 모드: /orders/:id/edit
const editId = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEdit = computed(() => editId.value !== null);

const SERVICE_OPTIONS = [
    { value: 'pickup', label: '픽업' },
    { value: 'sending', label: '공항샌딩' },
    { value: 'landing', label: '공항랜딩' },
];

// AI 구조화는 한글 라벨(픽업/샌딩/랜딩)로 반환한다 → 코드로 매핑
const SERVICE_CODE_BY_LABEL = { 픽업: 'pickup', 샌딩: 'sending', 랜딩: 'landing', 혼합: 'pickup' };

const toServiceCode = (raw) => {
    if (SERVICE_OPTIONS.some((o) => o.value === raw)) {
        return raw;
    }

    return SERVICE_CODE_BY_LABEL[raw] ?? 'pickup';
};

const summary = ref('');
const structuring = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const lineItems = ref([]);
const publishNow = ref(true);

const mode = ref('ai');
const setName = ref('');
const setLineItems = ref([]);

const form = reactive({
    customer_name: '',
    vehicle_type: '',
    service_type: 'pickup',
    service_date: '',
    service_time: '',
    pickup_location: '',
    dropoff_location: '',
    flight_number: '',
    passenger_count: null,
    luggage_count: null,
    expected_revenue: null,
    reservation_company: '직접예약',
});

// 날짜 변환: AI의 "8월3일"/"3号" → "YYYY-MM-DD" (연도는 올해 기준)
const toIsoDate = (raw) => {
    const value = (raw ?? '').trim();

    if (!value) {
        return '';
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }

    let month = null;
    let day = null;

    if (/(\d{1,2})월(\d{1,2})일/.test(value)) {
        month = Number(RegExp.$1);
        day = Number(RegExp.$2);
    } else if (/^(\d{1,2})[号日]$/.test(value)) {
        month = new Date().getMonth() + 1;
        day = Number(RegExp.$1);
    }

    if (month === null || day === null) {
        return value;
    }

    const year = new Date().getFullYear();

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

// "YYYY-MM-DD" → "월요일" 등 요일
const WEEKDAYS = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];

const weekdayOf = (isoDate) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
        return '';
    }

    return WEEKDAYS[new Date(`${isoDate}T00:00:00`).getDay()];
};

const weekdayLabel = computed(() => weekdayOf(form.service_date));

const applyStructured = (s) => {
    form.service_type = toServiceCode(s.service_type);
    form.service_date = toIsoDate(s.service_date ?? '');
    form.service_time = s.service_time ?? s.scheduled_time ?? '';
    form.vehicle_type = s.vehicle_type ?? '';
    form.passenger_count = s.passenger_count ?? null;
    form.luggage_count = s.luggage_count ?? null;
    form.pickup_location = s.pickup_location ?? '';
    form.dropoff_location = s.dropoff_location ?? '';
    form.flight_number = s.flight_number ?? '';
    form.expected_revenue = s.amount_value ?? null;
    lineItems.value = (s.line_items ?? []).map((item) => {
        const isoDate = toIsoDate(item.service_date ?? '');

        return {
            ...item,
            service_date: isoDate,
            service_weekday: item.service_weekday || weekdayOf(isoDate),
        };
    });
};

const SERVICE_LABELS = { pickup: '픽업', sending: '공항샌딩', landing: '공항랜딩' };

// 일정(AI 구조화) 테이블 표시용 행 — 날짜/요일/금액 포함 (금액은 없으면 빈칸)
const lineItemRows = computed(() =>
    (lineItems.value ?? []).map((item) => ({
        scheduled_time: item.scheduled_time || '-',
        service_date: item.service_date || '-',
        service_weekday: item.service_weekday || '-',
        service_type: SERVICE_LABELS[item.service_type] ?? (item.service_type || '-'),
        pickup_location: item.pickup_location || '-',
        dropoff_location: item.dropoff_location || '-',
        flight_number: item.flight_number || '-',
        amount: item.amount_text ?? (item.amount_value != null ? `${Number(item.amount_value).toLocaleString()}원` : ''),
    })),
);

const structure = async () => {
    structuring.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await apiStructureOrder(summary.value);
        applyStructured(data.data.structured);
        success.value = 'AI 구조화가 완료되었습니다. 내용을 확인하고 저장해주세요.';
    } catch (e) {
        error.value = getApiErrorMessage(e, '구조화에 실패했습니다.');
    } finally {
        structuring.value = false;
    }
};

// 등록 전 별도 검증 없음 — 모든 필드는 선택 입력 (AI 구조화/부분 입력 허용)
const save = async () => {
    saving.value = true;
    error.value = '';

    try {
        const payload = { ...form };

        // 서비스 일시를 "YYYY-MM-DD HH:MM:SS" 형태로 저장
        const isoDate = toIsoDate(form.service_date);

        if (isoDate && /^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
            payload.service_date = isoDate;
            payload.service_datetime = `${isoDate} ${form.service_time || '00:00'}:00`;
        }

        if (lineItems.value.length) {
            payload.line_items = lineItems.value;
        }

        let orderId = editId.value;

        if (isEdit.value) {
            await apiUpdateOrder(orderId, payload);
        } else {
            const { data } = await apiCreateOrder(payload);
            orderId = data.data.id;

            // "즉시 공개"면 초안 → 공개 상태로 전환해 마켓에 노출한다
            if (publishNow.value) {
                await apiTransitionOrder(orderId, 'published').catch(() => {});
            }
        }

        // 등록/수정 완료 → 목록으로 복귀
        success.value = isEdit.value ? '오더가 수정되었습니다.' : '오더가 등록되었습니다.';
        screen.value = 'list';
        await loadMyOrders();
    } catch (e) {
        error.value = getApiErrorMessage(e, isEdit.value ? '오더 수정에 실패했습니다.' : '오더 등록에 실패했습니다.');
    } finally {
        saving.value = false;
    }
};

// 수정 모드: 기존 오더를 불러와 폼에 채운다
const loadForEdit = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await apiOrder(editId.value);
        const orderData = data.order;

        form.customer_name = orderData.customer_name ?? '';
        form.vehicle_type = orderData.vehicle_type ?? '';
        form.service_type = orderData.service_type ?? 'pickup';
        form.service_date = toIsoDate(orderData.service_date ?? '');
        form.service_time = orderData.service_time ?? '';
        form.pickup_location = orderData.pickup_location ?? '';
        form.dropoff_location = orderData.dropoff_location ?? '';
        form.flight_number = orderData.flight_number ?? '';
        form.passenger_count = orderData.passenger_count ?? null;
        form.luggage_count = orderData.luggage_count ?? null;
        form.expected_revenue = orderData.expected_revenue ?? orderData.amount_value ?? null;
        form.reservation_company = orderData.reservation_company ?? '직접예약';

        lineItems.value = (orderData.line_items ?? []).map((item) => {
            const isoDate = toIsoDate(item.service_date ?? '');

            return {
                ...item,
                service_date: isoDate,
                service_weekday: item.service_weekday || weekdayOf(isoDate),
            };
        });
    } catch (e) {
        error.value = getApiErrorMessage(e, '오더를 불러오지 못했습니다.');
    } finally {
        loading.value = false;
    }
};

const loading = ref(false);

onMounted(() => {
    if (isEdit.value) {
        loadForEdit();
    } else {
        loadMyOrders();
    }
});

const addSetLine = () => {
    setLineItems.value.push({
        scheduled_time: '',
        service_date: '',
        service_time: '',
        service_type: '',
        pickup_location: '',
        dropoff_location: '',
        flight_number: '',
        passenger_count: null,
        luggage_count: null,
        expected_revenue: null,
        vehicle_type: '',
    });
};

const removeSetLine = (index) => {
    setLineItems.value.splice(index, 1);
};

const saveSet = async () => {
    saving.value = true;
    error.value = '';

    try {
        const orders = setLineItems.value.map((item) => {
            const isoDate = toIsoDate(item.service_date);
            const payload = {
                customer_name: '',
                vehicle_type: item.vehicle_type || '',
                service_type: item.service_type || 'pickup',
                service_date: '',
                service_time: item.service_time || '',
                pickup_location: item.pickup_location || '',
                dropoff_location: item.dropoff_location || '',
                flight_number: item.flight_number || '',
                passenger_count: item.passenger_count ?? null,
                luggage_count: item.luggage_count ?? null,
                expected_revenue: item.expected_revenue ?? null,
                reservation_company: '직접예약',
            };

            if (isoDate && /^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
                payload.service_date = isoDate;
                payload.service_datetime = `${isoDate} ${item.service_time || '00:00'}:00`;
            }

            return payload;
        });

        await apiCreateSetOrders({ group_name: setName.value, orders });
        router.push({ name: 'market' });
    } catch (e) {
        error.value = getApiErrorMessage(e, '셋트 등록에 실패했습니다.');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div>
        <!-- 내 오더 목록 -->
        <template v-if="screen === 'list'">
            <div class="page-head">
                <p class="page-head__desc">받은 오더와 등록한 오더를 관리합니다.</p>
                <n-button type="primary" size="large" round @click="goCreate">+ 오더 등록</n-button>
            </div>

            <div class="create-tabs">
                <n-radio-group v-model:value="listSource" size="large" @update:value="switchSource">
                    <n-radio-button v-for="tab in SOURCE_TABS" :key="tab.value" :value="tab.value">
                        {{ tab.label }}
                    </n-radio-button>
                </n-radio-group>
            </div>

            <div class="create-tabs create-tabs--status">
                <n-radio-group v-model:value="listTab" size="small" @update:value="switchTab">
                    <n-radio-button v-for="tab in STATUS_TABS" :key="tab.value" :value="tab.value">
                        {{ tab.label }}
                    </n-radio-button>
                </n-radio-group>
            </div>

            <!-- 활성 필터 칩 -->
            <div v-if="activeFilterCount > 0" class="create-tags">
                <n-tag v-if="region" size="medium" round closable @close="region = ''; loadMyOrders()">
                    📍 {{ region }}
                </n-tag>
                <n-tag v-if="vehicleType" size="medium" round closable @close="vehicleType = ''; loadMyOrders()">
                    🚐 {{ vehicleType }}
                </n-tag>
                <n-tag v-if="minAmount || maxAmount" size="medium" round closable @close="minAmount = null; maxAmount = null; loadMyOrders()">
                    💰 {{ minAmount ? Number(minAmount).toLocaleString() : 0 }}~{{ maxAmount ? Number(maxAmount).toLocaleString() : '∞' }}원
                </n-tag>
                <n-tag v-if="minPassengers" size="medium" round closable @close="minPassengers = null; loadMyOrders()">
                    👥 {{ minPassengers }}명 이상
                </n-tag>
                <n-button size="small" round tertiary @click="resetFilters(); loadMyOrders()">
                    전체 초기화
                </n-button>
            </div>

            <div v-if="myOrdersLoading" class="my-order-list">
                <OrderCardSkeleton v-for="n in 5" :key="n" />
            </div>
            <n-empty
                v-else-if="!myOrders.length"
                description="오더가 없습니다."
                :image-size="70"
            />
            <div v-else class="my-order-list">
                    <SetGroupCard
                        v-for="order in setRows"
                        :key="order.key"
                        :set="order"
                    />
                    <OrderCard
                        v-for="order in singleRows"
                        :key="order.key"
                        :order="order"
                    />
                </div>

            <!-- 필터 모달 (마켓과 동일) -->
            <n-modal
                v-model:show="filterOpen"
                preset="card"
                title="오더 필터"
                :style="{ maxWidth: '420px' }"
            >
                <div class="filter-body">
                    <label class="filter-label">지역(노선)</label>
                    <n-input
                        v-model:value="region"
                        placeholder="출발·도착 지역 (예: 인천공항)"
                        clearable
                        size="large"
                    />
                    <label class="filter-label">차량</label>
                    <n-select v-model:value="vehicleType" :options="VEHICLE_OPTIONS" size="large" />
                    <label class="filter-label">인원</label>
                    <n-select v-model:value="minPassengers" :options="PASSENGER_OPTIONS" size="large" />
                    <label class="filter-label">금액 범위 (원)</label>
                    <div class="filter-amount-row">
                        <n-input v-model:value="minAmount" type="number" placeholder="최소" clearable size="large" />
                        <span class="filter-amount-sep">~</span>
                        <n-input v-model:value="maxAmount" type="number" placeholder="최대" clearable size="large" />
                    </div>
                </div>
                <template #footer>
                    <div class="filter-footer">
                        <n-button @click="filterOpen = false">취소</n-button>
                        <n-button v-if="activeFilterCount > 0" @click="resetFilters()">초기화</n-button>
                        <n-button type="primary" @click="applyFilter">적용</n-button>
                    </div>
                </template>
            </n-modal>
        </template>

        <!-- 등록/수정 폼 -->
        <div v-else>
            <div class="page-head">
                <p class="page-head__desc">요약 텍스트를 AI로 구조화하거나 직접 입력해 등록합니다.</p>
            </div>

            <div class="create-tabs">
            <n-radio-group v-model:value="mode" size="large">
                <n-radio-button value="ai">AI 구조화 등록</n-radio-button>
                <n-radio-button value="manual">직접 입력 등록</n-radio-button>
                <n-radio-button value="set">셋트 등록</n-radio-button>
            </n-radio-group>
        </div>

        <n-spin :show="loading" class="create-body">
            <n-alert v-if="error" type="error" :show-icon="true" class="create-block">
                {{ error }}
            </n-alert>
            <n-alert v-if="success" type="success" :show-icon="true" class="create-block">
                {{ success }}
            </n-alert>

        <n-card v-if="mode === 'ai'" :bordered="true" class="create-block" title="AI 구조화">
            <n-input
                v-model:value="summary"
                type="textarea"
                :rows="4"
                placeholder="예) 8월 10일 오전 9시 강남구에서 강릉 정동진 픽업, 성인 3명 짐 2개, 카니발"
            />
            <n-button
                type="primary"
                class="create-structure-btn"
                :loading="structuring"
                :disabled="!summary.trim()"
                @click="structure"
            >
                AI 구조화
            </n-button>
        </n-card>

        <n-card v-if="mode === 'ai' || mode === 'manual'" :bordered="true" class="create-block" title="오더 정보">
            <n-form label-placement="top" label-width="auto">
                <div class="create-grid">
                    <n-form-item label="날짜">
                        <n-date-picker
                            v-model:value="form.service_date"
                            value-format="yyyy-MM-dd"
                            type="date"
                            placeholder="날짜 선택"
                            :clearable="true"
                            class="create-full"
                        />
                        <n-tag v-if="weekdayLabel" size="small" round class="create-weekday">
                            {{ weekdayLabel }}
                        </n-tag>
                    </n-form-item>
                    <n-form-item label="시간">
                        <n-time-picker
                            v-model:value="form.service_time"
                            value-format="HH:mm"
                            placeholder="시간 선택"
                            class="create-full"
                        />
                    </n-form-item>
                    <n-form-item label="구분">
                        <n-select
                            v-model:value="form.service_type"
                            :options="SERVICE_OPTIONS"
                            placeholder="구분 선택"
                        />
                    </n-form-item>
                    <n-form-item label="차량">
                        <n-input v-model:value="form.vehicle_type" placeholder="예) 카니발" />
                    </n-form-item>
                    <n-form-item label="출발">
                        <n-input v-model:value="form.pickup_location" placeholder="출발지" />
                    </n-form-item>
                    <n-form-item label="도착">
                        <n-input v-model:value="form.dropoff_location" placeholder="도착지" />
                    </n-form-item>
                    <n-form-item label="항공편">
                        <n-input v-model:value="form.flight_number" placeholder="예) KE101" />
                    </n-form-item>
                    <n-form-item label="인원">
                        <n-input-number v-model:value="form.passenger_count" :min="0" class="create-full" />
                    </n-form-item>
                    <n-form-item label="짐">
                        <n-input-number v-model:value="form.luggage_count" :min="0" class="create-full" />
                    </n-form-item>
                    <n-form-item label="금액">
                        <n-input-number
                            v-model:value="form.expected_revenue"
                            :min="0"
                            class="create-full"
                            placeholder="금액"
                        />
                    </n-form-item>
                </div>
            </n-form>
        </n-card>

        <n-card v-if="lineItems.length && (mode === 'ai' || mode === 'manual')" :bordered="true" class="create-block" title="일정 (AI 구조화)">
            <div class="schedule-list">
                <n-card
                    v-for="(item, index) in lineItemRows"
                    :key="index"
                    size="small"
                    class="schedule-card"
                >
                    <template #header>
                        <div class="schedule-card__head">
                            <n-space align="center" :size="8">
                                <strong class="schedule-time">{{ item.scheduled_time }}</strong>
                                <n-tag size="small" round>{{ item.service_type }}</n-tag>
                            </n-space>
                            <span class="schedule-date">{{ item.service_date }} {{ item.service_weekday }}</span>
                        </div>
                    </template>

                    <div class="schedule-card__route">
                        {{ item.pickup_location }}
                        <span class="schedule-card__arrow">→</span>
                        {{ item.dropoff_location }}
                    </div>
                    <div class="schedule-card__meta">
                        <n-space :size="12">
                            <span v-if="item.flight_number && item.flight_number !== '-'">
                                항공편: {{ item.flight_number }}
                            </span>
                            <span v-if="item.amount">금액: {{ item.amount }}</span>
                        </n-space>
                    </div>
                </n-card>
            </div>
        </n-card>

        <n-card v-if="mode === 'set'" :bordered="true" class="create-block" title="셋트 정보">
            <n-form label-placement="top">
                <n-form-item label="셋트명" required>
                    <n-input v-model:value="setName" placeholder="예) KLOOK 8월 셋트" />
                </n-form-item>
            </n-form>
        </n-card>

        <n-card v-if="mode === 'set' && setLineItems.length" bordered class="create-block" title="셋트 일정">
            <div class="set-list">
                <n-card
                    v-for="(item, index) in setLineItems"
                    :key="index"
                    size="small"
                    class="set-item"
                    :title="'일정 ' + (index + 1)"
                >
                    <template #header-extra>
                        <n-button text type="error" @click="removeSetLine(index)">삭제</n-button>
                    </template>
                    <div class="set-grid">
                        <n-form-item label="날짜">
                            <n-date-picker
                                v-model:value="item.service_date"
                                value-format="yyyy-MM-dd"
                                type="date"
                                placeholder="날짜"
                                class="set-full"
                            />
                        </n-form-item>
                        <n-form-item label="시간">
                            <n-time-picker v-model:value="item.service_time" value-format="HH:mm" class="set-full" />
                        </n-form-item>
                        <n-form-item label="구분">
                            <n-select v-model:value="item.service_type" :options="SERVICE_OPTIONS" placeholder="구분" />
                        </n-form-item>
                        <n-form-item label="출발">
                            <n-input v-model:value="item.pickup_location" placeholder="출발지" />
                        </n-form-item>
                        <n-form-item label="도착">
                            <n-input v-model:value="item.dropoff_location" placeholder="도착지" />
                        </n-form-item>
                        <n-form-item label="항공편">
                            <n-input v-model:value="item.flight_number" placeholder="예) KE101" />
                        </n-form-item>
                        <n-form-item label="인원">
                            <n-input-number v-model:value="item.passenger_count" :min="0" class="set-full" />
                        </n-form-item>
                        <n-form-item label="짐">
                            <n-input-number v-model:value="item.luggage_count" :min="0" class="set-full" />
                        </n-form-item>
                        <n-form-item label="금액">
                            <n-input-number v-model:value="item.expected_revenue" :min="0" class="set-full" />
                        </n-form-item>
                        <n-form-item label="차량">
                            <n-input v-model:value="item.vehicle_type" placeholder="예) 카니발" />
                        </n-form-item>
                    </div>
                </n-card>
            </div>
        </n-card>

        <n-button
            v-if="mode === 'set'"
            type="default"
            size="large"
            class="create-block-btn"
            @click="addSetLine"
        >
            + 일정 추가
        </n-button>

        <n-checkbox v-if="mode !== 'set' && !isEdit" v-model:checked="publishNow" class="create-block">
            등록 즉시 마켓에 공개
        </n-checkbox>

        <n-button v-if="mode !== 'set'" type="primary" size="large" :loading="saving" @click="save">
            {{ isEdit ? '수정 저장' : '오더 등록' }}
        </n-button>

        <n-button v-if="mode === 'set'" type="primary" size="large" :loading="saving" @click="saveSet">
            셋트 등록
        </n-button>
        </n-spin>
        </div>
    </div>
</template>

<style scoped>
.create-block {
    margin-bottom: 16px;
    border-radius: 16px;
}

.create-body {
    display: block;
}

/* 내가 등록한 오더 목록 */
.my-order-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.create-tabs--status {
    margin-top: -6px;
}

.create-tabs--status .n-radio-group {
    flex-wrap: wrap;
}

/* 활성 필터 칩 */
.create-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
}

/* 필터 모달 */
.filter-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
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

.filter-amount-row {
    display: flex;
    align-items: center;
    gap: 8px;
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

.create-structure-btn {
    margin-top: 12px;
}

.create-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0 16px;
}

.create-full {
    width: 100%;
}

.create-weekday {
    margin-top: 6px;
}

/* 일정 카드 목록 */
.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.schedule-card {
    border-radius: 10px;
}

.schedule-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
}

.schedule-time {
    font-size: 15px;
}

.schedule-date {
    color: var(--text-muted);
    font-size: 12px;
}

.schedule-card__route {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
}

.schedule-card__arrow {
    color: var(--accent);
    font-size: 16px;
    font-weight: 700;
}

.schedule-card__meta {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 12px;
}

.create-tabs {
    margin-bottom: 20px;
}

.set-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.set-item {
    border-radius: 12px;
}

.set-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0 12px;
}

.set-full {
    width: 100%;
}

.create-block-btn {
    margin-bottom: 16px;
}
</style>
