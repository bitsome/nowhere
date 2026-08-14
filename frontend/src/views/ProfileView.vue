<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useDriverStore } from '../stores/driver';
import { apiUpdateProfile } from '../api/auth';
import { apiCommunityUser } from '../api/community';
import { apiCreateVehicle, apiDeleteVehicle, apiDriverStats, apiMyVehicles, apiUpdateVehicle } from '../api/driver';
import { apiClient, getApiErrorMessage } from '../api/client';
import {
    isBrowserNotifyEnabled,
    requestNotifyPermission,
    setBrowserNotifyEnabled,
} from '../utils/browserNotify';
import LevelBadge from '../components/LevelBadge.vue';

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

const saving = ref(false);
const requesting = ref('');
const error = ref('');
const success = ref('');

// 브라우저 알림 설정
const notifyEnabled = ref(isBrowserNotifyEnabled());

const toggleNotify = async (enabled) => {
    if (enabled && 'Notification' in window && Notification.permission === 'default') {
        const granted = await requestNotifyPermission();

        if (!granted) {
            notifyEnabled.value = false;
            setBrowserNotifyEnabled(false);
            message.error('브라우저 알림 권한이 거부되었습니다. 브라우저 설정에서 허용해 주세요.');

            return;
        }
    }

    setBrowserNotifyEnabled(enabled);
    message.success(enabled ? '브라우저 알림이 켜졌습니다.' : '브라우저 알림이 꺼졌습니다.');
};
const myStats = ref(null);

// ── 기사 운영 — 가용 상태 / 오늘 통계 / 차량 관리 ──
const driver = useDriverStore();
const todayStats = ref(null);
const vehicles = ref([]);
const vehicleLoading = ref(true);
const vehicleFormOpen = ref(false);
const editingVehicleId = ref(null);
const savingVehicle = ref(false);

const vehicleForm = reactive({
    name: '',
    type: '',
    license_plate: '',
    color: '',
    capacity: 0,
    luggage_capacity: 0,
    insurance_expires_at: null,
    is_default: false,
});

const isDriver = computed(() => auth.user?.role === 'Driver');

// 온라인/오프라인 토글 (운행 중이면 끌 수 없다)
const toggleDriverStatus = async () => {
    if (driver.status === 'on_trip') {
        message.warning('운행 중에는 상태를 변경할 수 없습니다.');

        return;
    }

    try {
        await driver.setStatus(driver.isOnline ? 'offline' : 'online');
        message.success(driver.status === 'online' ? '온라인으로 전환되었습니다.' : '오프라인으로 전환되었습니다.');
        loadTodayStats();
    } catch (e) {
        message.error(getApiErrorMessage(e, '상태 변경에 실패했습니다.'));
    }
};

const loadTodayStats = async () => {
    try {
        const { data } = await apiDriverStats();
        todayStats.value = data.data;
    } catch {
        todayStats.value = null;
    }
};

const loadVehicles = async () => {
    vehicleLoading.value = true;

    try {
        const { data } = await apiMyVehicles();
        vehicles.value = data.data;
    } catch {
        vehicles.value = [];
    } finally {
        vehicleLoading.value = false;
    }
};

const openVehicleForm = (vehicle = null) => {
    editingVehicleId.value = vehicle?.id ?? null;
    vehicleForm.name = vehicle?.name ?? '';
    vehicleForm.type = vehicle?.type ?? '';
    vehicleForm.license_plate = vehicle?.license_plate ?? '';
    vehicleForm.color = vehicle?.color ?? '';
    vehicleForm.capacity = vehicle?.capacity ?? 0;
    vehicleForm.luggage_capacity = vehicle?.luggage_capacity ?? 0;
    vehicleForm.insurance_expires_at = vehicle?.insurance_expires_at ? new Date(vehicle.insurance_expires_at).getTime() : null;
    vehicleForm.is_default = vehicle?.is_default ?? false;
    vehicleFormOpen.value = true;
};

const toDateString = (ms) => {
    const date = new Date(ms);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
};

const closeVehicleForm = () => {
    vehicleFormOpen.value = false;
    editingVehicleId.value = null;
};

const saveVehicle = async () => {
    if (!vehicleForm.name.trim()) {
        message.warning('차량 이름을 입력해주세요.');

        return;
    }

    savingVehicle.value = true;

    try {
        const payload = {
            name: vehicleForm.name.trim(),
            type: vehicleForm.type.trim(),
            license_plate: vehicleForm.license_plate.trim(),
            color: vehicleForm.color.trim(),
            capacity: vehicleForm.capacity || 0,
            luggage_capacity: vehicleForm.luggage_capacity || 0,
            insurance_expires_at: vehicleForm.insurance_expires_at ? toDateString(vehicleForm.insurance_expires_at) : null,
            is_default: vehicleForm.is_default,
        };

        if (editingVehicleId.value) {
            await apiUpdateVehicle(editingVehicleId.value, payload);
            message.success('차량이 수정되었습니다.');
        } else {
            await apiCreateVehicle(payload);
            message.success('차량이 등록되었습니다.');
        }

        closeVehicleForm();
        await loadVehicles();
    } catch (e) {
        message.error(getApiErrorMessage(e, '차량 저장에 실패했습니다.'));
    } finally {
        savingVehicle.value = false;
    }
};

const removeVehicle = async (vehicle) => {
    try {
        await apiDeleteVehicle(vehicle.id);
        message.success('차량이 삭제되었습니다.');
        await loadVehicles();
    } catch (e) {
        message.error(getApiErrorMessage(e, '차량 삭제에 실패했습니다.'));
    }
};

const formatDuration = (seconds) => {
    const hours = Math.floor((seconds ?? 0) / 3600);
    const minutes = Math.floor(((seconds ?? 0) % 3600) / 60);

    return hours > 0 ? `${hours}시간 ${minutes}분` : `${minutes}분`;
};

const form = reactive({
    name: '',
    phone: '',
});

const roleLabel = computed(() => {
    const labels = {
        'Super Admin': '최고 관리자',
        Admin: '관리자',
        Operator: '운영자',
        Driver: '드라이버',
    };

    return labels[auth.user?.role] ?? auth.user?.role ?? '-';
});

const initials = computed(() => auth.user?.name?.charAt(0) ?? 'N');

const level = computed(() => auth.user?.level ?? null);

const formatWon = (value) => (value ?? 0).toLocaleString();

// 차량·면허 인증 신청 — 관리자에게 알림이 전달된다
const requestVerification = async (type) => {
    requesting.value = type;

    try {
        await apiClient.post('/verification/request', { [type]: true });
        message.success('인증 신청이 관리자에게 전달되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e));
    } finally {
        requesting.value = '';
    }
};

onMounted(() => {
    form.name = auth.user?.name ?? '';
    form.phone = auth.user?.phone ?? '';

    // 내 실적 (완료 운행·매출·평점) — 실패해도 프로필은 정상 표시
    apiCommunityUser(auth.user?.id)
        .then(({ data }) => {
            myStats.value = data.data;
        })
        .catch(() => {});

    // 기사 운영 — 상태/오늘 통계/차량 (드라이버 전용)
    if (auth.user?.role === 'Driver') {
        driver.load().then(() => {
            loadTodayStats();
        });
        loadVehicles();
    }
});

const save = async () => {
    if (!form.name.trim()) {
        error.value = '이름을 입력해주세요.';
        message.warning('이름을 입력해주세요.');

        return;
    }

    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await apiUpdateProfile({
            name: form.name.trim(),
            phone: form.phone.trim(),
        });

        auth.user = data.data;
        success.value = '회원정보가 저장되었습니다.';
        message.success('프로필이 저장되었습니다.');
    } catch (e) {
        error.value = getApiErrorMessage(e, '저장에 실패했습니다.');
        message.error(error.value);
    } finally {
        saving.value = false;
    }
};

const logout = async () => {
    await auth.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <div>
        <n-alert v-if="error" type="error" :show-icon="true" class="profile-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="profile-block">
            {{ success }}
        </n-alert>

        <n-card :bordered="true" class="profile-block">
            <div class="profile-hero">
                <span class="profile-hero__avatar">{{ initials }}</span>
                <div>
                    <div class="profile-hero__name-row">
                        <strong class="profile-hero__name">{{ auth.user?.name }}</strong>
                        <LevelBadge v-if="level" :level="level.level" size="sm" />
                        <n-tag v-if="auth.user?.is_vip" size="small" round type="warning">VIP</n-tag>
                    </div>
                    <span class="profile-hero__meta">{{ auth.user?.email }}</span>
                    <span class="profile-hero__meta">{{ auth.user?.phone }}</span>
                    <div class="profile-hero__badges">
                        <span v-if="auth.user?.is_vehicle_verified" class="verify-badge" title="차량 인증 완료">차량 인증</span>
                        <span v-if="auth.user?.is_license_verified" class="verify-badge" title="면허 인증 완료">면허 인증</span>
                        <n-tag size="small" round>{{ roleLabel }}</n-tag>
                    </div>
                </div>
            </div>
        </n-card>

        <!-- 기사 운영 — 가용 상태 / 오늘 통계 / 차량 -->
        <template v-if="isDriver">
            <n-card :bordered="true" class="profile-block">
                <div class="verify-head">
                    <strong>근무 상태</strong>
                    <span class="verify-hint">온라인이면 마켓에서 새 운행을 빠르게 확인할 수 있습니다</span>
                </div>

                <div class="driver-status">
                    <div class="driver-status__info">
                        <span class="driver-status__label" :class="`driver-status__label--${driver.status}`">
                            <span class="driver-status__dot" />
                            {{ driver.statusLabel }}
                        </span>
                        <span class="driver-status__meta">
                            오늘 {{ todayStats ? formatDuration(todayStats.online_seconds) : '-' }} 온라인
                            · {{ todayStats?.today_completed ?? '-' }}건 완료
                            · {{ todayStats ? formatWon(todayStats.today_income) : '-' }}원
                        </span>
                    </div>
                    <n-switch
                        :value="driver.isOnline"
                        :disabled="driver.status === 'on_trip'"
                        :loading="!driver.loaded"
                        @update:value="toggleDriverStatus"
                    />
                </div>
            </n-card>

            <n-card :bordered="true" class="profile-block">
                <div class="verify-head">
                    <strong>내 차량</strong>
                    <span class="verify-hint">운행 등록 시 내 차량 정보를 빠르게 채웁니다</span>
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
        </template>

        <!-- 내 실적 -->
        <div v-if="myStats" class="profile-stats">
            <div class="profile-stats__card">
                <span class="profile-stats__label">완료 운행</span>
                <strong class="profile-stats__value">{{ myStats.stats.completed_orders }}<small>건</small></strong>
            </div>
            <div class="profile-stats__card">
                <span class="profile-stats__label">누적 매출</span>
                <strong class="profile-stats__value">{{ formatWon(myStats.stats.total_revenue) }}<small>원</small></strong>
            </div>
            <div class="profile-stats__card">
                <span class="profile-stats__label">받은 평점</span>
                <strong class="profile-stats__value" :class="{ 'profile-stats__value--muted': myStats.reviewSummary.count === 0 }">
                    {{ myStats.reviewSummary.count > 0 ? myStats.reviewSummary.avg : '-' }}<small>{{ myStats.reviewSummary.count > 0 ? `점 / ${myStats.reviewSummary.count}개` : '' }}</small>
                </strong>
            </div>
        </div>

        <!-- 받은 리뷰 -->
        <n-card v-if="myStats" :bordered="true" class="profile-block">
            <div class="verify-head">
                <strong>받은 리뷰</strong>
                <span class="verify-hint">완료된 운행 후 상대방이 남긴 리뷰입니다</span>
            </div>
            <n-empty
                v-if="!myStats.reviews.length"
                description="아직 받은 리뷰가 없습니다."
                :image-size="60"
                class="profile-reviews-empty"
            />
            <div v-else class="profile-review-list">
                <article v-for="review in myStats.reviews" :key="review.id" class="profile-review">
                    <div class="profile-review__head">
                        <span class="profile-review__author">{{ review.reviewer?.name }}</span>
                        <n-rate :value="review.rating" readonly size="small" color="#ffa940" />
                    </div>
                    <p class="profile-review__content" v-text="review.content" />
                    <span class="profile-review__time">{{ review.created_at }}</span>
                </article>
            </div>
        </n-card>

        <!-- 알림 설정 -->
        <n-card :bordered="true" class="profile-block">
            <div class="verify-head">
                <strong>알림</strong>
                <span class="verify-hint">새 운행·채팅·알림이 도착하면 화면 상단에 데스크톱 알림으로 알려드립니다.</span>
            </div>
            <div class="verify-row">
                <span class="verify-row__label">브라우저 알림</span>
                <n-switch :value="notifyEnabled" @update:value="toggleNotify" />
            </div>
        </n-card>

        <!-- 차량·면허 인증 -->
        <n-card :bordered="true" class="profile-block">
            <div class="verify-head">
                <strong>인증</strong>
                <span class="verify-hint">관리자 승인 후 마켓에서 인증 배지가 표시됩니다</span>
            </div>

            <div class="verify-row">
                <span class="verify-row__label">
                    차량 인증
                    <span v-if="auth.user?.is_vehicle_verified" class="verify-row__done">완료</span>
                    <span v-else class="verify-row__pending">미인증</span>
                </span>
                <n-button
                    v-if="!auth.user?.is_vehicle_verified"
                    size="small"
                    type="primary"
                    ghost
                    :loading="requesting === 'vehicle'"
                    @click="requestVerification('vehicle')"
                >
                    신청
                </n-button>
                <span v-else class="verify-row__badge">✓</span>
            </div>

            <div class="verify-row">
                <span class="verify-row__label">
                    면허 인증
                    <span v-if="auth.user?.is_license_verified" class="verify-row__done">완료</span>
                    <span v-else class="verify-row__pending">미인증</span>
                </span>
                <n-button
                    v-if="!auth.user?.is_license_verified"
                    size="small"
                    type="primary"
                    ghost
                    :loading="requesting === 'license'"
                    @click="requestVerification('license')"
                >
                    신청
                </n-button>
                <span v-else class="verify-row__badge">✓</span>
            </div>
        </n-card>

        <!-- 레벨 시스템 -->
        <n-card v-if="level" :bordered="true" class="profile-block">
            <div class="level-head">
                <LevelBadge :level="level.level" size="lg" />
                <div class="level-head__text">
                    <strong>{{ level.title }}</strong>
                    <span>누적 XP {{ formatWon(auth.user?.xp ?? 0) }}점</span>
                </div>
            </div>

            <div class="level-bar">
                <div class="level-bar__fill" :style="{ width: `${level.progress}%` }" />
            </div>

            <p class="level-hint">
                <template v-if="level.next_xp !== null">
                    다음 레벨까지 <strong>{{ formatWon(level.next_xp - (auth.user?.xp ?? 0)) }}점</strong> 남았습니다
                    ({{ level.min_xp }} → {{ level.next_xp }}점).
                </template>
                <template v-else>
                    최고 레벨에 도달했습니다!
                </template>
            </p>

            <div v-if="auth.user?.recent_xp_events?.length" class="xp-events">
                <div
                    v-for="event in auth.user.recent_xp_events"
                    :key="event.created_at"
                    class="xp-event"
                >
                    <span>{{ event.label }}</span>
                    <strong>+{{ event.xp }}</strong>
                </div>
            </div>
        </n-card>

        <n-card :bordered="true" class="profile-block">
            <button type="button" class="community-entry" @click="router.push({ name: 'community' })">
                <span class="community-entry__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="8" r="3.5" />
                        <path d="M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5" />
                        <circle cx="17.5" cy="9" r="2.5" />
                        <path d="M15.5 15.2c2.6.2 4.6 1.5 5.5 4.3" />
                    </svg>
                </span>
                <span class="community-entry__text">
                    <strong>유저 커뮤니티</strong>
                    <small>드라이버·운영진과 일상을 공유하는 피드</small>
                </span>
                <span class="community-entry__arrow">›</span>
            </button>

            <!-- 내가 올린 글 — 다른 유저도 볼 수 있는 공개 페이지로 이동 -->
            <button
                type="button"
                class="community-entry"
                @click="router.push({ name: 'user-page', params: { id: auth.user?.id } })"
            >
                <span class="community-entry__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 4h16v16H4z" />
                        <path d="M8 9h8M8 13h8M8 17h5" />
                    </svg>
                </span>
                <span class="community-entry__text">
                    <strong>내가 올린 글</strong>
                    <small>내 프로필·글·운행을 다른 유저에게 보여주는 공개 페이지</small>
                </span>
                <span class="community-entry__arrow">›</span>
            </button>
        </n-card>

        <n-card :bordered="true" class="profile-block" title="프로필 수정">
            <n-form label-placement="top" label-width="auto">
                <n-form-item label="이름" required>
                    <n-input v-model:value="form.name" placeholder="이름" />
                </n-form-item>
                <n-form-item label="연락처">
                    <n-input v-model:value="form.phone" placeholder="예) 010-1234-5678" />
                </n-form-item>
                <n-form-item label="이메일">
                    <n-input :value="auth.user?.email" disabled />
                </n-form-item>
            </n-form>

            <n-button type="primary" size="large" :loading="saving" @click="save">
                저장
            </n-button>
        </n-card>

        <!-- 로그아웃 -->
        <n-button
            type="error"
            tertiary
            size="large"
            block
            class="profile-logout"
            @click="logout"
        >
            로그아웃
        </n-button>

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
.profile-block {
    margin-bottom: 16px;
    border-radius: 16px;
}

/* 인증 카드 */
.verify-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.verify-hint {
    color: var(--text-muted);
    font-size: 12px;
}

.verify-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.verify-row:last-child {
    border-bottom: none;
}

.verify-row__label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
}

.verify-row__done {
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(46, 160, 67, 0.12);
    color: #2ea043;
    font-size: 11px;
    font-weight: 700;
}

.verify-row__pending {
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(128, 128, 128, 0.14);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
}

.verify-row__badge {
    color: #2ea043;
    font-weight: 800;
}

/* 내 실적 카드 */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.profile-stats__card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.profile-stats__label {
    color: var(--text-muted);
    font-size: 12px;
}

.profile-stats__value {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    white-space: nowrap;
}

.profile-stats__value small {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    margin-left: 2px;
}

.profile-stats__value--muted {
    color: var(--text-muted);
}

.profile-logout {
    margin-top: 8px;
    border-radius: 12px;
}

/* ── 프로필 히어로 ── */
.profile-hero {
    display: flex;
    align-items: center;
    gap: 16px;
}

.profile-hero__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 24px;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--brand) 30%, transparent);
}

.profile-hero__name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.profile-hero__name {
    font-size: 18px;
    font-weight: 700;
}

.profile-hero__meta {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 13px;
}

.profile-hero__badges {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.verify-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 12px;
    font-weight: 600;
}

/* ── 레벨 ── */
.level-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.level-head__text {
    display: flex;
    flex-direction: column;
}

.level-head__text strong {
    font-size: 16px;
}

.level-head__text span {
    color: var(--text-muted);
    font-size: 13px;
    margin-top: 2px;
}

.level-bar {
    height: 10px;
    border-radius: 5px;
    background: rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.level-bar__fill {
    display: block;
    height: 100%;
    border-radius: 5px;
    background: var(--brand-gradient);
    transition: width 0.4s ease;
}

.level-hint {
    margin: 10px 0 0;
    color: var(--text-muted);
    font-size: 13px;
}

.level-hint strong {
    color: var(--text);
}

.xp-events {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 16px;
    padding: 14px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .xp-events {
    background: rgba(255, 255, 255, 0.03);
}

.xp-event {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
}

.xp-event span {
    color: var(--text);
}

.xp-event strong {
    color: var(--status-accepted);
    font-size: 14px;
}

/* ── 커뮤니티 입장 ── */
.community-entry {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 8px 4px;
    border: 0;
    background: none;
    color: var(--text);
    cursor: pointer;
    text-align: left;
    border-radius: 12px;
    transition: background 0.12s ease;
}

.community-entry:hover {
    background: color-mix(in srgb, var(--brand) 5%, transparent);
}

.community-entry + .community-entry {
    border-top: 1px solid var(--border);
    border-radius: 0;
    margin-top: 4px;
    padding-top: 12px;
}

.community-entry__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--brand-gradient);
    color: #ffffff;
    flex-shrink: 0;
}

.community-entry__icon svg {
    width: 22px;
    height: 22px;
}

.community-entry__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.community-entry__text strong {
    font-size: 15px;
}

.community-entry__text small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 2px;
}

.community-entry__arrow {
    color: var(--text-muted);
    font-size: 24px;
    font-weight: 300;
}

/* ── 기사 운영 — 근무 상태 ── */
.driver-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
}

.driver-status__info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.driver-status__label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 14px;
    font-weight: 700;
}

.driver-status__dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--text-muted);
}

.driver-status__label--online .driver-status__dot,
.driver-status__label--on_trip .driver-status__dot {
    background: var(--status-completed);
}

.driver-status__label--online,
.driver-status__label--on_trip {
    color: var(--status-completed);
}

.driver-status__label--rest .driver-status__dot {
    background: var(--warn);
}

.driver-status__label--rest {
    color: var(--warn);
}

.driver-status__meta {
    color: var(--text-muted);
    font-size: 12px;
}

/* ── 기사 운영 — 내 차량 ── */
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
