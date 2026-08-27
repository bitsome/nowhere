<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useProfileSettings } from '../composables/useProfileSettings';
import BaseIcon from '../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// 모듈: 프로필 설정(로그아웃) 재사용
const settings = useProfileSettings({ auth, router, message });
const { error, success, logout } = settings;

// 차량 설정은 기사 전용
const isDriver = computed(() => auth.user?.role === 'Driver');
</script>

<template>
    <div class="settings-page">
        <div class="page-head">
            <div>
                <h1 class="page-head__title">설정</h1>
                <p class="page-head__desc">기능별 설정을 선택해 관리합니다.</p>
            </div>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="settings-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="settings-block">
            {{ success }}
        </n-alert>

        <!-- 설정 카드 그리드 — 아이콘 + 이름 + 설명 -->
        <div class="settings-grid">
            <button type="button" class="settings-card" @click="router.push({ name: 'settings-profile' })">
                <span class="settings-card__icon">
                    <BaseIcon name="profile" :size="20" />
                </span>
                <strong>프로필 수정</strong>
                <small>이름·연락처 변경</small>
            </button>
            <button v-if="isDriver" type="button" class="settings-card" @click="router.push({ name: 'settings-vehicles' })">
                <span class="settings-card__icon">
                    <BaseIcon name="car" :size="20" />
                </span>
                <strong>차량 설정</strong>
                <small>차량 추가·수정·삭제</small>
            </button>
            <button type="button" class="settings-card" @click="router.push({ name: 'settings-notifications' })">
                <span class="settings-card__icon">
                    <BaseIcon name="bell" :size="20" />
                </span>
                <strong>알림</strong>
                <small>브라우저 알림 설정</small>
            </button>
            <button type="button" class="settings-card" @click="router.push({ name: 'settings-verification' })">
                <span class="settings-card__icon">
                    <BaseIcon name="shield" :size="20" />
                </span>
                <strong>인증 상태</strong>
                <small>차량·면허 인증 신청</small>
            </button>
            <button type="button" class="settings-card" @click="router.push({ name: 'settings-appearance' })">
                <span class="settings-card__icon">
                    <BaseIcon name="sunny" :size="20" />
                </span>
                <strong>화면</strong>
                <small>화면 모드·앱 설치</small>
            </button>
        </div>

        <!-- 로그아웃 -->
        <n-button
            type="error"
            tertiary
            size="large"
            block
            class="settings-logout"
            @click="logout"
        >
            로그아웃
        </n-button>
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

/* ── 설정 카드 그리드 ── */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.settings-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 16px 14px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    text-align: left;
    transition: border-color 0.12s ease, background 0.12s ease;
}

.settings-card:hover {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 4%, transparent);
}

.settings-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--brand-gradient);
    color: #ffffff;
    flex-shrink: 0;
}

.settings-card__icon svg {
    width: 20px;
    height: 20px;
}

.settings-card strong {
    font-size: 11px;
}

.settings-card small {
    color: var(--text-muted);
    font-size: 11.5px;
}

.settings-logout {
    margin-top: 16px;
    border-radius: 12px;
}
</style>
