<script setup>
import { onMounted, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '../../stores/ui';
import { useOrderSchedule } from '../../composables/useOrderSchedule';
import { useOrderForm } from '../../composables/useOrderForm';
import { useOrderSet } from '../../composables/useOrderSet';
import OrderCard from '../../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../../components/orders/OrderCardSkeleton.vue';
import OrderFieldsForm from '../../components/orders/OrderFieldsForm.vue';
import SetGroupCard from '../../components/orders/SetGroupCard.vue';
import BaseIcon from '../../components/common/BaseIcon.vue';
import EmptyState from '../../components/common/EmptyState.vue';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();
const message = useMessage();

// 화면 모드: 'list'(내 운행 스케줄) / 'form'(등록·수정 폼)
// 내 마켓의 "운행 등록"은 ?form=1 로 진입해 폼부터 시작한다
const screen = ref(route.params.id ? 'form' : (route.query.form === '1' ? 'form' : 'list'));

// 폼이면 하단 탭을 숨기고 헤더를 뒤로가기 형태로 바꾼다
watch(
    screen,
    (mode) => {
        ui.orderFormActive = mode === 'form';

        // 폼 진입 시 맨 위로 스크롤 — 목록 스크롤 위치가 남아 상단이 가려지지 않게
        if (mode === 'form') {
            window.scrollTo({ top: 0 });
        }
    },
    { immediate: true },
);

// 폼 뒤로가기 → 목록 복귀
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'order-form:back') {
            screen.value = 'list';
        }
    },
);

// ── 공유 상태 (목록/폼/셋트가 함께 사용) ──
const error = ref('');
const success = ref('');
const saving = ref(false);

// ── 모듈: 목록 스케줄 / 폼(등록·수정) / 셋트 ──
const schedule = useOrderSchedule({ error });
const orderForm = useOrderForm({
    route,
    screen,
    error,
    success,
    saving,
    loadMyOrders: schedule.loadMyOrders,
});
const orderSet = useOrderSet({
    saving,
    error,
    success,
    screen,
    publishNow: orderForm.publishNow,
    loadMyOrders: schedule.loadMyOrders,
    message,
});

// 템플릿 바인딩용 — 각 컴포저블에서 꺼낸다
const VIEW_MODES = [
    { label: '목록', value: 'list', icon: 'list' },
    { label: '캘린더', value: 'calendar', icon: 'calendar' },
];

const {
    myOrders, myOrdersLoading, page, pagination, listTab, listSearch, STATUS_TABS,
    collapsedGroups, groupUnit, GROUP_UNITS, setGroupUnit, toggleGroup,
    setRows, singleRows, scheduleRows, dateGroups, groupLabel, dateLabel, scheduleHint,
    loadFirstPage, handlePage, switchTab,
    viewMode, setViewMode, todayIso, calTitle, calDays, driveDates,
    selectedDate, selectedDateOrders, selectedDateLabel, prevMonth, nextMonth, selectDay,
} = schedule;

const {
    editId, isEdit, SERVICE_OPTIONS, VEHICLE_OPTIONS, summary, structuring, lineItems, publishNow, loading, mode,
    form, aiOrders, removeAiOrder, lineItemRows, structure, save, saveAiOrders, loadForEdit,
    ORDER_TAGS, customTag, toggleTag, addCustomTag, removeTag,
    templates, templateOpen, templateName, templateSaving, loadTemplates, applyTemplate, saveTemplate, removeTemplate,
} = orderForm;

const {
    setName, setLineItems, addSetLine, bulkInput, bulkError, VEHICLE_HINTS,
    convertBulkToSet, removeSetLine, saveSet,
} = orderSet;

// 카드 헤더 요약 — 여러 건을 나눠 등록할 때 어떤 운행인지 스크롤 중에도 알아볼 수 있게
const orderSummary = (order) => {
    const route = [order.pickup_location, order.dropoff_location].filter(Boolean).join(' → ');
    const when = [order.service_date, order.service_time].filter(Boolean).join(' ');

    return [route, when].filter(Boolean).join(' · ');
};

onMounted(() => {
    if (isEdit.value) {
        // 수정은 직접 입력 폼으로 진행한다 — AI 구조화는 새 운행을 만들 때만 쓴다
        mode.value = 'manual';
        loadForEdit();
    } else {
        loadFirstPage();
        loadTemplates();
    }
});
</script>

<template>
    <div class="page-shell">
        <!-- 내 운행 목록 — 받은 운행을 날짜별 스케줄로 표시 -->
        <template v-if="screen === 'list'">
            <div class="page-head">
                <div>
                    <p class="page-head__desc">지금 진행 중이거나 예약된 운행을 날짜별로 모아 보여줍니다. 끝난 운행은 운행 기록에서 확인하세요.</p>
                </div>
            </div>

            <!-- 상태 탭 — 좌측 상태, 우측에 목록/캘린더 전환 + 새로고침 -->
            <div class="status-tabs">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="status-tabs__btn"
                    :class="{ 'status-tabs__btn--active': listTab === tab.value }"
                    @click="switchTab(tab.value)"
                >
                    {{ tab.label }}
                </button>
                <div class="status-tabs__side">
                    <button
                        v-for="mode in VIEW_MODES"
                        :key="mode.value"
                        type="button"
                        class="status-tabs__icon"
                        :class="{ 'status-tabs__icon--active': viewMode === mode.value }"
                        :aria-label="`${mode.label} 보기`"
                        :title="`${mode.label} 보기`"
                        @click="setViewMode(mode.value)"
                    >
                        <BaseIcon :name="mode.icon" :size="15" />
                    </button>
                    <button
                        type="button"
                        class="status-tabs__refresh"
                        aria-label="새로고침"
                        title="새로고침"
                        @click="loadFirstPage"
                    >
                        <BaseIcon name="refresh" :size="15" />
                    </button>
                </div>
            </div>

            <!-- 보기 단위 전환 — 일별 / 월별 (목록 모드에서만 표시) -->
            <div v-if="viewMode === 'list'" class="status-tabs">
                <button
                    v-for="unit in GROUP_UNITS"
                    :key="unit.value"
                    type="button"
                    class="status-tabs__btn"
                    :class="{ 'status-tabs__btn--active': groupUnit === unit.value }"
                    @click="setGroupUnit(unit.value)"
                >
                    {{ unit.label }}
                </button>
            </div>
            <p v-if="viewMode === 'list'" class="schedule-mode-hint">{{ scheduleHint }}</p>

            <!-- 캘린더 보기 — 월 그리드 + 날짜별 운행 (일|월|연 단위는 숨김) -->
            <div v-if="viewMode === 'calendar'" class="cal">
                <div class="cal__head">
                    <button type="button" class="cal__nav" aria-label="이전 달" @click="prevMonth"><BaseIcon name="arrow-back" :size="16" /></button>
                    <b>{{ calTitle }}</b>
                    <button type="button" class="cal__nav" aria-label="다음 달" @click="nextMonth"><BaseIcon name="arrow-forward" :size="16" /></button>
                </div>
                <div class="cal__grid">
                    <div v-for="dow in ['월', '화', '수', '목', '금', '토', '일']" :key="dow" class="cal__dow">{{ dow }}</div>
                    <button
                        v-for="(cell, index) in calDays"
                        :key="index"
                        type="button"
                        class="cal__day"
                        :class="{
                            'cal__day--muted': cell.muted,
                            'cal__day--today': cell.date === todayIso,
                            'cal__day--selected': cell.date === selectedDate,
                        }"
                        :disabled="!cell.date"
                        @click="selectDay(cell.date)"
                    >
                        {{ cell.day }}
                        <span v-if="driveDates.has(cell.date)" class="cal__dot" />
                    </button>
                </div>
                <div class="cal__section-title">
                    <b>{{ selectedDateLabel }}</b>
                    <span class="cal__count">{{ selectedDateOrders.length }}건</span>
                </div>
                <div v-if="selectedDateOrders.length" class="schedule-list">
                    <template v-for="order in selectedDateOrders" :key="order.key">
                        <SetGroupCard v-if="order.kind === 'set'" :set="order" />
                        <OrderCard v-else :order="order" />
                    </template>
                </div>
                <EmptyState
                    v-else
                    icon="truck"
                    title="이 날짜에는 운행이 없습니다"
                    hint="다른 날짜를 선택해 보세요"
                />
            </div>

            <div v-if="viewMode === 'list' && myOrdersLoading" class="my-order-list">
                <OrderCardSkeleton v-for="n in 5" :key="n" />
            </div>
            <EmptyState
                v-else-if="viewMode === 'list' && !myOrders.length"
                icon="truck"
                title="가져온 운행이 없습니다"
                hint="마켓에서 조건에 맞는 운행을 가져와 보세요"
            >
                <template #action>
                    <n-button type="primary" round @click="router.push({ name: 'market' })">마켓 보러 가기</n-button>
                </template>
            </EmptyState>
            <div v-else-if="viewMode === 'list'" class="schedule-list">
                <div v-for="group in dateGroups" :key="group.key" class="schedule-group">
                    <button
                        type="button"
                        class="schedule-group__head"
                        :aria-expanded="!collapsedGroups.has(group.key)"
                        @click="toggleGroup(group.key)"
                    >
                        <span class="schedule-group__date">{{ groupLabel(group.key) }}</span>
                        <span class="schedule-group__right">
                            <span class="schedule-group__count">{{ group.rows.length }}건</span>
                            <BaseIcon
                                class="schedule-group__chevron"
                                :class="{ 'schedule-group__chevron--collapsed': collapsedGroups.has(group.key) }"
                                name="chevron-down"
                                :size="16"
                            />
                        </span>
                    </button>
                    <div v-show="!collapsedGroups.has(group.key)" class="schedule-group__body">
                        <template v-for="order in group.rows" :key="order.key">
                            <SetGroupCard v-if="order.kind === 'set'" :set="order" />
                            <OrderCard v-else :order="order" />
                        </template>
                    </div>
                </div>
            </div>

            <div v-if="viewMode === 'list' && pagination && pagination.last_page > 1" class="create-pagination">
                <n-pagination
                    :page="page"
                    :page-size="pagination.per_page"
                    :item-count="pagination.total"
                    @update:page="handlePage"
                />
            </div>
        </template>

        <!-- 등록/수정 폼 -->
        <div v-else>
            <div class="page-head">
                <p class="page-head__desc">요약 텍스트를 AI로 구조화하거나 직접 입력해 등록합니다.</p>
            </div>

            <div v-if="!isEdit" class="create-tabs">
            <n-radio-group v-model:value="mode" size="large">
                <n-radio-button value="ai">붙여넣기로 등록</n-radio-button>
                <n-radio-button value="manual">직접 입력</n-radio-button>
                <n-radio-button value="set">여러 건 묶어서</n-radio-button>
            </n-radio-group>
        </div>

        <n-spin :show="loading" class="create-body">
            <n-alert v-if="error" type="error" :show-icon="true" class="create-block">
                {{ error }}
            </n-alert>
            <n-alert v-if="success" type="success" :show-icon="true" class="create-block">
                {{ success }}
            </n-alert>

        <n-card v-if="mode === 'ai'" :bordered="true" class="create-block" title="운행 문구 붙여넣기">
            <p class="create-hint">
                위챗·카톡에 올라온 문구를 그대로 붙여넣으세요. 여러 건이면 알아서 나눠 드립니다.
            </p>
            <n-input
                v-model:value="summary"
                type="textarea"
                :rows="4"
                placeholder="예) 3.30送机 蚕室 3人 2行李 9万"
            />
            <n-button
                class="create-structure-btn"
                :loading="structuring"
                :disabled="!summary.trim()"
                @click="structure"
            >
                문구 해석하기
            </n-button>
        </n-card>

        <!-- 해석 결과 — 그 자리에서 고쳐 등록한다. 여러 건이면 각각 독립 운행이 된다 -->
        <template v-if="mode === 'ai'">
            <n-alert v-if="aiOrders.length > 1" type="info" :show-icon="true" class="create-block">
                문구에서 {{ aiOrders.length }}건을 찾았습니다. 각각 따로 등록되며, 필요 없는 건은 삭제할 수 있습니다.
            </n-alert>

            <n-card
                v-for="(order, index) in aiOrders"
                :key="index"
                :bordered="true"
                class="create-block"
            >
                <template #header>
                    <div class="create-order-head">
                        <strong>{{ aiOrders.length > 1 ? `운행 ${index + 1}` : '해석 결과' }}</strong>
                        <span v-if="orderSummary(order)" class="create-order-head__summary">{{ orderSummary(order) }}</span>
                    </div>
                </template>
                <template v-if="aiOrders.length > 1" #header-extra>
                    <n-button text type="error" @click="removeAiOrder(index)">삭제</n-button>
                </template>
                <n-form label-placement="top">
                    <OrderFieldsForm :model="order" />
                </n-form>
            </n-card>
        </template>

        <!-- 템플릿 — 직접 입력 폼에서 현재 입력값을 저장·재사용한다 (AI 결과·수정 모드 제외) -->
        <n-card v-if="mode === 'manual' && !isEdit" :bordered="true" class="create-block">
            <div class="template-head">
                <strong>템플릿</strong>
                <n-button size="small" secondary :disabled="!form.pickup_location && !form.dropoff_location" @click="templateOpen = true">
                    현재 입력 저장
                </n-button>
            </div>
            <div v-if="templates.length" class="template-list">
                <div v-for="tpl in templates" :key="tpl.id" class="template-chip">
                    <button type="button" class="template-chip__apply" @click="applyTemplate(tpl)">
                        <strong>{{ tpl.name }}</strong>
                        <span class="template-chip__meta">{{ tpl.pickup_location || '-' }} → {{ tpl.dropoff_location || '-' }}</span>
                    </button>
                    <button type="button" class="template-chip__del" aria-label="템플릿 삭제" @click="removeTemplate(tpl)">
                        <BaseIcon name="close" :size="12" />
                    </button>
                </div>
            </div>
            <p v-else class="template-empty">자주 쓰는 노선을 템플릿으로 저장해 한 번에 채워 보세요.</p>
        </n-card>

        <n-card v-if="mode === 'manual'" :bordered="true" class="create-block" title="운행 정보">
            <n-form label-placement="top" label-width="auto">
                <OrderFieldsForm :model="form" />
                <n-form-item label="태그">
                    <div class="create-tags">
                        <div class="create-tags__chips">
                            <button
                                v-for="tag in ORDER_TAGS"
                                :key="tag.value"
                                type="button"
                                class="create-tag-chip"
                                :class="{ 'create-tag-chip--active': form.tags.includes(tag.value) }"
                                @click="toggleTag(tag.value)"
                            >
                                {{ tag.label }}
                            </button>
                        </div>
                        <div v-if="form.tags.length" class="create-tags__selected">
                            <span
                                v-for="tag in form.tags"
                                :key="tag"
                                class="create-tag-chip create-tag-chip--selected"
                            >
                                {{ tag }}
                                <button
                                    type="button"
                                    class="create-tag-chip__del"
                                    aria-label="태그 삭제"
                                    @click="removeTag(tag)"
                                >
                                    <BaseIcon name="close" :size="11" />
                                </button>
                            </span>
                        </div>
                        <div class="create-tags__custom">
                            <n-input
                                v-model:value="customTag"
                                size="small"
                                placeholder="직접 입력 후 추가 — 운행을 어필하는 태그"
                                class="create-tags__input"
                                @keyup.enter="addCustomTag"
                            />
                            <n-button size="small" secondary :disabled="!customTag.trim()" @click="addCustomTag">추가</n-button>
                        </div>
                    </div>
                </n-form-item>
            </n-form>
        </n-card>

        <n-card v-if="isEdit && lineItems.length" :bordered="true" class="create-block" title="일정">
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

        <n-card v-if="mode === 'set'" :bordered="true" class="create-block" title="묶음 정보">
            <n-form label-placement="top">
                <n-form-item label="묶음 이름" required>
                    <n-input v-model:value="setName" placeholder="예) KLOOK 8월" />
                </n-form-item>
            </n-form>
        </n-card>

        <n-card v-if="mode === 'set'" :bordered="true" class="create-block" title="한 번에 입력">
            <n-input
                v-model:value="bulkInput"
                type="textarea"
                :rows="6"
                placeholder="각 줄이 하나의 운행이 됩니다.
예)
8/10 09:00 인천공항→명동 카니발 3명 200000원
8/10 14:00 강남에서 강릉 정동진 스타리아 4명 250000원
8/11 07:30 김포공항→판교 그랜저 2명 15만원"
            />
            <div v-if="bulkError" class="bulk-error">{{ bulkError }}</div>
            <n-button
                class="create-structure-btn"
                :disabled="!bulkInput.trim()"
                @click="convertBulkToSet"
            >
                운행으로 나누기
            </n-button>
            <p class="bulk-hint">
                날짜 / 시간 / 출발→도착 / 차량 / 인원 / 금액 — 알아서 해석됩니다. 변환 후 아래 '묶을 운행'에서 수정할 수 있습니다.
            </p>
        </n-card>

        <n-card v-if="mode === 'set' && setLineItems.length" bordered class="create-block" title="묶을 운행">
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
                            <n-select
                                v-model:value="item.vehicle_type"
                                :options="VEHICLE_OPTIONS"
                                placeholder="차량 선택 (직접 입력도 가능)"
                                clearable
                                filterable
                                tag
                            />
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
            + 운행 추가
        </n-button>

        <n-checkbox v-if="!isEdit" v-model:checked="publishNow" class="create-block">
            등록 즉시 마켓에 공개
        </n-checkbox>

        <p v-if="publishNow" class="create-hint create-hint--publish">
            출발·도착·일시·차량·구분·금액을 채우면 바로 공개됩니다. 비어 있는 운행은 초안으로 남습니다.
        </p>

        <n-button
            v-if="mode === 'ai'"
            type="primary"
            size="large"
            :loading="saving"
            :disabled="!aiOrders.length"
            @click="saveAiOrders"
        >
            {{ aiOrders.length > 1 ? `${aiOrders.length}건 등록` : '운행 등록' }}
        </n-button>

        <n-button v-else-if="mode === 'manual'" type="primary" size="large" :loading="saving" @click="save">
            {{ isEdit ? '수정 저장' : '운행 등록' }}
        </n-button>

        <n-button
            v-if="mode === 'set'"
            type="primary"
            size="large"
            :loading="saving"
            :disabled="!setName.trim() || !setLineItems.length"
            @click="saveSet"
        >
            {{ setLineItems.length ? `${setLineItems.length}건 묶어서 등록` : '묶어서 등록' }}
        </n-button>

        <!-- 템플릿 저장 모달 -->
        <n-modal v-model:show="templateOpen" preset="card" title="템플릿 저장" :style="{ maxWidth: '400px' }">
            <p class="template-modal__desc">현재 입력된 노선·차량·금액을 템플릿으로 저장합니다.</p>
            <n-input v-model:value="templateName" placeholder="템플릿 이름 (예: 강남 → 인천공항)" :maxlength="100" />
            <template #footer>
                <div class="filter-footer">
                    <n-button @click="templateOpen = false">취소</n-button>
                    <n-button type="primary" :loading="templateSaving" @click="saveTemplate">
                        저장
                    </n-button>
                </div>
            </template>
        </n-modal>
        </n-spin>
        </div>
    </div>
</template>

<style scoped>
/* 운행 등록 템플릿 */
.template-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.template-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.template-chip {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.template-chip__apply {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 9px 12px;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
}

.template-chip__meta {
    color: var(--text-muted);
    font-size: 11px;
}

.template-chip__del {
    padding: 8px 12px;
    border: none;
    background: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 11px;
}

.template-chip__del:hover {
    color: var(--danger);
}

.template-empty {
    margin: 0;
    color: var(--text-muted);
    font-size: 11px;
}

.template-modal__desc {
    margin: 0 0 12px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.6;
}
/* 한 번에 입력 (셋트) */
.bulk-error {
    margin: 8px 0 0;
    color: var(--badge-red);
    font-size: 11px;
    font-weight: 600;
}

.bulk-hint {
    margin: 10px 0 0;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.6;
}

.create-block {
    margin-bottom: 16px;
    border-radius: var(--card-radius);
}

.create-body {
    display: block;
}

/* 내가 등록한 운행 목록 */
.my-order-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

/* 상태 탭 — 내 마켓과 동일한 칩 스타일 */
.status-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: var(--chips-gap);
}

/* 탭 아래 하위 탭 행(보기 단위 등) — 위로 끌어올려 여백을 작게 (내마켓 하위 탭과 동일) */
.status-tabs + .status-tabs {
    margin-top: -4px;
}

.status-tabs__btn {
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

.status-tabs__btn--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

/* 상태 탭 우측 그룹 — 목록/캘린더 전환 + 새로고침 */
.status-tabs__side {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}

/* 상태 탭 우측 — 보기 전환(목록/캘린더)·새로고침 아이콘 버튼 (마켓 아이콘 버튼과 동일 스타일) */
.status-tabs__refresh,
.status-tabs__icon {
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

.status-tabs__refresh {
    margin-left: auto;
}

/* 활성 보기(목록/캘린더) — 선택 상태 강조 */
.status-tabs__icon--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

@media (hover: hover) {
    .status-tabs__refresh:hover,
    .status-tabs__icon:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}

/* 날짜 그룹 열림/펼침 안내 */
.schedule-mode-hint {
    margin: -4px 0 12px;
    font-size: 11px;
    color: var(--text-muted);
    text-align: center;
}

/* 스케줄러 — 날짜별 그룹 (하단 .schedule-list 정의가 통합·우선 — 위 중복 제거) */

.schedule-group__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    margin-bottom: 10px;
    padding: 6px 4px;
    border: none;
    background: transparent;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.schedule-group__right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.schedule-group__date {
    font-size: 11px;
    font-weight: 800;
    color: var(--text);
}

.schedule-group__count {
    font-size: 10px;
    font-weight: 400;
    background: var(--brand-soft);
    color: var(--brand);
    padding: 1px 6px;
    border-radius: 999px;
}

.schedule-group__chevron {
    width: 18px;
    height: 18px;
    color: var(--text-muted);
    transition: transform 0.2s ease;
}

.schedule-group__chevron--collapsed {
    transform: rotate(-90deg);
}

.schedule-group__body {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* 모달 푸터 — 템플릿 저장 등에서 공용 (flex 우측 정렬) */
.filter-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.create-structure-btn {
    margin-top: 12px;
}

/* 입력 안내 — 등록자가 무엇을 채워야 하는지 한 줄로 알려 준다 */
.create-hint {
    margin: 0 0 10px;
    color: var(--text-muted);
    font-size: 11.5px;
    line-height: 1.6;
}

.create-hint--publish {
    margin: -6px 0 14px;
}

/* 해석 결과 카드 헤더 — 제목 + 노선·일시 요약 */
.create-order-head {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.create-order-head strong {
    font-size: 12px;
    font-weight: 800;
}

.create-order-head__summary {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 400;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* 태그 — 운행 어필용 프리셋 칩 + 직접 입력 */
.create-tags {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    margin-bottom: 14px;
}
.create-tags__chips,
.create-tags__selected {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.create-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}
.create-tag-chip--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
}
.create-tag-chip--selected {
    padding-right: 6px;
    border-color: color-mix(in srgb, var(--brand) 35%, transparent);
    background: var(--brand-soft);
    color: var(--brand);
}
.create-tag-chip__del {
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
/* hover — 데스크톱에서만. 터치 기기는 탭 후 남는 포커스로 hover가 고정돼 '선택된 것처럼' 보이므로 제외 */
@media (hover: hover) {
    .create-tag-chip__del:hover {
        background: color-mix(in srgb, var(--brand) 12%, transparent);
    }
}
.create-tags__custom {
    display: flex;
    gap: 6px;
    width: 100%;
}
.create-tags__input {
    flex: 1;
    min-width: 0;
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
    font-size: 11px;
}

.schedule-date {
    color: var(--text-muted);
    font-size: 11px;
}

.schedule-card__route {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
}

.schedule-card__arrow {
    color: var(--accent);
    font-size: 12px;
    font-weight: 700;
}

.schedule-card__meta {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 11px;
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


/* ── 운행 캘린더 ── */
.cal {
    margin-bottom: 16px;
}
.cal__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 2px 12px;
    font-size: 11px;
}
.cal__head b {
    font-weight: 800;
    letter-spacing: -0.02em;
}
.cal__nav {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--brand);
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}
.cal__nav:hover {
    border-color: var(--brand);
    background: var(--brand-soft);
}
.cal__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 12px;
}
.cal__dow {
    text-align: center;
    color: var(--text-muted);
    font-size: 11px;
    padding-bottom: 4px;
}
.cal__day {
    position: relative;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text);
    font-size: 11px;
    cursor: pointer;
}
.cal__day--muted {
    color: var(--text-muted);
    background: transparent;
    border-color: transparent;
    cursor: default;
}
.cal__day--today {
    background: var(--brand);
    border-color: var(--brand);
    /* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝아 흰 글자 대비 약함 — 앱 표준 #07120e를 두 모드 공통 사용 */
    color: #07120e;
    font-weight: 800;
}
.cal__day--selected:not(.cal__day--today) {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
    font-weight: 700;
}
.cal__dot {
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--brand);
}
.cal__day--today .cal__dot {
    background: #07120e;
}
.cal__section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.cal__section-title b {
    font-size: 11px;
    font-weight: 800;
}
.cal__count {
    font-size: 11px;
    color: var(--text-muted);
}

</style>