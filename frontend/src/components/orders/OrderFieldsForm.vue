<script setup>
import { computed, ref, watch } from 'vue';
import BaseIcon from '../common/BaseIcon.vue';
import { SERVICE_OPTIONS, VEHICLE_OPTIONS, weekdayOf } from '../../utils/orderCreate';

/**
 * 운행 입력 필드 — 직접 입력 폼과 AI 구조화 결과(수정 가능)가 같은 필드를 공유한다.
 * 모델 객체를 제자리에서 수정한다(부모의 reactive 객체를 그대로 v-model 로 연결).
 *
 * 마켓 공개에 반드시 필요한 값(출발·도착·일시·차량·구분·금액)을 먼저 보여주고,
 * 나머지는 '추가 입력'으로 접는다. 다만 값이 이미 있으면 접지 않는다 —
 * 해석된 값을 숨겨 두면 사람이 확인하지 않고 지나친다.
 */
const props = defineProps({
    model: {
        type: Object,
        required: true,
    },
});

const weekdayLabel = computed(() => weekdayOf(props.model.service_date));

const extraFields = ['flight_number', 'customer_name', 'customer_phone', 'passenger_count', 'luggage_count', 'is_priority'];

const isFilled = (value) => value !== null && value !== undefined && value !== '' && value !== false;

const filledExtraCount = computed(() => extraFields.filter((field) => isFilled(props.model[field])).length);

const extraOpen = ref(false);

// 값이 채워져 있으면 펼친 상태로 시작한다
watch(
    filledExtraCount,
    (count) => {
        if (count > 0) {
            extraOpen.value = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="order-fields">
        <n-form-item label="출발">
            <n-input v-model:value="model.pickup_location" placeholder="출발지" />
        </n-form-item>
        <n-form-item label="도착">
            <n-input v-model:value="model.dropoff_location" placeholder="도착지" />
        </n-form-item>
        <n-form-item label="날짜">
            <n-date-picker
                v-model:value="model.service_date"
                value-format="yyyy-MM-dd"
                type="date"
                placeholder="날짜 선택"
                :clearable="true"
                class="order-fields__full"
            />
            <n-tag v-if="weekdayLabel" size="small" round class="order-fields__weekday">
                {{ weekdayLabel }}
            </n-tag>
        </n-form-item>
        <n-form-item label="시간">
            <n-time-picker
                v-model:value="model.service_time"
                value-format="HH:mm"
                placeholder="시간 선택"
                class="order-fields__full"
            />
        </n-form-item>
        <n-form-item label="차량">
            <n-select
                v-model:value="model.vehicle_type"
                :options="VEHICLE_OPTIONS"
                placeholder="차량 선택 (직접 입력도 가능)"
                clearable
                filterable
                tag
            />
        </n-form-item>
        <n-form-item label="구분">
            <n-select v-model:value="model.service_type" :options="SERVICE_OPTIONS" placeholder="구분 선택" />
        </n-form-item>
        <n-form-item label="금액">
            <n-input-number v-model:value="model.expected_revenue" :min="0" class="order-fields__full" placeholder="금액" />
        </n-form-item>

        <button type="button" class="order-fields__toggle" @click="extraOpen = !extraOpen">
            <BaseIcon
                class="order-fields__chevron"
                :class="{ 'order-fields__chevron--collapsed': !extraOpen }"
                name="chevron-down"
                :size="14"
            />
            추가 입력
            <span v-if="filledExtraCount" class="order-fields__count">{{ filledExtraCount }}</span>
        </button>

        <div v-show="extraOpen" class="order-fields__extra">
            <n-form-item label="항공편">
                <n-input v-model:value="model.flight_number" placeholder="예) KE101" />
            </n-form-item>
            <n-form-item label="고객명">
                <n-input v-model:value="model.customer_name" placeholder="예) 홍길동" />
            </n-form-item>
            <n-form-item label="고객 연락처">
                <!-- inputmode는 n-input의 prop이 아니라 input-props로 넘겨야 내부 <input>에 적용된다 -->
                <n-input
                    v-model:value="model.customer_phone"
                    placeholder="예) 010-1234-5678"
                    :input-props="{ inputmode: 'tel' }"
                />
            </n-form-item>
            <n-form-item label="인원">
                <n-input-number v-model:value="model.passenger_count" :min="0" class="order-fields__full" />
            </n-form-item>
            <n-form-item label="짐">
                <n-input-number v-model:value="model.luggage_count" :min="0" class="order-fields__full" />
            </n-form-item>
            <n-form-item label="긴급">
                <n-checkbox v-model:checked="model.is_priority">긴급 운행으로 등록</n-checkbox>
            </n-form-item>
        </div>
    </div>
</template>

<style scoped>
.order-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0 16px;
}

.order-fields__full {
    width: 100%;
}

.order-fields__weekday {
    margin-top: 6px;
}

/* 추가 입력 펼치기 — 필수 항목과 시각적으로 분리한다 */
.order-fields__toggle {
    grid-column: 1 / -1;
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: 6px;
    margin: 4px 0 10px;
    padding: 6px 0;
    border: none;
    background: none;
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

@media (hover: hover) {
    .order-fields__toggle:hover {
        color: var(--brand);
    }
}

.order-fields__chevron {
    transition: transform 0.2s ease;
}

.order-fields__chevron--collapsed {
    transform: rotate(-90deg);
}

/* 채워진 추가 항목 개수 — 접혀 있어도 값이 있다는 것을 알린다 */
.order-fields__count {
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.order-fields__extra {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0 16px;
}
</style>
