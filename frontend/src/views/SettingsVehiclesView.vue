<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useDriverStore } from '../stores/driver';
import { useDriverWorkspace } from '../composables/useDriverWorkspace';
import BaseIcon from '../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsVehiclesView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// 모듈: 기사 운영(차량 관리) 재사용
const driver = useDriverStore();
const workspace = useDriverWorkspace({ message, driver });

const {
    vehicles, vehicleLoading, vehicleFormOpen, editingVehicleId, savingVehicle, vehicleForm,
    loadVehicles, openVehicleForm, closeVehicleForm, saveVehicle, removeVehicle,
} = workspace;

onMounted(() => {
    // 차량 설정 — 기사 전용
    if (auth.user?.role === 'Driver') {
        loadVehicles();
    }
});
</script>

<template>
    <div class="settings-page">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">차량 설정</h1>
                <p class="page-head__desc">운행 등록 시 내 차량 정보를 빠르게 채웁니다.</p>
            </div>
        </div>

        <n-card :bordered="true" class="settings-block">
            <div class="verify-head">
                <strong>내 차량</strong>
                <n-button size="small" type="primary" ghost @click="openVehicleForm()">+ 차량 등록</n-button>
            </div>

            <div v-if="vehicles.length" class="vehicle-list">
                <div v-for="vehicle in vehicles" :key="vehicle.id" class="vehicle-item">
                    <div class="vehicle-item__main">
                        <strong>{{ vehicle.name }}</strong>
                        <span class="vehicle-item__meta">
                            {{ vehicle.type || '차종 미지정' }}<template v-if="vehicle.license_plate"> · {{ vehicle.license_plate }}</template>
                            <template v-if="vehicle.capacity"> · {{ vehicle.capacity }}인승</template>
                            <template v-if="vehicle.luggage_capacity"> · 짐 {{ vehicle.luggage_capacity }}개</template>
                        </span>
                        <div class="vehicle-item__tags">
                            <n-tag v-if="vehicle.is_default" size="small" round type="info">기본</n-tag>
                            <n-tag v-if="vehicle.is_verified" size="small" round type="success">검증</n-tag>
                        </div>
                    </div>
                    <div class="vehicle-item__actions">
                        <n-button size="small" quaternary @click="openVehicleForm(vehicle)">수정</n-button>
                        <n-button size="small" quaternary type="error" @click="removeVehicle(vehicle)">삭제</n-button>
                    </div>
                </div>
            </div>
            <n-empty v-else-if="!vehicleLoading" description="등록된 차량이 없습니다." :image-size="60" />
        </n-card>

        <!-- 차량 등록/수정 모달 -->
        <n-modal
            v-model:show="vehicleFormOpen"
            preset="card"
            :title="editingVehicleId ? '차량 수정' : '차량 등록'"
            :style="{ maxWidth: '440px' }"
            @after-leave="closeVehicleForm"
        >
            <n-form label-placement="top" label-width="auto">
                <n-form-item label="차량 이름" required>
                    <n-input v-model:value="vehicleForm.name" placeholder="예) 내 카니발" />
                </n-form-item>
                <n-form-item label="차종">
                    <n-input v-model:value="vehicleForm.type" placeholder="예) 카니발" />
                </n-form-item>
                <div class="vehicle-form__row">
                    <n-form-item label="번호판" style="flex: 1">
                        <n-input v-model:value="vehicleForm.license_plate" placeholder="예) 12가3456" />
                    </n-form-item>
                    <n-form-item label="색상" style="flex: 1">
                        <n-input v-model:value="vehicleForm.color" placeholder="예) 화이트" />
                    </n-form-item>
                </div>
                <div class="vehicle-form__row">
                    <n-form-item label="승차정원" style="flex: 1">
                        <n-input-number v-model:value="vehicleForm.capacity" :min="0" :max="99" style="width: 100%" />
                    </n-form-item>
                    <n-form-item label="짐 개수" style="flex: 1">
                        <n-input-number v-model:value="vehicleForm.luggage_capacity" :min="0" :max="99" style="width: 100%" />
                    </n-form-item>
                </div>
                <n-form-item label="보험 만료일">
                    <n-date-picker v-model:value="vehicleForm.insurance_expires_at" type="date" style="width: 100%" />
                </n-form-item>
                <n-form-item label="기본 차량">
                    <n-switch v-model:value="vehicleForm.is_default" />
                </n-form-item>
            </n-form>
            <template #footer>
                <div class="vehicle-form__footer">
                    <n-button @click="closeVehicleForm">취소</n-button>
                    <n-button type="primary" :loading="savingVehicle" @click="saveVehicle">저장</n-button>
                </div>
            </template>
        </n-modal>
    </div>
</template>

<style scoped>
/* 페이지 폭 — 홈·더보기와 동일 패턴 */
.settings-page {
    width: 100%;
    max-width: 880px;
    margin: 0 auto;
    padding: 8px 20px 24px;
}

@media (max-width: 480px) {
    .settings-page {
        width: calc(100% + 40px);
        margin: 0 -20px;
        padding: 8px 14px 24px;
        max-width: none;
    }
}

.settings-block {
    margin-bottom: 14px;
    border-radius: 16px;
}

/* 뒤로가기 */
.settings-back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    margin-left: -10px;
    border: 0;
    background: none;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.12s ease;
}

.settings-back:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

/* ── 차량 ── */
.vehicle-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.vehicle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
}

.vehicle-item__main {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.vehicle-item__meta {
    color: var(--text-muted);
    font-size: 12px;
}

.vehicle-item__tags {
    display: flex;
    gap: 4px;
}

.vehicle-item__actions {
    display: flex;
    flex-shrink: 0;
}

.vehicle-form__row {
    display: flex;
    gap: 12px;
}

.vehicle-form__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
