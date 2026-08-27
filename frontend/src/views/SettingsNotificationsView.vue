<script setup>
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useProfileSettings } from '../composables/useProfileSettings';
import BaseIcon from '../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsNotificationsView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// 모듈: 프로필 설정(알림) 재사용
const settings = useProfileSettings({ auth, router, message });
const { error, success, notifyEnabled, toggleNotify } = settings;
</script>

<template>
    <div class="settings-page">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">알림</h1>
                <p class="page-head__desc">새 운행·채팅·알림 도착 시 데스크톱 알림으로 알려드립니다.</p>
            </div>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="settings-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="settings-block">
            {{ success }}
        </n-alert>

        <n-card :bordered="true" class="settings-block">
            <div class="notify-row">
                <div class="notify-row__text">
                    <strong>브라우저 알림</strong>
                    <span>앱이 백그라운드에 있어도 새 소식을 알려드립니다.</span>
                </div>
                <n-switch :value="notifyEnabled" @update:value="toggleNotify" />
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
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.12s ease;
}

.settings-back:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

/* ── 알림 토글 행 ── */
.notify-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 4px 0;
}

.notify-row__text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.notify-row__text strong {
    font-size: 11px;
}

.notify-row__text span {
    color: var(--text-muted);
    font-size: 11px;
}
</style>
