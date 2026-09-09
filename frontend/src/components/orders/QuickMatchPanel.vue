<script setup>
/**
 * QuickMatchPanel — 빠른매칭 패널 (마켓 첫 섹션).
 * 매칭 상태(온/오프·조건 요약·조건 관리 모달)와 나에게 매칭된 운행 목록을 한데 묶는다.
 * - 변경 후 emit('changed') → 부모(마켓) 목록을 갱신해 중복 노출을 막는다.
 * - 기사(Driver)만 관련 데이터를 호출한다.
 */
import { computed, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../../stores/auth';
import { apiMyDriver, apiMyVehicles, apiSetDriverMatchEnabled } from '../../api/driver';
import { apiMatchPreferences, apiCreateMatchPreference } from '../../api/match';
import { apiOrders } from '../../api/orders';
import { getApiErrorMessage } from '../../api/client';
import { useMatchSettings } from '../../composables/useMatchSettings';
import { ORDER_TAGS } from '../../utils/tags';
import { matchTimeInfo } from '../../utils/matchTime';
import BaseIcon from '../common/BaseIcon.vue';
import EmptyState from '../common/EmptyState.vue';
import OrderCard from './OrderCard.vue';
import OrderCardSkeleton from './OrderCardSkeleton.vue';
import UiCard from '../ui/UiCard.vue';
import UiChip from '../ui/UiChip.vue';

const emit = defineEmits(['changed']);

const auth = useAuthStore();
const message = useMessage();

const isDriver = computed(() => auth.user?.role === 'Driver');

const driver = ref(null);
const vehicle = ref(null);
const vehicles = ref([]);
const preference = ref(null);
const preferences = ref([]);
const matchedOrders = ref([]);
const matchedOrdersLoading = ref(true);
const loading = ref(true);

const matchEnabled = computed(() => Boolean(driver.value?.match_enabled));
const hasActivePreference = computed(() => preferences.value.some((p) => p.is_active));
// 매칭 설정 시간대 — 날짜(오늘/내일)를 앞에 붙이고, 자정을 넘기면 '다음날' 배지를 단다
const preferenceTime = computed(() => matchTimeInfo(preference.value || {}));

const statusText = computed(() => {
    if (!matchEnabled.value) {
        return '매칭을 켜면 조건에 맞는 운행이 모여요';
    }
    if (quickConditionCount.value > 0) {
        return `활성 매칭 조건 ${quickConditionCount.value}개 · 맞는 운행을 자동 추천해요`;
    }
    return '조건을 설정하면 맞는 운행을 추천해 드려요';
});

// 매칭 상태·조건·매칭된 운행을 병렬로 불러온다
const load = async (silent = false) => {
    if (!isDriver.value) {
        loading.value = false;
        matchedOrdersLoading.value = false;

        return;
    }

    if (!silent) {
        loading.value = true;
    }

    const [me, vehiclesRes, prefs, matched] = await Promise.allSettled([
        apiMyDriver(),
        apiMyVehicles(),
        apiMatchPreferences(),
        apiOrders({ scope: 'market', matched: 1, per_page: 100, sort: 'latest' }),
    ]);

    if (me.status === 'fulfilled') {
        driver.value = me.value.data.data;
    }

    if (vehiclesRes.status === 'fulfilled') {
        vehicles.value = vehiclesRes.value.data.data ?? [];
        vehicle.value = vehicles.value[0] ?? null;
    }

    if (prefs.status === 'fulfilled') {
        const list = prefs.value.data.data ?? [];
        preferences.value = list;
        preference.value = list.find((p) => p.is_active) ?? list[0] ?? null;
    }

    if (matched.status === 'fulfilled') {
        matchedOrders.value = (matched.value.data.data ?? []).filter((order) => order.is_matched_to_me && order.kind !== 'set');
    }

    matchedOrdersLoading.value = false;

    if (!silent) {
        loading.value = false;
    }
};

// 매칭 켜기/끄기 — 실패 시 원래 상태로 되돌린다
const toggleMatch = async (enabled) => {
    const prev = driver.value?.match_enabled ?? false;

    if (driver.value) {
        driver.value.match_enabled = enabled;
    }

    try {
        await apiSetDriverMatchEnabled(enabled);
        message.success(enabled ? '빠른 매칭을 켰습니다.' : '빠른 매칭을 껐습니다.');
        load(true);
        emit('changed');
    } catch (e) {
        if (driver.value) {
            driver.value.match_enabled = prev;
        }
        message.error(getApiErrorMessage(e, '매칭 설정을 변경하지 못했습니다.'));
    }
};

// 매칭 설정 팝오버 — 시간·태그·금액·차량을 골라 간단히 매칭 설정을 만든다.
// 저장 후 패널 데이터(매칭된 운행)를 조용히 갱신한다.
const matchSettings = useMatchSettings({
    message,
    loadMatchedOrders: () => load(true),
});

const {
    editingMatchId,
    matchForm,
    savingMatch,
    TIME_PRESETS, AMOUNT_PRESETS,
    applyTimePreset,
    saveMatch,
} = matchSettings;

// 저장된 매칭 설정 칩 표시용 유형 이름
const SERVICE_LABELS = { pickup: '픽업', sending: '샌딩', landing: '랜딩' };

// ── 빠른 매칭 모달 — 유형·태그·시간·금액·차량을 골라 간단히 매칭한다 ──
const quickMatchOpen = ref(false);
// 'all' = 조건 관리로 전체 열기, 그 외에는 해당 섹션만 표시
const quickSection = ref('all');
const quickTimeIndex = ref(null);
const quickDateRange = ref('');
const quickServiceType = ref('');
const quickAmount = ref(0);
const quickVehicleId = ref(null);
// 태그 빠른 선택 — 원하는 운행 태그를 다중 선택한다 (운행 태그와 하나라도 겹치면 매칭)
const quickTags = ref([]);
// 모달을 닫을 때 변경이 있었으면 자동 저장한다
const quickChanged = ref(false);
const quickTagDirty = ref(false);
const quickAmountDirty = ref(false);
const quickVehicleDirty = ref(false);

// 서비스 유형 — 탭해서 선택, 다시 탭하면 해제
const QUICK_SERVICES = [
    { label: '랜딩', value: 'landing' },
    { label: '샌딩', value: 'sending' },
    { label: '픽업', value: 'pickup' },
];

// 태그 프리셋 — 운행 등록과 동일한 공용 태그 목록을 사용한다
const QUICK_TAGS = ORDER_TAGS;
const QUICK_TAG_SHOW = 8;
const quickTagMore = ref(false);
const quickTagAddOpen = ref(false);
const quickCustomTag = ref('');

// 모달에 노출할 프리셋 태그 — 펼치기 전에는 일부만 보여준다
const quickVisibleTags = computed(() => (quickTagMore.value ? QUICK_TAGS : QUICK_TAGS.slice(0, QUICK_TAG_SHOW)));

// 태그 추가 모달 열기 — 입력값을 비우고 시작한다
const openQuickTagAdd = () => {
    quickCustomTag.value = '';
    quickTagAddOpen.value = true;
};

// 직접 입력 태그 추가 — 빈 값·중복 방지, 선택 목록에 반영. 저장 후 모달을 닫는다
const addQuickCustomTag = () => {
    const tag = quickCustomTag.value.trim();

    if (!tag) {
        return;
    }

    if (!quickTags.value.includes(tag)) {
        quickTags.value.push(tag);
    }

    quickCustomTag.value = '';
    quickTagAddOpen.value = false;
    quickTagDirty.value = true;
    quickChanged.value = true;
};

// 태그 삭제 — 직접 추가한 태그 포함 선택 목록에서 제거
const removeQuickTag = (tag) => {
    const list = quickTags.value;
    const index = list.indexOf(tag);

    if (index >= 0) {
        list.splice(index, 1);
        quickTagDirty.value = true;
        quickChanged.value = true;
    }
};

// 'HH:mm' 문자열 → naive-ui time-picker(ms 타임스탬프) 변환 (모달 열 때 현재 설정 복원용)
const parseTime = (time) => {
    if (!time) {
        return null;
    }
    const [hours, minutes] = String(time).split(':').map(Number);

    return new Date(2000, 0, 1, hours || 0, minutes || 0).getTime();
};

// 기본 선택 차량 — 한 대면 그 차량, 여러 대면 기본(is_default) 차량. 모달에서 바꿀 수 있다
const defaultQuickVehicleId = () => {
    if (!vehicles.value.length) {
        return null;
    }
    if (vehicles.value.length === 1) {
        return vehicles.value[0].id;
    }

    return (vehicles.value.find((v) => v.is_default) ?? vehicles.value[0]).id;
};

// 시간대 프리셋 매칭 — 현재 설정(시작/종료)과 일치하는 프리셋 칩을 미리 선택해 둔다
const matchPresetIndex = (start, end) => {
    if (!start || !end) {
        return null;
    }
    const index = TIME_PRESETS.findIndex((preset) => preset.start === start && preset.end === end);

    return index >= 0 ? index : null;
};

// 매칭 설정 칩에 표시할 차량 — 설정에 지정한 차량 우선, 없으면 대표 차량
const prefVehicle = computed(() => {
    if (preference.value?.vehicle_id) {
        return vehicles.value.find((v) => v.id === preference.value.vehicle_id) || vehicle.value;
    }

    return vehicle.value;
});

// 태그 칩 — 저장된 매칭 설정의 태그를 최대 3개까지 '·'로 표시, 넘으면 '+N'
const tagsChips = computed(() => {
    const pref = preference.value;
    const chips = [];
    const tags = pref?.tags ?? [];
    if (tags.length) {
        const shown = tags.slice(0, 3);
        const more = tags.length - shown.length;
        chips.push({ key: 'tags', label: '태그', text: shown.join(' · ') + (more > 0 ? ` · +${more}` : '') });
    }

    return chips;
});

// 빠른 매칭 설정 요약 — 설정된 조건 개수 (빈 설정 안내 문구 분기용)
const quickConditionCount = computed(() => {
    const pref = preference.value;
    let n = 0;
    if (pref?.vehicle_id) n += 1;
    if (pref?.service_type) n += 1;
    if (pref?.tags?.length) n += 1;
    if (pref?.start_time) n += 1;
    if (Number(pref?.min_revenue) > 0) n += 1;

    return n;
});

// 모달을 열 때 — 현재 설정을 matchForm과 각 섹션에 되돌려 놓는다
const initQuickState = () => {
    const pref = preference.value;

    // 저장 대상 — 기존 활성 설정이 있으면 갱신, 없으면 새로 만든다
    editingMatchId.value = pref?.id ?? null;
    matchForm.name = pref?.name || '빠른 매칭';
    matchForm.start_time = parseTime(pref?.start_time);
    matchForm.end_time = parseTime(pref?.end_time);
    matchForm.date_range = pref?.date_range || '';
    matchForm.days = [];
    matchForm.area = pref?.area || '';
    matchForm.service_type = pref?.service_type || '';
    matchForm.origin = pref?.origin || '';
    matchForm.destination = pref?.destination || '';
    matchForm.vehicle_id = pref?.vehicle_id || null;
    matchForm.min_revenue = Number(pref?.min_revenue) || 0;
    matchForm.max_passengers = null;
    matchForm.is_active = true;

    // 섹션별 값 복원
    quickTimeIndex.value = matchPresetIndex(pref?.start_time, pref?.end_time);
    quickDateRange.value = pref?.date_range || '';
    quickServiceType.value = pref?.service_type || '';
    quickTags.value = Array.isArray(pref?.tags) ? [...pref.tags] : [];
    quickTagMore.value = false;
    quickTagAddOpen.value = false;
    quickCustomTag.value = '';
    quickAmount.value = AMOUNT_PRESETS.some((p) => p.value === Number(pref?.min_revenue))
        ? Number(pref?.min_revenue)
        : 0;
    quickVehicleId.value = pref?.vehicle_id ?? defaultQuickVehicleId();

    quickTagDirty.value = false;
    quickAmountDirty.value = false;
    quickVehicleDirty.value = false;
    quickChanged.value = false;
};

// 조건 관리·칩 클릭 — 해당 섹션 모달을 연다
const openQuickMatch = (section = 'all') => {
    initQuickState();
    quickSection.value = section;
    quickMatchOpen.value = true;
};

// 모달 열림/닫힘 — 열면 현재 설정 복원, 닫을 때 변경이 있었으면 자동 저장
const onQuickModalChange = (show) => {
    if (show) {
        initQuickState();
    } else if (quickChanged.value) {
        saveQuickMatch();
    }
};

// 시간대 선택 — 프리셋을 matchForm에 바로 반영한다 (다시 누르면 해제)
const pickQuickTime = (index) => {
    quickTimeIndex.value = quickTimeIndex.value === index ? null : index;

    if (quickTimeIndex.value == null) {
        matchForm.start_time = null;
        matchForm.end_time = null;
    } else {
        applyTimePreset(TIME_PRESETS[quickTimeIndex.value]);
    }

    quickChanged.value = true;
};

// 금액 선택 — 같은 칩을 다시 누르면 해제 (0 = 조건 없음)
const pickQuickAmount = (value) => {
    quickAmount.value = quickAmount.value === value ? 0 : value;
    quickAmountDirty.value = true;
    quickChanged.value = true;
};

// 서비스 유형 — 같은 유형을 다시 누르면 해제 (유형 조건 없음)
const pickQuickService = (value) => {
    quickServiceType.value = quickServiceType.value === value ? '' : value;
    quickTagDirty.value = true;
    quickChanged.value = true;
};

// 태그 다중 선택 — 탭해서 추가/해제 (하나라도 겹치면 매칭)
const pickQuickTag = (tag) => {
    quickTagDirty.value = true;
    quickChanged.value = true;

    const list = quickTags.value;
    const index = list.indexOf(tag);

    if (index >= 0) {
        list.splice(index, 1);
    } else {
        list.push(tag);
    }
};

// 차량 선택 — 같은 칩을 다시 누르면 해제 (조건 없음)
const pickQuickVehicle = (id) => {
    quickVehicleId.value = quickVehicleId.value === id ? null : id;
    quickVehicleDirty.value = true;
    quickChanged.value = true;
};

// ── 원큐 설정 — 프리셋 하나만 누르면 기본 조건 생성 + 매칭 켬 ──
const QUICK_START_PRESETS = [
    { label: '주간', sub: '06–20', start: '06:00', end: '20:00' },
    { label: '야간', sub: '20–06', start: '20:00', end: '06:00' },
    { label: '종일', sub: '오늘·내일', start: null, end: null },
];
const startingQuick = ref(false);

// 활성 설정이 이미 있으면 매칭만 켜고 안내한다
const startQuickMatching = async (preset) => {
    if (startingQuick.value) {
        return;
    }

    if (preference.value) {
        if (!driver.value?.match_enabled) {
            try {
                await apiSetDriverMatchEnabled(true);
                driver.value.match_enabled = true;
                emit('changed');
            } catch (e) {
                message.error(getApiErrorMessage(e, '매칭을 켜지 못했습니다.'));
            }
        }

        message.info('활성 매칭 설정이 이미 있어요. 아래 조건을 조정해 보세요.');

        return;
    }

    startingQuick.value = true;

    try {
        // 기본 조건 — 오늘·내일 + (시간 프리셋) + 등록 차량. 상세는 '조건 관리'에서 조정
        const res = await apiCreateMatchPreference({
            name: '빠른 매칭',
            start_time: preset.start,
            end_time: preset.end,
            date_range: 'today_tomorrow',
            days: null,
            area: null,
            tags: null,
            service_type: null,
            origin: null,
            destination: null,
            vehicle_id: defaultQuickVehicleId(),
            max_passengers: null,
            min_revenue: 0,
            is_active: true,
        });

        if (!res?.data?.data) {
            throw new Error('매칭 설정 응답이 올바르지 않습니다.');
        }

        if (!driver.value?.match_enabled) {
            await apiSetDriverMatchEnabled(true);
            driver.value.match_enabled = true;
        }

        message.success(`빠른 매칭을 시작했어요 (${preset.label}). 조건은 '조건 관리'에서 조정할 수 있어요.`);
        emit('changed');
        load(true);
    } catch (e) {
        message.error(getApiErrorMessage(e, '빠른 매칭을 시작하지 못했습니다.'));
    } finally {
        startingQuick.value = false;
    }
};

// 선택한 조건으로 매칭 설정 저장 — 건드리지 않은 섹션은 기존 값을 유지한다
const saveQuickMatch = async () => {
    matchForm.date_range = quickDateRange.value;

    if (quickTagDirty.value) {
        matchForm.service_type = quickServiceType.value || null;

        // 태그 — 다중 선택한 태그를 저장한다. 기존 지역 조건은 새 설정에 남기지 않는다.
        matchForm.tags = [...quickTags.value];
        matchForm.area = null;
        matchForm.origin = null;
        matchForm.destination = null;
    }

    if (quickAmountDirty.value) {
        matchForm.min_revenue = quickAmount.value || 0;
    }

    if (quickVehicleDirty.value) {
        matchForm.vehicle_id = quickVehicleId.value;
    }

    await saveMatch();
    quickChanged.value = false;
    quickMatchOpen.value = false;
    emit('changed');
};

// 모달 초기화 — 모든 조건을 비운다 (저장/닫기 시 빈 조건으로 반영된다)
const resetQuickMatch = () => {
    quickSection.value = 'all';
    quickServiceType.value = '';
    quickTags.value = [];
    quickTagMore.value = false;
    quickTagAddOpen.value = false;
    quickCustomTag.value = '';
    quickTimeIndex.value = null;
    matchForm.start_time = null;
    matchForm.end_time = null;
    quickAmount.value = 0;
    quickVehicleId.value = null;

    quickTagDirty.value = true;
    quickAmountDirty.value = true;
    quickVehicleDirty.value = true;
    quickChanged.value = true;
};

onMounted(() => load());
</script>

<template>
    <div v-if="isDriver" class="qmp">
        <!-- 매칭 상태 카드 — 켜기/끄기 + 조건 요약 + 조건 관리 -->
        <UiCard class="qmp-card">
            <div class="qmp-card__row">
                <button type="button" class="qmp-card__main" @click="openQuickMatch('all')">
                    <span class="qmp-card__text">
                        <span class="qmp-card__title">
                            <BaseIcon class="qmp-card__flash" name="flash" :size="14" />
                            빠른매칭
                            <em v-if="matchEnabled" class="qmp-card__state">켜짐</em>
                        </span>
                        <span class="qmp-card__sub">{{ statusText }}</span>
                    </span>
                    <span class="qmp-card__manage">조건 관리<BaseIcon name="arrow-forward" :size="14" /></span>
                </button>
                <button
                    type="button"
                    class="qmp-switch"
                    :class="{ 'qmp-switch--on': matchEnabled }"
                    aria-label="빠른 매칭"
                    :disabled="loading"
                    @click="toggleMatch(!matchEnabled)"
                >
                    <span></span>
                </button>
            </div>

            <!-- 원큐 설정 — 활성 조건이 없을 때 프리셋 하나로 바로 매칭 시작 -->
            <div v-if="!loading && !preference" class="qmp-quick">
                <p class="qmp-quick__title">원큐 설정 — 희망 시간대를 고르면 바로 매칭이 켜져요</p>
                <div class="qmp-quick__row">
                    <button
                        v-for="preset in QUICK_START_PRESETS"
                        :key="preset.label"
                        type="button"
                        class="qmp-quick__btn"
                        :disabled="startingQuick"
                        @click="startQuickMatching(preset)"
                    >
                        <b>{{ preset.label }}</b>
                        <span>{{ preset.sub }}</span>
                    </button>
                </div>
            </div>

            <!-- 조건 요약 칩 — 카테고리별로 묶고, 클릭하면 해당 섹션 모달이 열린다 -->
            <div v-if="matchEnabled && (prefVehicle || preference)" class="qmp-card__chips">
                <span v-if="prefVehicle" class="qmp-chip-group">
                    <button type="button" class="qmp-chip-btn" @click="openQuickMatch('vehicle')">
                        <UiChip>
                            <b>차량</b>{{ prefVehicle.name || prefVehicle.type || '등록 차량' }}
                            <span v-if="prefVehicle.capacity" class="qmp-chip-next">{{ prefVehicle.capacity }}인승</span>
                        </UiChip>
                    </button>
                </span>
                <span v-if="preference?.service_type" class="qmp-chip-group">
                    <button type="button" class="qmp-chip-btn" @click="openQuickMatch('type')">
                        <UiChip><b>유형</b>{{ SERVICE_LABELS[preference.service_type] || preference.service_type }}</UiChip>
                    </button>
                </span>
                <span v-if="tagsChips.length" class="qmp-chip-group">
                    <button
                        v-for="chip in tagsChips"
                        :key="chip.key"
                        type="button"
                        class="qmp-chip-btn"
                        @click="openQuickMatch('tags')"
                    >
                        <UiChip><b>{{ chip.label }}</b>{{ chip.text }}</UiChip>
                    </button>
                </span>
                <span v-if="preference?.start_time" class="qmp-chip-group">
                    <button type="button" class="qmp-chip-btn" @click="openQuickMatch('time')">
                        <UiChip>
                            <b>시간</b>
                            <template v-if="preferenceTime.date">{{ preferenceTime.date }}&nbsp;</template>
                            {{ preferenceTime.start }}–
                            <span v-if="preferenceTime.overnight" class="qmp-chip-next">다음날</span>
                            {{ preferenceTime.end }}
                        </UiChip>
                    </button>
                </span>
                <span v-if="preference?.min_revenue" class="qmp-chip-group">
                    <button type="button" class="qmp-chip-btn" @click="openQuickMatch('amount')">
                        <UiChip><b>최소금액</b>{{ preference.min_revenue.toLocaleString() }}원</UiChip>
                    </button>
                </span>
            </div>
        </UiCard>

        <!-- 나에게 매칭된 운행 -->
        <section class="qmp-section">
            <div class="qmp-section__head">
                <span class="qmp-section__title">나에게 매칭된 운행</span>
                <span class="qmp-section__meta">
                    <span class="qmp-section__count">{{ matchedOrders.length }}건</span>
                    <button
                        type="button"
                        class="qmp-refresh"
                        :disabled="matchedOrdersLoading"
                        aria-label="새로고침"
                        @click="load(true)"
                    >
                        <BaseIcon class="qmp-refresh__icon" :class="{ 'qmp-refresh__icon--spin': matchedOrdersLoading }" name="refresh" :size="13" />
                    </button>
                </span>
            </div>

            <div v-if="matchedOrdersLoading" class="qmp-list">
                <OrderCardSkeleton v-for="n in 2" :key="n" />
            </div>
            <div v-else-if="matchedOrders.length" class="qmp-list">
                <OrderCard v-for="order in matchedOrders" :key="order.id" :order="order" />
            </div>
            <EmptyState
                v-else
                icon="truck"
                :title="matchEnabled ? '조건에 맞는 운행이 아직 없습니다' : '빠른 매칭이 꺼져 있어요'"
                :hint="hasActivePreference
                    ? '운행이 등록되면 여기에 자동으로 모여요'
                    : '빠른 매칭을 켜고 조건을 설정하면 조건에 맞는 운행이 여기에 모여요'"
            />
        </section>

        <!-- 빠른 매칭 조건 모달 -->
        <n-modal
            v-model:show="quickMatchOpen"
            preset="card"
            title="빠른 매칭 설정"
            :style="{ width: 'min(420px, 92vw)' }"
            :bordered="false"
            :mask-closable="true"
            @update:show="onQuickModalChange"
        >
            <div class="qmp-quick">
                <!-- 유형 — 픽업/샌딩/랜딩 (다시 탭하면 해제) -->
                <template v-if="quickSection === 'all' || quickSection === 'type'">
                    <section class="qmp-quick__section">
                        <p class="qmp-quick__label">유형</p>
                        <div class="qmp-quick__chips">
                            <button
                                v-for="service in QUICK_SERVICES"
                                :key="service.value"
                                type="button"
                                class="qmp-quick__chip"
                                :class="{ 'qmp-quick__chip--active': quickServiceType === service.value }"
                                @click="pickQuickService(service.value)"
                            >
                                {{ service.label }}
                            </button>
                        </div>
                    </section>
                </template>

                <!-- 태그 — 원하는 운행 태그 다중 선택 (하나라도 겹치면 매칭), 많으면 '더보기'로 펼친다 -->
                <template v-if="quickSection === 'all' || quickSection === 'tags'">
                    <section class="qmp-quick__section">
                        <p class="qmp-quick__label">태그</p>
                        <div class="qmp-quick__chips">
                            <button
                                v-for="tag in quickVisibleTags"
                                :key="tag.value"
                                type="button"
                                class="qmp-quick__chip"
                                :class="{ 'qmp-quick__chip--active': quickTags.includes(tag.value) }"
                                @click="pickQuickTag(tag.value)"
                            >
                                {{ tag.label }}
                            </button>
                            <button
                                v-if="QUICK_TAGS.length > QUICK_TAG_SHOW"
                                type="button"
                                class="qmp-quick__chip qmp-quick__chip--more"
                                @click="quickTagMore = !quickTagMore"
                            >
                                {{ quickTagMore ? '접기' : '더보기' }}
                            </button>
                            <button
                                type="button"
                                class="qmp-quick__chip qmp-quick__chip--more"
                                @click="openQuickTagAdd"
                            >
                                + 태그 추가
                            </button>
                        </div>

                        <!-- 선택한 태그 — 직접 추가한 태그도 여기서 삭제할 수 있다 -->
                        <div v-if="quickTags.length" class="qmp-quick__sub">
                            <p class="qmp-quick__sub-label">선택한 태그</p>
                            <div class="qmp-quick__chips">
                                <span
                                    v-for="tag in quickTags"
                                    :key="tag"
                                    class="qmp-quick__chip qmp-quick__chip--active qmp-quick__chip--remove"
                                >
                                    {{ tag }}
                                    <button
                                        type="button"
                                        class="qmp-quick__chip-del"
                                        aria-label="태그 삭제"
                                        @click="removeQuickTag(tag)"
                                    >
                                        <BaseIcon name="close" :size="11" />
                                    </button>
                                </span>
                            </div>
                        </div>
                    </section>
                </template>

                <!-- 태그 추가 모달 — 직접 입력한 태그를 저장한다 -->
                <n-modal
                    v-model:show="quickTagAddOpen"
                    preset="card"
                    title="태그 추가"
                    placement="top"
                    :style="{ width: 'min(320px, 88vw)', marginTop: '20vh' }"
                    :bordered="false"
                >
                    <div class="qmp-quick__add">
                        <n-input
                            v-model:value="quickCustomTag"
                            size="small"
                            placeholder="태그 직접 입력 후 저장"
                            class="qmp-quick__add-input"
                            :maxlength="30"
                            autofocus
                            @keyup.enter="addQuickCustomTag"
                        />
                        <n-button
                            type="primary"
                            size="small"
                            :disabled="!quickCustomTag.trim()"
                            @click="addQuickCustomTag"
                        >
                            저장
                        </n-button>
                    </div>
                </n-modal>

                <!-- 시간 — 주간/야간 프리셋 하나 선택 (다시 누르면 해제) -->
                <template v-if="quickSection === 'all' || quickSection === 'time'">
                    <section class="qmp-quick__section">
                        <p class="qmp-quick__label">시간</p>
                        <div class="qmp-quick__chips">
                            <button
                                v-for="(preset, index) in TIME_PRESETS"
                                :key="preset.label"
                                type="button"
                                class="qmp-quick__chip"
                                :class="{ 'qmp-quick__chip--active': quickTimeIndex === index }"
                                @click="pickQuickTime(index)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </section>
                </template>

                <!-- 금액 — 최소 수익 (다시 누르면 해제) -->
                <template v-if="quickSection === 'all' || quickSection === 'amount'">
                    <section class="qmp-quick__section">
                        <p class="qmp-quick__label">금액</p>
                        <div class="qmp-quick__chips">
                            <button
                                v-for="preset in AMOUNT_PRESETS"
                                :key="preset.value"
                                type="button"
                                class="qmp-quick__chip"
                                :class="{ 'qmp-quick__chip--active': quickAmount === preset.value }"
                                @click="pickQuickAmount(preset.value)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </section>
                </template>

                <!-- 차량 — 등록 차량이 있으면 기본 차량이 선택되고 다른 차량으로 바꿀 수 있다 -->
                <template v-if="vehicles.length && (quickSection === 'all' || quickSection === 'vehicle')">
                    <section class="qmp-quick__section">
                        <p class="qmp-quick__label">차량</p>
                        <div class="qmp-quick__chips">
                            <button
                                v-for="car in vehicles"
                                :key="car.id"
                                type="button"
                                class="qmp-quick__chip"
                                :class="{ 'qmp-quick__chip--active': quickVehicleId === car.id }"
                                @click="pickQuickVehicle(car.id)"
                            >
                                {{ car.name || car.type || '등록 차량' }}
                                <span v-if="car.capacity" class="qmp-quick__chip-sub">{{ car.capacity }}인승</span>
                            </button>
                        </div>
                    </section>
                </template>

                <!-- 하단 액션 — 초기화(모든 조건 해제) / 저장 -->
                <div class="qmp-quick__actions">
                    <n-button size="small" secondary @click="resetQuickMatch">초기화</n-button>
                    <n-button type="primary" size="small" :loading="savingMatch" @click="saveQuickMatch">저장</n-button>
                </div>
            </div>
        </n-modal>
    </div>
</template>

<style scoped>
.qmp-card {
    padding: 12px 16px;
}
.qmp-card__row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.qmp-card__main {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0;
    border: 0;
    background: none;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
}
.qmp-card__text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}
.qmp-card__title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 800;
}
.qmp-card__flash {
    color: var(--brand);
}
.qmp-card__state {
    font-style: normal;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}
.qmp-card__sub {
    color: var(--text-muted);
    font-size: 11px;
}
.qmp-card__manage {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

/* 토글 스위치 */
.qmp-switch {
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
.qmp-switch:disabled {
    opacity: 0.6;
}
.qmp-switch span {
    position: absolute;
    width: 21px;
    height: 21px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.28);
    left: 3px;
    top: 3px;
    transition: transform 0.18s ease;
}
.qmp-switch--on {
    background: var(--brand);
}
.qmp-switch--on span {
    transform: translateX(19px);
}
html.dark .qmp-switch {
    background: #3a4146;
}
html.dark .qmp-switch span {
    background: #8b9490;
    box-shadow: none;
}
html.dark .qmp-switch--on span {
    background: #07120e;
}

/* 원큐 설정 — 활성 조건이 없을 때 노출 */
.qmp-quick {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}
.qmp-quick__title {
    font-size: 11px;
    color: var(--text-muted);
}
.qmp-quick__row {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.qmp-quick__row::-webkit-scrollbar {
    display: none;
}
.qmp-quick__btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    font-family: inherit;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.qmp-quick__btn b {
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
}
.qmp-quick__btn span {
    font-size: 10px;
    color: var(--text-muted);
}
.qmp-quick__btn:hover:not(:disabled) {
    border-color: var(--brand);
}
.qmp-quick__btn:disabled {
    opacity: 0.6;
    cursor: default;
}

/* 조건 요약 칩 — 한 줄로 나열하고 넘치면 가로 스크롤 */
.qmp-card__chips {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding-top: 12px;
    padding-bottom: 2px;
    border-top: 1px solid var(--border);
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.qmp-card__chips::-webkit-scrollbar {
    display: none;
}
.qmp-chip-group {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.qmp-chip-group + .qmp-chip-group {
    border-left: 1px solid var(--border);
    padding-left: 10px;
    margin-left: 4px;
}
.qmp-chip-group b {
    color: var(--brand);
    margin-right: 4px;
}
.qmp-chip-btn {
    padding: 0;
    border: 0;
    background: none;
    font-family: inherit;
    white-space: nowrap;
    cursor: pointer;
    border-radius: 999px;
    transition: opacity 0.15s ease;
}
.qmp-chip-btn:hover {
    opacity: 0.78;
}
.qmp-chip-next {
    margin: 0 3px;
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}

/* 매칭된 운행 목록 */
.qmp-section {
    margin-top: 18px;
}
.qmp-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.qmp-section__title {
    font-size: 11px;
    font-weight: 800;
}
.qmp-section__meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.qmp-section__count {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}
.qmp-refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    padding: 0;
    transition: color 0.15s ease, border-color 0.15s ease;
}
.qmp-refresh:disabled {
    opacity: 0.5;
    cursor: default;
}
.qmp-refresh__icon--spin {
    animation: qmp-spin 0.9s linear infinite;
}
@keyframes qmp-spin {
    to {
        transform: rotate(360deg);
    }
}
.qmp-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}
</style>

<!-- 조건 모달 — body에 텔레포트되므로 전역 스타일 필요 (scoped 적용 안 됨) -->
<style>
.qmp-quick {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.qmp-quick__section {
    display: flex;
    flex-direction: column;
    gap: 13px;
}
.qmp-quick__section + .qmp-quick__section {
    border-top: 1px solid var(--border);
    padding-top: 20px;
}
.qmp-quick__label {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.2px;
}
.qmp-quick__label::before {
    content: '';
    flex-shrink: 0;
    width: 3px;
    height: 12px;
    border-radius: 2px;
    background: var(--brand);
}
.qmp-quick__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.qmp-quick__chip {
    padding: 6px 13px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: transparent;
    color: var(--text-muted);
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
@media (hover: hover) {
    .qmp-quick__chip:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}
.qmp-quick__chip--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
    font-weight: 700;
}
.qmp-quick__chip--more {
    border-style: dashed;
    color: var(--text-muted);
}
.qmp-quick__chip--remove {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 8px 6px 13px;
    cursor: default;
}
.qmp-quick__chip-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: var(--brand);
    cursor: pointer;
}
@media (hover: hover) {
    .qmp-quick__chip-del:hover {
        background: color-mix(in srgb, var(--brand) 12%, transparent);
    }
}
.qmp-quick__sub {
    border-top: 1px solid var(--border);
    padding-top: 12px;
    margin-top: 14px;
}
.qmp-quick__sub-label {
    margin: 0 0 8px;
    font-size: 10.5px;
    font-weight: 700;
    color: var(--text-muted);
}
.qmp-quick__add {
    display: flex;
    gap: 6px;
    width: 100%;
}
.qmp-quick__add-input {
    flex: 1;
    min-width: 0;
}
.qmp-quick__chip-sub {
    margin-left: 5px;
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}
.qmp-quick__actions {
    display: flex;
    gap: 8px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}
.qmp-quick__actions .n-button:last-child {
    flex: 1;
}
</style>
