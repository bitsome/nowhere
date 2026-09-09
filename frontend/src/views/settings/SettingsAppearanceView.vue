<script setup>
import { useRouter } from 'vue-router';
import { useThemeStore } from '../../stores/theme';
import { usePwaInstall } from '../../composables/usePwaInstall';
import BaseIcon from '../../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsAppearanceView' });

const router = useRouter();
const theme = useThemeStore();
const pwa = usePwaInstall();
</script>

<template>
    <div class="settings-page page-shell">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">화면</h1>
                <p class="page-head__desc">화면 모드와 앱 설치를 관리합니다.</p>
            </div>
        </div>

        <n-card :bordered="true" class="settings-block">
            <div class="appear-row">
                <div class="appear-row__text">
                    <strong>화면 모드</strong>
                    <span>어두운 화면으로 전환합니다.</span>
                </div>
                <n-switch :value="theme.isDark" @update:value="theme.toggle()" />
            </div>
        </n-card>

        <n-card v-if="pwa.canInstall.value" :bordered="true" class="settings-block">
            <div class="appear-row">
                <div class="appear-row__text">
                    <strong>앱 설치</strong>
                    <span>홈 화면에 추가해 앱처럼 사용할 수 있습니다.</span>
                </div>
                <n-button
                    type="primary"
                    ghost
                    :loading="pwa.installing.value"
                    @click="pwa.install()"
                >
                    설치
                </n-button>
            </div>
        </n-card>
    </div>
</template>

<style scoped>
.settings-page {
    /* 상단 시작은 공용 .page-shell 기준으로 통일 — 하단 여백만 페이지가 관리한다 */
    padding-bottom: 24px;
}

.settings-block {
    margin-bottom: 14px;
    border-radius: var(--card-radius);
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

/* ── 화면 토글 행 ── */
.appear-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 4px 0;
}

.appear-row__text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.appear-row__text strong {
    font-size: 11px;
}

.appear-row__text span {
    color: var(--text-muted);
    font-size: 11px;
}
</style>
