<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../../stores/auth';
import { useProfileSettings } from '../../composables/useProfileSettings';
import { usePwaInstall } from '../../composables/usePwaInstall';
import BaseIcon from '../../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsNotificationsView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// 모듈: 프로필 설정(알림) 재사용
const settings = useProfileSettings({ auth, router, message });
const { error, success, notifyEnabled, notifySupported, notifyBlocked, toggleNotify } = settings;

const pwa = usePwaInstall();

// 아이폰 Safari 는 홈 화면에 추가한 앱에서만 알림이 동작한다.
// 여기서 켜기를 누르면 권한 요청이 즉시 거부되어 "권한이 거부되었습니다"로 잘못 안내된다.
const needsInstallFirst = computed(() => pwa.guide.value === 'ios' || pwa.guide.value === 'inapp');
</script>

<template>
    <div class="settings-page page-shell">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">알림</h1>
                <p class="page-head__desc">새 운행·채팅이 오면 앱을 닫아 둬도 알려 드립니다.</p>
            </div>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="settings-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="settings-block">
            {{ success }}
        </n-alert>

        <!-- 아이폰 Safari·인앱 브라우저 — 홈 화면에 추가하기 전에는 알림을 켤 수 없다 -->
        <n-card v-if="needsInstallFirst" :bordered="true" class="settings-block">
            <div class="notify-row">
                <div class="notify-row__text">
                    <strong>홈 화면에 추가한 뒤 켤 수 있어요</strong>
                    <span v-if="pwa.guide.value === 'ios'">
                        아이폰은 홈 화면에 추가한 앱에서만 알림이 동작합니다.
                    </span>
                    <span v-else>
                        지금 브라우저에서는 알림을 켤 수 없습니다. Safari로 열어 주세요.
                    </span>
                </div>
                <n-button type="primary" ghost @click="router.push({ name: 'settings-appearance' })">
                    추가 방법
                </n-button>
            </div>
        </n-card>

        <!-- 브라우저가 차단 — 차단된 뒤에는 권한 창이 다시 뜨지 않아 사용자가 직접 풀어야 한다 -->
        <n-card v-else-if="notifyBlocked" :bordered="true" class="settings-block">
            <div class="notify-row">
                <div class="notify-row__text">
                    <strong>브라우저가 알림을 차단하고 있어요</strong>
                    <span>주소창의 자물쇠 아이콘 → 알림 → 허용으로 바꾼 뒤 다시 켜 주세요.</span>
                </div>
            </div>
        </n-card>

        <!-- 푸시를 켤 수 없는 환경(HTTP 접속·구형 브라우저) — 켜기를 시도하면 실패만 반복된다 -->
        <n-card v-else-if="!notifySupported" :bordered="true" class="settings-block">
            <div class="notify-row">
                <div class="notify-row__text">
                    <strong>이 브라우저에서는 알림을 켤 수 없어요</strong>
                    <span>HTTPS 주소로 접속하거나 최신 브라우저로 열어 주세요.</span>
                </div>
            </div>
        </n-card>

        <n-card v-else :bordered="true" class="settings-block">
            <div class="notify-row">
                <div class="notify-row__text">
                    <strong>브라우저 알림</strong>
                    <span>앱이 백그라운드에 있어도 새 소식을 알려 드립니다.</span>
                </div>
                <n-switch :value="notifyEnabled" @update:value="toggleNotify" />
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
