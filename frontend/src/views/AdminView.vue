<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiAdminDrivers, apiAdminSetDriverStatus, apiAdminUpdateVerification, apiAdminUsers } from '../api/admin';
import { getApiErrorMessage } from '../api/client';

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

        <n-tabs v-model:value="tab" type="line" animated @update:value="tab === 'drivers' ? loadDrivers() : load()">
            <n-tab-pane name="users" tab="사용자 관리">
                <n-spin :show="loading">
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
                                        {{ user.is_vehicle_verified ? '인증됨 ✓' : '승인' }}
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
                                        {{ user.is_license_verified ? '인증됨 ✓' : '승인' }}
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

                    <n-empty v-if="!loading && users.length === 0" description="사용자가 없습니다." :image-size="80" />
                </n-spin>
            </n-tab-pane>

            <n-tab-pane name="drivers" tab="기사 관리">
                <n-spin :show="loading">
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
                                            {{ driver.is_vehicle_verified ? '✓ 인증' : '미인증' }}
                                        </span>
                                        <span class="admin-user__verify-label">면허</span>
                                        <span class="admin-user__verify-label" :class="{ 'admin-user__verify-label--ok': driver.is_license_verified }">
                                            {{ driver.is_license_verified ? '✓ 인증' : '미인증' }}
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

                    <n-empty v-if="!loading && drivers.length === 0" description="드라이버가 없습니다." :image-size="80" />
                </n-spin>
            </n-tab-pane>
        </n-tabs>
    </div>
</template>

<style scoped>
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
</style>
