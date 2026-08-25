<script setup>
import { onActivated, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { useDriverStore } from '../stores/driver';
import { useMatchSettings } from '../composables/useMatchSettings';
import { useMatchCalling } from '../composables/useMatchCalling';
import OrderCard from '../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'MatchView' });

const message = useMessage();
const driver = useDriverStore();

// ── 매칭된 운행 (내 조건에 맞는 운행) ──
const matchedOrders = ref([]);
const ordersLoading = ref(true);

const loadMatchedOrders = async () => {
    ordersLoading.value = true;

    try {
        const { data } = await apiOrders({ scope: 'market', matched: 1, per_page: 100, sort: 'latest' });
        matchedOrders.value = Array.isArray(data?.data) ? data.data.filter((order) => order.is_matched_to_me) : [];
    } catch {
        matchedOrders.value = [];
    } finally {
        ordersLoading.value = false;
    }
};

// ── 모듈: 매칭 설정 관리 / 콜링 상태 ──
const settings = useMatchSettings({ message, loadMatchedOrders });
const calling = useMatchCalling({ driver, matchPrefs: settings.matchPrefs, message });

const {
    matchPrefs, matchLoading, matchFormOpen, editingMatchId, savingMatch,
    DAY_LABELS, DATE_RANGES, TIME_PRESETS, AMOUNT_PRESETS, PASSENGER_PRESETS,
    applyTimePreset, applyAmountPreset, applyPassengerPreset,
    formPresets, applyFormPreset, removeFormPreset, matchForm,
    loadMatchPrefs, openMatchForm, closeMatchForm, saveMatch, removeMatch, toggleMatchActive,
    matchDayLabel, matchSummary,
} = settings;

const { isCalling, callingHint, toggleCalling } = calling;

onMounted(() => {
    driver.load();
    loadMatchedOrders();
    loadMatchPrefs();
});

// keep-alive 복귀 시 최신 매칭 상태로 갱신
onActivated(() => {
    driver.load();
    loadMatchedOrders();
    loadMatchPrefs();
});
</script>

<template>
    <div class="match-page">
        <!-- 페이지 헤더 — 다른 탭(내 운행 등)과 동일한 page-head 패턴 -->
        <div class="page-head">
            <p class="page-head__desc">내 조건에 맞는 운행을 자동으로 찾아드립니다.</p>
            <n-button type="primary" size="large" round @click="openMatchForm()">+ 매칭 등록</n-button>
        </div>

        <!-- 콜링 상태 — 로딩 아이콘으로 매칭 동작 중을 시각화, 스위치로 시작/중지 -->
        <section class="calling-bar" :class="{ 'calling-bar--off': !isCalling }">
            <div class="calling-bar__info">
                <span class="calling-icon" :class="{ 'calling-icon--pulse': isCalling }">
                    <BaseIcon name="match" :size="20" />
                </span>
                <div class="calling-bar__text">
                    <strong>{{ isCalling ? '콜링 중' : '콜링 꺼짐' }}</strong>
                    <small>{{ callingHint }}</small>
                </div>
            </div>
            <n-switch :value="driver.matchEnabled" size="medium" @update:value="toggleCalling" />
        </section>

        <!-- 매칭 설정 — 콜링 조건을 먼저 확인/관리할 수 있게 위로 -->
        <section class="match-page__section">
            <div class="match-page__head">
                <strong>매칭 설정</strong>
                <span class="match-page__hint">시간대·지역·가격 조건을 등록해 두세요</span>
            </div>

            <div v-if="matchPrefs.length" class="match-list">
                <div v-for="pref in matchPrefs" :key="pref.id" class="match-item">
                    <div class="match-item__main">
                        <div class="match-item__name-row">
                            <strong>{{ pref.name }}</strong>
                            <n-tag v-if="!pref.is_active" size="small" round type="default">비활성</n-tag>
                        </div>
                        <span class="match-item__meta">{{ matchSummary(pref) }}</span>
                    </div>
                    <div class="match-item__actions">
                        <n-switch :value="pref.is_active" size="small" @update:value="toggleMatchActive(pref)" />
                        <n-button size="small" quaternary @click="openMatchForm(pref)">수정</n-button>
                        <n-button size="small" quaternary type="error" @click="removeMatch(pref)">삭제</n-button>
                    </div>
                </div>
            </div>
            <EmptyState
                v-else-if="!matchLoading"
                icon="search"
                title="매칭 설정이 없습니다"
                hint="시간대·지역·가격 조건을 등록해 두면 자동으로 콜링해요"
            >
                <template #action>
                    <n-button type="primary" size="medium" round @click="openMatchForm()">+ 매칭 등록</n-button>
                </template>
            </EmptyState>
        </section>

        <!-- 나에게 매칭된 운행 -->
        <section class="match-page__section">
            <div class="match-page__head">
                <strong>나에게 매칭된 운행</strong>
                <span class="match-page__hint">내 조건에 맞는 운행이 여기에 모여요</span>
                <n-button size="small" quaternary @click="loadMatchedOrders()">새로고침</n-button>
            </div>

            <div v-if="ordersLoading" class="match-page__list">
                <OrderCardSkeleton v-for="n in 2" :key="n" />
            </div>
            <div v-else-if="matchedOrders.length" class="match-page__list">
                <OrderCard v-for="order in matchedOrders" :key="order.id" :order="order" />
            </div>
            <EmptyState
                v-else
                icon="truck"
                title="조건에 맞는 운행이 아직 없습니다"
                hint="매칭 설정을 등록해 두면 조건에 맞는 운행이 여기에 모여요"
            />
        </section>

        <!-- 매칭 설정 등록/수정 모달 -->
        <n-modal
            v-model:show="matchFormOpen"
            preset="card"
            :title="editingMatchId ? '매칭 설정 수정' : '매칭 설정 등록'"
            :style="{ maxWidth: '440px' }"
        >
            <n-form label-placement="top" label-width="auto">
                <n-form-item label="설정 이름" required>
                    <n-input v-model:value="matchForm.name" placeholder="예) 아침 공항 콜" />
                </n-form-item>
                <!-- 저장된 설정 태그 — 클릭하면 그 설정대로 폼이 채워진다 -->
                <div v-if="formPresets.length" class="match-form__presets">
                    <span class="match-form__presets-label">저장된 설정</span>
                    <div class="match-form__chips">
                        <span
                            v-for="(preset, index) in formPresets"
                            :key="index"
                            class="match-form__chip match-form__chip--preset"
                            @click="applyFormPreset(preset)"
                        >
                            {{ preset.name || '설정' }}
                            <em class="match-form__chip-remove" @click.stop="removeFormPreset(index)"><BaseIcon name="close" :size="12" /></em>
                        </span>
                    </div>
                </div>
                <n-form-item label="날짜 범위">
                    <div class="match-form__chips">
                        <button
                            v-for="range in DATE_RANGES"
                            :key="range.value || 'all'"
                            type="button"
                            class="match-form__chip"
                            :class="{ 'match-form__chip--active': matchForm.date_range === range.value }"
                            @click="matchForm.date_range = range.value"
                        >
                            {{ range.label }}
                        </button>
                    </div>
                </n-form-item>
                <n-form-item label="시간대 프리셋">
                    <div class="match-form__chips">
                        <button
                            v-for="preset in TIME_PRESETS"
                            :key="preset.label"
                            type="button"
                            class="match-form__chip"
                            @click="applyTimePreset(preset)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </n-form-item>
                <div class="match-form__row">
                    <n-form-item label="시작 시각" style="flex: 1">
                        <n-time-picker v-model:value="matchForm.start_time" format="HH:mm" style="width: 100%" />
                    </n-form-item>
                    <n-form-item label="종료 시각" style="flex: 1">
                        <n-time-picker v-model:value="matchForm.end_time" format="HH:mm" style="width: 100%" />
                    </n-form-item>
                </div>
                <n-form-item label="요일">
                    <n-checkbox-group v-model:value="matchForm.days">
                        <n-space item-style="display:flex">
                            <n-checkbox v-for="(label, index) in DAY_LABELS" :key="index" :value="index + 1">
                                {{ label }}
                            </n-checkbox>
                        </n-space>
                    </n-checkbox-group>
                </n-form-item>
                <n-form-item label="출발 지역">
                    <n-input v-model:value="matchForm.area" placeholder="예) 인천공항, 제주시 (비우면 전체)" />
                </n-form-item>
                <n-form-item label="최대 인원">
                    <div class="match-form__field">
                        <n-input-number
                            v-model:value="matchForm.max_passengers"
                            :min="1"
                            :max="99"
                            :step="1"
                            style="width: 100%"
                            placeholder="비우면 인원 제한 없음"
                        />
                        <div class="match-form__chips">
                            <button
                                v-for="preset in PASSENGER_PRESETS"
                                :key="preset.value"
                                type="button"
                                class="match-form__chip"
                                :class="{ 'match-form__chip--active': matchForm.max_passengers === preset.value }"
                                @click="applyPassengerPreset(preset.value)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>
                </n-form-item>
                <n-form-item label="최소 수익 (원)">
                    <div class="match-form__field">
                        <n-input-number v-model:value="matchForm.min_revenue" :min="0" :step="10000" style="width: 100%" />
                        <div class="match-form__chips">
                            <button
                                v-for="preset in AMOUNT_PRESETS"
                                :key="preset.value"
                                type="button"
                                class="match-form__chip"
                                :class="{ 'match-form__chip--active': matchForm.min_revenue === preset.value }"
                                @click="applyAmountPreset(preset.value)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>
                </n-form-item>
                <n-form-item label="매칭 활성화">
                    <n-switch v-model:value="matchForm.is_active" />
                </n-form-item>
            </n-form>
            <template #footer>
                <div class="match-form__footer">
                    <n-button @click="closeMatchForm">취소</n-button>
                    <n-button type="primary" :loading="savingMatch" @click="saveMatch">저장</n-button>
                </div>
            </template>
        </n-modal>
    </div>
</template>

<style scoped>
.match-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

/* ── 콜링 상태 배너 ── */
.calling-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    background: var(--brand-soft);
    border: 1px solid color-mix(in srgb, var(--brand) 30%, transparent);
}

.calling-bar--off {
    background: rgba(0, 0, 0, 0.04);
    border-color: var(--border);
}

html.dark .calling-bar--off {
    background: rgba(255, 255, 255, 0.05);
}

.calling-bar__info {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.calling-icon {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--surface);
    color: var(--brand);
    flex-shrink: 0;
}

.calling-icon svg {
    width: 20px;
    height: 20px;
}

/* 콜링 중 — 로딩 펄스 링 */
.calling-icon--pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: var(--brand);
    opacity: 0.5;
    animation: calling-ping 1.4s ease-out infinite;
}

.calling-icon--pulse svg {
    animation: calling-sweep 1.6s linear infinite;
}

@keyframes calling-ping {
    0% {
        transform: scale(1);
        opacity: 0.5;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

@keyframes calling-sweep {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.calling-bar--off .calling-icon {
    color: var(--text-muted);
    background: var(--surface);
}

.calling-bar__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.calling-bar__text strong {
    font-size: 15px;
}

.calling-bar__text small {
    color: var(--text-muted);
    font-size: 12px;
}

.match-page__section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.match-page__head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.match-page__head strong {
    font-size: 16px;
}

.match-page__hint {
    color: var(--text-muted);
    font-size: 12px;
    flex: 1;
    min-width: 0;
}

.match-page__list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.match-page__empty {
    padding: 24px 0;
}

/* 매칭 설정 목록 */
.match-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.match-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.match-item__main {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.match-item__name-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.match-item__meta {
    color: var(--text-muted);
    font-size: 12px;
}

.match-item__actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.match-form__row {
    display: flex;
    gap: 12px;
}

/* 날짜 범위/시간대 프리셋 칩 */
.match-form__presets {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    margin-bottom: 2px;
}

.match-form__presets-label {
    font-size: 12px;
    color: var(--text-muted);
}

.match-form__chip--preset {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    user-select: none;
}

.match-form__chip-remove {
    font-style: normal;
    font-size: 14px;
    line-height: 1;
    opacity: 0.55;
    cursor: pointer;
}

.match-form__chip-remove:hover {
    opacity: 1;
}

.match-form__field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.match-form__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
}

.match-form__chip {
    padding: 4px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: transparent;
    color: var(--text-muted);
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.match-form__chip:hover {
    border-color: var(--brand);
    color: var(--brand);
}

.match-form__chip--active {
    background: var(--brand);
    border-color: var(--brand);
    color: #ffffff;
    font-weight: 700;
}

.match-form__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>