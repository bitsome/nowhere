<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useChatsStore } from '../../stores/chats';
import { useDriverStore } from '../../stores/driver';
import { useNotificationsStore } from '../../stores/notifications';
import { useThemeStore } from '../../stores/theme';
import { useUiStore } from '../../stores/ui';
import BaseIcon from '../common/BaseIcon.vue';

const emit = defineEmits(['action']);

const route = useRoute();
const router = useRouter();
const message = useMessage();
const auth = useAuthStore();
const chats = useChatsStore();
const driver = useDriverStore();
const notifications = useNotificationsStore();
const theme = useThemeStore();
const ui = useUiStore();

// 스크롤 여부 — 헤더 하단에 은은한 그림자로 경계 표시 (전 페이지 공통)
const scrolled = ref(false);

const onScroll = () => {
    scrolled.value = window.scrollY > 8;
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});

const PAGE_LABEL = {
    home: '홈', market: '운행 마켓', chat: '채팅', match: '매칭',
    community: '커뮤니티', profile: '회원정보', notifications: '알림',
    'user-page': '사용자 정보', 'order-create': '받은 운행',
    'order-edit': '운행 수정', 'order-detail': '운행 상세',
    dashboard: '대시보드', admin: '운영 관리', 'my-market': '등록한 운행',
    more: '더보기', history: '지난 운행', reviews: '받은 리뷰',
    'order-favorites': '찜한 운행',
};

const activeConv = computed(() =>
    chats.activeId ? chats.conversations.find((c) => c.id === chats.activeId) : null,
);

// 내 운행 등록/수정 폼 — 집중 화면 (뒤로가기 + 하단 탭 숨김)
const isOrderForm = computed(() => route.name === 'order-create' && ui.orderFormActive);

const isChatThread = computed(() => route.name === 'chat' && chats.activeId);

const label = computed(() => {
    if (isChatThread.value) return activeConv.value?.counterpart?.name ?? '';
    if (isOrderForm.value) return '운행 등록';
    return PAGE_LABEL[route.name] ?? '';
});

// 서브페이지(대화방·운행상세·알림·등록폼 등)는 뒤로가기를 표시한다
const showBack = computed(() => {
    if (!route.name) return false; // 초기 렌더링 전
    if (isChatThread.value) return true;
    if (route.name === 'notifications') return true;
    if (isOrderForm.value) return true;
    return !['home', 'market', 'my-orders', 'community', 'profile', 'order-create', 'chat', 'more'].includes(route.name);
});

const goBack = () => {
    // 대화방은 스토어를 닫는다
    if (isChatThread.value) {
        emit('action', 'thread:back');
        return;
    }

    // 운행 등록 폼은 내부 화면 전환이므로 목록으로 복귀하도록 액션 전달
    if (isOrderForm.value) {
        emit('action', 'order-form:back');
        return;
    }

    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'market' });
    }
};

// 알림 벨 — 집중 폼에서는 숨김
const showBell = computed(() => {
    if (isOrderForm.value) return false;

    return ['home', 'market', 'order-create', 'chat', 'community', 'profile', 'more'].includes(route.name);
});

// 프로필 아이콘 — 상위 탭에서 접근 (프로필 화면에서는 숨김)
const showProfileIcon = computed(() => showBell.value && route.name !== 'profile');

const profileDotMenu = computed(() => {
    const menus = [
        { label: theme.isDark ? '라이트 모드' : '다크 모드', key: 'theme' },
    ];

    if (auth.isAdmin) {
        menus.push({ label: '운영 관리', key: 'admin' });
    }

    return menus;
});

// 홈 헤더 온라인 칩 — 클릭 시 가용 상태 모달 (온라인/오프라인/휴식)
const STATUS_OPTIONS = [
    { value: 'online', label: '온라인', hint: '마켓에서 새 운행을 빠르게 확인할 수 있어요' },
    { value: 'offline', label: '오프라인', hint: '새 운행 알림을 받지 않습니다' },
    { value: 'rest', label: '휴식', hint: '잠시 쉬는 동안 상태를 표시합니다' },
];

const STATUS_MESSAGES = {
    online: '온라인으로 전환되었습니다.',
    offline: '오프라인으로 전환되었습니다.',
    rest: '휴식 상태로 전환되었습니다.',
};

const statusOpen = ref(false);
const applyingStatus = ref(null);

const openStatusModal = () => {
    if (driver.status === 'on_trip') {
        message.warning('운행 중에는 상태를 변경할 수 없습니다.');

        return;
    }

    statusOpen.value = true;
};

const applyStatus = async (status) => {
    if (status === driver.status) {
        statusOpen.value = false;

        return;
    }

    applyingStatus.value = status;

    try {
        await driver.setStatus(status);
        message.success(STATUS_MESSAGES[status]);
        statusOpen.value = false;
    } catch {
        message.error('상태 변경에 실패했습니다.');
    } finally {
        applyingStatus.value = null;
    }
};

function doMenu(key) {
    switch (key) {
        case 'admin': router.push({ name: 'admin' }); break;
        case 'theme': theme.toggle(); break;
        default: emit('action', key); break;
    }
}
</script>

<template>
    <!-- 전 페이지 공통 플랫 헤더 — 뒤로가기/메뉴 + 제목 + 우측 액션 -->
    <header class="hb" :class="{ 'hb--scrolled': scrolled }">
        <div class="hb__inner">
            <div class="hb__left">
                <button v-if="showBack" type="button" class="hb-btn" aria-label="뒤로" @click="goBack()">
                    <BaseIcon name="arrow-back" :size="20" />
                </button>

                <span v-if="label" class="hb__label">{{ label }}</span>
            </div>

            <div class="hb__right">
                <!-- 운행 중 — 헤더 상태 칩 (탭하여 내 운행) -->
                <button v-if="driver.isOnTrip" type="button" class="hb-driving" @click="router.push({ name: 'order-create' })">
                    <span class="hb-driving__dot" />
                    운행 중
                </button>

                <!-- 홈 — 기사 온라인 상태 칩 (클릭 시 가용 상태 모달). 운행 중이면 위 '운행 중' 칩만 보여 중복을 없앤다 -->
                <template v-if="route.name === 'home' && !driver.isOnTrip">
                    <button
                        type="button"
                        class="hb-online"
                        :class="{ 'hb-online--off': !driver.isOnline }"
                        :aria-label="'가용 상태: ' + driver.statusLabel"
                        :title="'가용 상태 변경'"
                        @click="openStatusModal"
                    >
                        <span class="hb-online__dot" />
                        {{ driver.statusLabel }}
                    </button>
                </template>

                <template v-if="route.name === 'market'">
                    <button type="button" class="hb-btn" aria-label="필터" @click="$emit('action', 'filter')">
                        <BaseIcon name="options" :size="20" />
                        <span v-if="ui.filterActive" class="hb-filter-dot" />
                    </button>
                </template>

                <template v-if="route.name === 'community'">
                    <button type="button" class="hb-btn" aria-label="글쓰기" @click="$emit('action', 'community:write')">
                        <BaseIcon name="create" :size="20" />
                    </button>
                </template>

                <!-- 알림 벨 — 모든 상위 탭 공통 -->
                <router-link v-if="showBell" to="/notifications" class="hb-btn" aria-label="알림">
                    <BaseIcon name="bell" :size="20" />
                    <span v-if="notifications.unreadCount > 0" class="hb-badge">{{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}</span>
                </router-link>

                <!-- 프로필 — 내 정보 탭이 하단에서 헤더로 이동됨 -->
                <router-link v-if="showProfileIcon" to="/profile" class="hb-btn" aria-label="내 정보">
                    <BaseIcon name="profile" :size="20" />
                </router-link>

                <template v-if="route.name === 'profile'">
                    <n-dropdown trigger="click" :options="profileDotMenu" @select="doMenu">
                        <button type="button" class="hb-btn hb-btn--dots" aria-label="더보기">
                            <BaseIcon name="more" :size="20" />
                        </button>
                    </n-dropdown>
                </template>
            </div>
        </div>
    </header>

    <!-- 가용 상태 변경 모달 (홈 헤더 온라인 칩 클릭) -->
    <n-modal
        v-model:show="statusOpen"
        preset="card"
        title="가용 상태"
        :style="{ maxWidth: '400px' }"
    >
        <div class="status-modal">
            <button
                v-for="opt in STATUS_OPTIONS"
                :key="opt.value"
                type="button"
                class="status-option"
                :class="{ 'status-option--active': driver.status === opt.value }"
                :disabled="applyingStatus !== null"
                @click="applyStatus(opt.value)"
            >
                <span class="status-option__dot" :class="{ 'status-option__dot--on': driver.status === opt.value }" />
                <span class="status-option__text">
                    <span class="status-option__label">{{ opt.label }}</span>
                    <span class="status-option__hint">{{ opt.hint }}</span>
                </span>
                <n-spin v-if="applyingStatus === opt.value" :size="16" />
                <BaseIcon v-else-if="driver.status === opt.value" name="check" :size="18" class="status-option__check" />
            </button>
        </div>
    </n-modal>
</template>

<style scoped>
/* 전 페이지 공통 플랫 헤더 — 모든 화면에서 동일한 모양 */
.hb {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    transition: box-shadow 0.15s ease;
}

.hb--scrolled {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

html.dark .hb--scrolled {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
}

.hb__inner {
    max-width: var(--page-max-width);
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
}

.hb__left {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    min-width: 0;
}

.hb__right {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.hb__label {
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hb-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--text);
    cursor: pointer;
    flex-shrink: 0;
    text-decoration: none;
    transition: background 0.12s ease;
    position: relative;
}

.hb-btn:hover {
    background: rgba(0, 0, 0, 0.06);
}

html.dark .hb-btn:hover {
    background: rgba(255, 255, 255, 0.08);
}

html.dark .hb-btn {
    color: #e2e2e2;
}

html.dark .hb__label {
    color: #e2e2e2;
}

.hb-btn svg {
    width: 19px;
    height: 19px;
}

/* 알림 미확인 배지 */
.hb-badge {
    position: absolute;
    top: 0;
    right: 0;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--badge-red);
    color: #fff;
    font-size: 10px;
    font-weight: 400;
    line-height: 16px;
    text-align: center;
    box-shadow: 0 0 0 2px var(--surface);
}

/* 홈 — 기사 온라인 상태 칩 (클릭하여 전환) */
.hb-online {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-right: 2px;
    padding: 5px 10px;
    border: 0;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
    transition: filter 0.12s ease, opacity 0.12s ease;
}
.hb-online:hover {
    filter: brightness(0.95);
}
.hb-online:disabled {
    opacity: 0.6;
    cursor: default;
}
.hb-online--off {
    background: color-mix(in srgb, var(--text-muted) 12%, transparent);
    color: var(--text-muted);
}
.hb-online__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

/* 운행 중 — 헤더 상태 칩 (전 화면 공통) */
.hb-driving {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-right: 2px;
    padding: 5px 10px;
    border: 0;
    border-radius: 999px;
    background: color-mix(in srgb, var(--status-accepted) 14%, transparent);
    color: var(--status-accepted);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
    transition: filter 0.12s ease;
}

.hb-driving:hover {
    filter: brightness(0.94);
}

.hb-driving__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    animation: hb-driving-pulse 1.4s ease-in-out infinite;
}

@keyframes hb-driving-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.35;
    }
}

/* 모션 감소 설정 — 운행중 표시 점의 반복 점멸 정지 (base.css 스켈레톤과 동일 규칙) */
@media (prefers-reduced-motion: reduce) {
    .hb-driving__dot {
        animation: none;
    }
}

.hb-btn--dots {
    width: 32px;
    height: 32px;
}

.hb-btn--dots svg {
    width: 16px;
    height: 16px;
}

/* 필터 활성 점 */
.hb-filter-dot {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--brand);
    box-shadow: 0 0 0 2px var(--surface);
}

/* 가용 상태 변경 모달 — 옵션 리스트 */
.status-modal {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.status-option {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 10px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--text);
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 0.12s ease;
}

.status-option:hover:not(:disabled) {
    background: rgba(0, 0, 0, 0.05);
}

html.dark .status-option:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.07);
}

.status-option:disabled {
    cursor: default;
}

.status-option__dot {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    border: 2px solid var(--border-strong, var(--border));
    border-radius: 50%;
    transition: border-color 0.12s ease, background 0.12s ease;
}

.status-option__dot--on {
    border-color: var(--brand);
    background: var(--brand);
    box-shadow: inset 0 0 0 3px var(--surface);
}

.status-option__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.status-option__label {
    font-size: 11px;
    font-weight: 700;
}

.status-option__hint {
    font-size: 11px;
    color: var(--text-muted);
}

.status-option__check {
    color: var(--brand);
    flex-shrink: 0;
}
</style>
