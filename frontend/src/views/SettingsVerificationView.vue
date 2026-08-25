<script setup>
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useProfileSettings } from '../composables/useProfileSettings';
import BaseIcon from '../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsVerificationView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// 모듈: 프로필 설정(인증 신청) 재사용
const settings = useProfileSettings({ auth, router, message });
const { error, success, requesting, requestVerification } = settings;
</script>

<template>
    <div class="settings-page">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">인증 상태</h1>
                <p class="page-head__desc">관리자 승인 후 마켓에서 인증 배지가 표시됩니다.</p>
            </div>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="settings-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="settings-block">
            {{ success }}
        </n-alert>

        <n-card :bordered="true" class="settings-block">
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
                <span v-else class="verify-row__badge"><BaseIcon name="check" :size="12" /></span>
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
                <span v-else class="verify-row__badge"><BaseIcon name="check" :size="14" /></span>
            </div>
        </n-card>
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
</style>
