<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useProfileSettings } from '../composables/useProfileSettings';
import BaseIcon from '../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsProfileView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// 모듈: 프로필 설정(이름·연락처 수정) 재사용
const settings = useProfileSettings({ auth, router, message });
const { saving, error, success, form, save } = settings;

onMounted(() => {
    form.name = auth.user?.name ?? '';
    form.phone = auth.user?.phone ?? '';
});
</script>

<template>
    <div class="settings-page">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">프로필 수정</h1>
                <p class="page-head__desc">회원 이름과 연락처를 변경합니다.</p>
            </div>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="settings-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="settings-block">
            {{ success }}
        </n-alert>

        <n-card :bordered="true" class="settings-block" title="프로필 수정">
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
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.12s ease;
}

.settings-back:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}
</style>
