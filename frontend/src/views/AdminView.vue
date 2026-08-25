<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import {
    apiAdminAutoOrderSettings,
    apiAdminAutoOrders,
    apiAdminDeleteAutoOrders,
    apiAdminDrivers,
    apiAdminSetDriverStatus,
    apiAdminUpdateAutoOrderSettings,
    apiAdminUpdateVerification,
    apiAdminUsers,
} from '../api/admin';
import { getApiErrorMessage } from '../api/client';
import { statusColorVar } from '../utils/colors';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const message = useMessage();
const loading = ref(true);
const actingId = ref(null);
const users = ref([]);
const meta = ref({ total: 0 });
const page = ref(1);
const pageSize = 20;

const tab = ref('users');

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiAdminUsers({ page: page.value });
        users.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        message.error(getApiErrorMessage(e, '사용자 목록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const changeVerification = async (user, field) => {
    actingId.value = user.id;
    const payload = {};

    if (field === 'vehicle') {
        payload.vehicle = !user.is_vehicle_verified;
    } else {
        payload.license = !user.is_license_verified;
    }

    try {
        const { data } = await apiAdminUpdateVerification(user.id, payload);
        user.is_vehicle_verified = data.data.is_vehicle_verified;
        user.is_license_verified = data.data.is_license_verified;
        message.success('인증 상태가 업데이트되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '인증 상태 변경에 실패했습니다.'));
    } finally {
        actingId.value = null;
    }
};

// ── 기사 관리 ──
const drivers = ref([]);
const driverMeta = ref({ total: 0 });
const driverPage = ref(1);
const driverStatusBusy = ref(null);

const STATUS_OPTIONS = [
    { value: 'online', label: '온라인' },
    { value: 'offline', label: '오프라인' },
    { value: 'rest', label: '휴식' },
    { value: 'on_trip', label: '운행 중' },
];

const loadDrivers = async () => {
    loading.value = true;

    try {
        const { data } = await apiAdminDrivers({ page: driverPage.value });
        drivers.value = data.data;
        driverMeta.value = data.meta;
    } catch (e) {
        message.error(getApiErrorMessage(e, '기사 목록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const changeDriverStatus = async (driver, status) => {
    driverStatusBusy.value = driver.id;

    try {
        const { data } = await apiAdminSetDriverStatus(driver.id, status);
        driver.status = data.data.status;
        driver.status_label = data.data.status_label;
        message.success(`${driver.name} 기사 상태를 '${data.data.status_label}'(으)로 변경했습니다.`);
    } catch (e) {
        message.error(getApiErrorMessage(e, '기사 상태 변경에 실패했습니다.'));
    } finally {
        driverStatusBusy.value = null;
    }
};

const onDriversRefresh = () => {
    loadDrivers();
};

// ── 자동 운행 등록 설정 — 매일 관리자 계정으로 운행을 자동 등록 (중지/시작 가능) ──
const autoSetting = ref({
    is_active: false,
    min_count: 10,
    max_count: 20,
    owner_user_id: null,
    last_run_at: null,
});
const autoLoading = ref(false);
const autoSaving = ref(false);

const loadAutoSetting = async () => {
    autoLoading.value = true;

    try {
        const { data } = await apiAdminAutoOrderSettings();
        autoSetting.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '자동 등록 설정을 불러오지 못했습니다.'));
    } finally {
        autoLoading.value = false;
    }
};

// 등록 계정 후보 — 관리자(Admin/Super Admin) 사용자만
const adminOwnerOptions = computed(() =>
    users.value
        .filter((u) => u.role === 'Admin' || u.role === 'Super Admin')
        .map((u) => ({ label: `${u.name} (${u.email})`, value: u.id })),
);

const saveAutoSetting = async () => {
    autoSaving.value = true;

    try {
        const payload = {
            is_active: autoSetting.value.is_active,
            min_count: Math.max(1, Number(autoSetting.value.min_count) || 10),
            max_count: Math.max(1, Number(autoSetting.value.max_count) || 20),
            owner_user_id: autoSetting.value.owner_user_id ?? null,
        };

        const { data } = await apiAdminUpdateAutoOrderSettings(payload);
        autoSetting.value = data.data;
        message.success(autoSetting.value.is_active ? '자동 운행 등록이 시작되었습니다.' : '자동 운행 등록이 중지되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '자동 등록 설정 저장에 실패했습니다.'));
    } finally {
        autoSaving.value = false;
    }
};

const formatRunAt = (value) => {
    if (!value) {
        return '아직 실행 전';
    }

    return new Date(value).toLocaleString('ko-KR');
};

// ── 자동 등록 운행 이력 — 자동 등록으로 생성된 운행을 확인하고 관리 ──
const autoOrders = ref([]);
const autoOrdersLoading = ref(false);
const autoDeleting = ref(false);

const loadAutoOrders = async () => {
    autoOrdersLoading.value = true;

    try {
        const { data } = await apiAdminAutoOrders();
        autoOrders.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '자동 등록 이력을 불러오지 못했습니다.'));
    } finally {
        autoOrdersLoading.value = false;
    }
};

const deleteAutoOrder = async (id) => {
    autoDeleting.value = true;

    try {
        const { data } = await apiAdminDeleteAutoOrders({ ids: [id] });
        autoOrders.value = autoOrders.value.filter((o) => o.id !== id);
        message.success(`${data.data.deleted}건 삭제되었습니다.`);
    } catch (e) {
        message.error(getApiErrorMessage(e, '운행 삭제에 실패했습니다.'));
    } finally {
        autoDeleting.value = false;
    }
};

const deleteAllAutoOrders = async () => {
    autoDeleting.value = true;

    try {
        const { data } = await apiAdminDeleteAutoOrders({ all: true });
        autoOrders.value = [];
        message.success(`자동 등록 운행 ${data.data.deleted}건이 삭제되었습니다.`);
    } catch (e) {
        message.error(getApiErrorMessage(e, '전체 삭제에 실패했습니다.'));
    } finally {
        autoDeleting.value = false;
    }
};

const autoStatusColor = (order) => statusColorVar[order.status] ?? 'var(--status-draft)';

const onTabChange = (name) => {
    if (name === 'drivers') {
        loadDrivers();
    } else if (name === 'auto') {
        loadAutoSetting();
        loadAutoOrders();
    } else {
        load();
    }
};

onMounted(() => {
    load();
    window.addEventListener('app:drivers-refresh', onDriversRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener('app:drivers-refresh', onDriversRefresh);
});
</script>

<template>
    <div>
        <div class="page-head">
            <div>
                <p class="page-head__eyebrow">Admin</p>
                <h1 class="page-head__title">운영 관리</h1>
                <p class="page-head__desc">드라이버 차량·면허 인증을 승인하거나 해제합니다.</p>
            </div>
        </div>

        <n-tabs v-model:value="tab" type="line" animated @update:value="onTabChange">
            <n-tab-pane name="users" tab="사용자 관리">
                <div v-if="loading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row">
                        <div class="admin-skeleton__line">
                            <div class="sk-line sk-line--md" style="width: 45%" />
                            <div class="sk-line" style="width: 65%; margin-top: 8px" />
                        </div>
                        <div class="admin-skeleton__chips">
                            <div class="sk-chip" />
                            <div class="sk-chip" />
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div class="admin-list">
                        <div v-for="user in users" :key="user.id" class="admin-user">
                            <div class="admin-user__main">
                                <div class="admin-user__name">
                                    <strong>{{ user.name }}</strong>
                                    <n-tag size="small" round :type="user.role === 'Super Admin' ? 'error' : user.role === 'Admin' ? 'warning' : 'default'">
                                        {{ user.role }}
                                    </n-tag>
                                </div>
                                <p class="admin-user__meta">{{ user.email }} · 가입 {{ user.created_at }} · 완료 {{ user.completed_count }}건</p>
                            </div>
                            <div class="admin-user__verify">
                                <div class="admin-user__verify-row">
                                    <span class="admin-user__verify-label">차량</span>
                                    <n-button
                                        size="small"
                                        :type="user.is_vehicle_verified ? 'success' : 'default'"
                                        round
                                        :loading="actingId === user.id"
                                        @click="changeVerification(user, 'vehicle')"
                                    >
                                        <template v-if="user.is_vehicle_verified"><BaseIcon name="check" :size="12" /> 인증됨</template>
                                        <template v-else>승인</template>
                                    </n-button>
                                </div>
                                <div class="admin-user__verify-row">
                                    <span class="admin-user__verify-label">면허</span>
                                    <n-button
                                        size="small"
                                        :type="user.is_license_verified ? 'success' : 'default'"
                                        round
                                        :loading="actingId === user.id"
                                        @click="changeVerification(user, 'license')"
                                    >
                                        <template v-if="user.is_license_verified"><BaseIcon name="check" :size="12" /> 인증됨</template>
                                        <template v-else>승인</template>
                                    </n-button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="meta.last_page > 1" class="admin-pager">
                        <n-button size="small" :disabled="page <= 1" @click="page--; load()">이전</n-button>
                        <span class="admin-pager__info">{{ page }} / {{ meta.last_page }} (총 {{ meta.total }}명)</span>
                        <n-button size="small" :disabled="page >= meta.last_page" @click="page++; load()">다음</n-button>
                    </div>

                    <EmptyState v-if="users.length === 0" title="사용자가 없습니다" hint="등록된 사용자가 없습니다." />
                </template>
            </n-tab-pane>

            <n-tab-pane name="drivers" tab="기사 관리">
                <div v-if="loading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row">
                        <div class="admin-skeleton__line">
                            <div class="sk-line sk-line--md" style="width: 45%" />
                            <div class="sk-line" style="width: 65%; margin-top: 8px" />
                        </div>
                        <div class="admin-skeleton__chips">
                            <div class="sk-chip" />
                            <div class="sk-chip" />
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div class="admin-list">
                        <div v-for="driver in drivers" :key="driver.id" class="admin-user">
                            <div class="admin-user__main">
                                <div class="admin-user__name">
                                    <strong>{{ driver.name }}</strong>
                                    <n-tag size="small" round :type="driver.status === 'on_trip' ? 'success' : driver.status === 'rest' ? 'warning' : 'default'">
                                        {{ driver.status_label }}
                                    </n-tag>
                                </div>
                                <p class="admin-user__meta">
                                    {{ driver.phone || driver.email }} · 차량 {{ driver.vehicle_count }}대
                                    <template v-if="driver.today_completed"> · 오늘 {{ driver.today_completed }}건 · {{ driver.today_income.toLocaleString() }}원</template>
                                    <template v-else> · 오늘 운행 없음</template>
                                </p>
                                <div class="admin-user__verify">
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">차량</span>
                                        <span class="admin-user__verify-label" :class="{ 'admin-user__verify-label--ok': driver.is_vehicle_verified }">
                                            <template v-if="driver.is_vehicle_verified"><BaseIcon name="check" :size="12" /> 인증</template>
                                            <template v-else>미인증</template>
                                        </span>
                                        <span class="admin-user__verify-label">면허</span>
                                        <span class="admin-user__verify-label" :class="{ 'admin-user__verify-label--ok': driver.is_license_verified }">
                                            <template v-if="driver.is_license_verified"><BaseIcon name="check" :size="12" /> 인증</template>
                                            <template v-else>미인증</template>
                                        </span>
                                    </div>
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">상태 전환</span>
                                        <n-select
                                            :value="driver.status"
                                            :options="STATUS_OPTIONS"
                                            size="small"
                                            style="width: 110px"
                                            :disabled="driverStatusBusy === driver.id"
                                            @update:value="(value) => changeDriverStatus(driver, value)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="driverMeta.last_page > 1" class="admin-pager">
                        <n-button size="small" :disabled="driverPage <= 1" @click="driverPage--; loadDrivers()">이전</n-button>
                        <span class="admin-pager__info">{{ driverPage }} / {{ driverMeta.last_page }} (총 {{ driverMeta.total }}명)</span>
                        <n-button size="small" :disabled="driverPage >= driverMeta.last_page" @click="driverPage++; loadDrivers()">다음</n-button>
                    </div>

                    <EmptyState v-if="drivers.length === 0" title="기사가 없습니다" hint="등록된 기사가 없습니다." />
                </template>
            </n-tab-pane>

            <n-tab-pane name="auto" tab="자동 운행">
                <div v-if="autoLoading" class="admin-skeleton">
                    <div v-for="n in 2" :key="n" class="sk-card admin-skeleton__row">
                        <div class="sk-line sk-line--md" style="width: 40%" />
                        <div class="sk-line" style="width: 70%; margin-top: 10px" />
                    </div>
                </div>

                <div v-else class="auto-card">
                    <div class="auto-card__head">
                        <div>
                            <strong>매일 운행 자동 등록</strong>
                            <p class="auto-card__desc">매일 오전 9시에 관리자 계정으로 운행을 자동 등록합니다. 설정으로 언제든 중지/시작할 수 있습니다.</p>
                        </div>
                        <n-switch
                            v-model:value="autoSetting.is_active"
                            size="large"
                            :disabled="autoSaving"
                            :rail-style="({ checked }) => (checked ? { backgroundColor: 'var(--brand)' } : {})"
                        >
                            <template #checked>시작</template>
                            <template #unchecked>중지</template>
                        </n-switch>
                    </div>

                    <div class="auto-card__row">
                        <span class="auto-card__label">등록 계정</span>
                        <n-select
                            v-model:value="autoSetting.owner_user_id"
                            :options="adminOwnerOptions"
                            placeholder="기본 관리자 (가장 먼저 가입한 관리자)"
                            clearable
                            style="flex: 1"
                        />
                    </div>

                    <div class="auto-card__row">
                        <span class="auto-card__label">하루 등록 건수</span>
                        <div class="auto-card__count">
                            <n-input-number
                                v-model:value="autoSetting.min_count"
                                :min="1"
                                :max="50"
                                size="small"
                                style="width: 90px"
                            />
                            <span class="auto-card__tilde">~</span>
                            <n-input-number
                                v-model:value="autoSetting.max_count"
                                :min="1"
                                :max="50"
                                size="small"
                                style="width: 90px"
                            />
                            <span class="auto-card__unit">건</span>
                        </div>
                    </div>

                    <div class="auto-card__row">
                        <span class="auto-card__label">마지막 실행</span>
                        <span class="auto-card__value">{{ formatRunAt(autoSetting.last_run_at) }}</span>
                    </div>

                    <div class="auto-card__footer">
                        <n-button
                            type="primary"
                            round
                            :loading="autoSaving"
                            @click="saveAutoSetting"
                        >
                            설정 저장
                        </n-button>
                    </div>
                </div>

                <!-- 자동 등록 운행 이력 — 생성된 운행을 확인하고 관리 -->
                <div class="auto-history">
                    <div class="auto-history__head">
                        <div>
                            <strong>최근 자동 등록 이력</strong>
                            <p class="auto-history__desc">자동 등록으로 생성된 운행(최근 50건)을 확인하고 삭제할 수 있습니다.</p>
                        </div>
                        <div class="auto-history__actions">
                            <n-button size="small" quaternary round title="새로고침" @click="loadAutoOrders">
                                <BaseIcon name="refresh" :size="14" />
                            </n-button>
                            <n-button
                                size="small"
                                type="error"
                                round
                                :loading="autoDeleting"
                                :disabled="!autoOrders.length"
                                @click="deleteAllAutoOrders"
                            >
                                전체 삭제
                            </n-button>
                        </div>
                    </div>

                    <div v-if="autoOrdersLoading" class="auto-history__skeleton">
                        <div v-for="n in 3" :key="n" class="sk-card auto-history__row auto-history__row--skel">
                            <div class="sk-line sk-line--md" style="width: 40%" />
                            <div class="sk-line" style="width: 65%; margin-top: 8px" />
                        </div>
                    </div>

                    <p v-else-if="!autoOrders.length" class="auto-history__empty">
                        아직 자동 등록된 운행이 없습니다. 다음 실행(매일 오전 9시) 후 이곳에 표시됩니다.
                    </p>

                    <div v-else class="auto-history__list">
                        <div v-for="order in autoOrders" :key="order.id" class="auto-history__row">
                            <span
                                class="auto-history__status"
                                :style="{ background: autoStatusColor(order), borderColor: autoStatusColor(order) }"
                            >
                                {{ order.status_label }}
                            </span>
                            <div class="auto-history__main">
                                <strong class="auto-history__route">{{ order.route }}</strong>
                                <span class="auto-history__meta">
                                    {{ order.service_date }} {{ order.service_time }} · {{ order.customer_name }} · {{ order.created_at }}
                                </span>
                            </div>
                            <span class="auto-history__amount">{{ (order.expected_revenue ?? 0).toLocaleString() }}원</span>
                            <button
                                type="button"
                                class="auto-history__del"
                                title="삭제"
                                :disabled="autoDeleting"
                                @click="deleteAutoOrder(order.id)"
                            >
                                <BaseIcon name="trash" :size="15" />
                            </button>
                        </div>
                    </div>
                </div>
            </n-tab-pane>
        </n-tabs>
    </div>
</template>

<style scoped>
/* 로딩 스켈레톤 — admin-user 행 형태 */
.admin-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.admin-skeleton__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.admin-skeleton__line {
    flex: 1;
    min-width: 0;
}

.admin-skeleton__chips {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.admin-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.admin-user {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--card-bg);
}

.admin-user__main {
    min-width: 0;
}

.admin-user__name {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-user__meta {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 12px;
}

.admin-user__verify {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
}

.admin-user__verify-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.admin-user__verify-label {
    color: var(--text-muted);
    font-size: 12px;
}

.admin-user__verify-label--ok {
    color: var(--status-completed);
    font-weight: 700;
}

.admin-user__main > .admin-user__verify {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 14px;
    margin-top: 8px;
}

.admin-pager {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 16px;
}

.admin-pager__info {
    color: var(--text-muted);
    font-size: 13px;
}

/* 자동 운행 등록 설정 카드 */
.auto-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 18px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.auto-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.auto-card__head strong {
    font-size: 15px;
    font-weight: 800;
}

.auto-card__desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.5;
}

.auto-card__row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.auto-card__label {
    flex-shrink: 0;
    width: 92px;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
}

.auto-card__value {
    color: var(--text);
    font-size: 13px;
}

.auto-card__count {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
}

.auto-card__tilde {
    color: var(--text-muted);
    font-size: 13px;
}

.auto-card__unit {
    color: var(--text-muted);
    font-size: 13px;
}

.auto-card__footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 4px;
    border-top: 1px solid var(--border);
}

/* 자동 등록 운행 이력 */
.auto-history {
    margin-top: 16px;
    padding: 18px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.auto-history__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.auto-history__head strong {
    font-size: 15px;
    font-weight: 800;
}

.auto-history__desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.5;
}

.auto-history__actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.auto-history__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}

.auto-history__row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
}

.auto-history__row--skel {
    flex-direction: column;
    align-items: stretch;
    border: 0;
}

.auto-history__status {
    flex-shrink: 0;
    padding: 2px 8px;
    border-radius: 999px;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.auto-history__main {
    flex: 1;
    min-width: 0;
}

.auto-history__route {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
    font-weight: 700;
}

.auto-history__meta {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 11px;
}

.auto-history__amount {
    flex-shrink: 0;
    color: var(--brand);
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.auto-history__del {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--danger);
    cursor: pointer;
    transition: border-color 0.12s ease, background 0.12s ease;
}

.auto-history__del:hover {
    border-color: var(--danger);
    background: color-mix(in srgb, var(--danger) 8%, transparent);
}

.auto-history__del:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.auto-history__empty {
    margin: 14px 0 0;
    padding: 14px;
    border-radius: 10px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 12px;
    text-align: center;
}

@media (max-width: 480px) {
    .auto-card__row {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .auto-card__label {
        width: auto;
    }
}
</style>
