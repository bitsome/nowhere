<script setup>
import { onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiAdminUpdateVerification, apiAdminUsers } from '../api/admin';
import { getApiErrorMessage } from '../api/client';

const message = useMessage();
const loading = ref(true);
const actingId = ref(null);
const users = ref([]);
const meta = ref({ total: 0 });
const page = ref(1);
const pageSize = 20;

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

onMounted(load);
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
