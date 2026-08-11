<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { darkTheme } from 'naive-ui';
import { useAuthStore } from './stores/auth';
import { useChatsStore } from './stores/chats';
import { useNotificationsStore } from './stores/notifications';
import { useThemeStore } from './stores/theme';
import { useUiStore } from './stores/ui';
import ChatListener from './components/ChatListener.vue';
import HeaderBar from './components/HeaderBar.vue';
import NotificationListener from './components/NotificationListener.vue';
import { connectEventStream } from './utils/eventStream';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const notifications = useNotificationsStore();
const chats = useChatsStore();
const ui = useUiStore();
const drawerOpen = ref(false);
const initReady = ref(false);

// 대화방 열림 — 전체 화면 모드 (상·하단 패딩 제거, 하단 네비 숨김)
const isChatThread = computed(() => route.name === 'chat' && chats.activeId);

// 채팅 화면을 벗어나면(뒤로가기 등) 대화방을 닫아 하단 메뉴를 복원한다
watch(
    () => route.name,
    (name) => {
        if (name !== 'chat' && chats.activeId) {
            chats.close();
        }
    },
);

// 대화방 히스토리 항목에서 뒤로가기 → 대화 목록으로 복귀
const onPopState = () => {
    if (chats.activeId) {
        chats.close();
    }
};

onMounted(() => {
    window.addEventListener('popstate', onPopState);
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', onPopState);
});

// ── 하단 네비 탭 ──
const navItems = [
    { name: 'market', label: '마켓' },
    { name: 'order-create', label: '내 오더' },
    { name: 'chat', label: '채팅' },
    { name: 'community', label: '커뮤니티' },
    { name: 'profile', label: '내 정보' },
];

const closeDrawer = () => {
    drawerOpen.value = false;
};

// 헤더 emit 액션 처리
const handleHeaderAction = (key) => {
    if (key === 'community:write') {
        ui.emitAction('community:write');
    } else if (key === 'orders:filter') {
        ui.emitAction('orders:filter');
    } else if (key === 'filter') {
        ui.emitAction('filter');
    } else if (key === 'thread:back') {
        chats.close();
    }
};

const handleMenuAction = (key) => {
    closeDrawer();

    if (key.startsWith('nav:')) {
        router.push({ name: key.slice(4) });
        return;
    }

    switch (key) {
        case 'community:my-posts':
            ui.communityMyPostsOnly = !ui.communityMyPostsOnly;
            ui.emitAction('community:reload');
            break;
        case 'community:sort-popular':
            ui.communitySort = 'popular';
            ui.emitAction('community:reload');
            break;
        case 'community:sort-latest':
            ui.communitySort = 'latest';
            ui.emitAction('community:reload');
            break;
        case 'notifications':
            router.push({ name: 'notifications' });
            break;
        case 'theme':
            theme.toggle();
            break;
        case 'logout':
            auth.logout().then(() => router.push({ name: 'login' }));
            break;
        case 'refresh':
            ui.emitAction('refresh');
            break;
    }
};

onMounted(async () => {
    theme.init();
    await auth.fetchMe().catch(() => {});
    initReady.value = true;

    // 로그인 상태에서 첫 상호작용 시 웹 알림 권한 요청 (거절 시 재요청 안 함)
    if (auth.user && 'Notification' in window && Notification.permission === 'default') {
        const requestNotifyPermission = () => {
            Notification.requestPermission();
            window.removeEventListener('click', requestNotifyPermission);
        };
        window.addEventListener('click', requestNotifyPermission, { once: true });
    }

    // SSE 실시간 신호 — 탭이 숨겨져 있을 때만 연결한다.
    // php artisan serve(단일 워커) 환경에서 연결이 항상 열려 있으면
    // 다른 API 요청을 블로킹하므로, 화면을 보는 동안은 폴링으로 충분하다.
    if (auth.user) {
        let sseSource = null;

        const attachSse = () => {
            if (document.visibilityState === 'visible' || sseSource) {
                return;
            }
            sseSource = connectEventStream(() => {
                window.dispatchEvent(new CustomEvent('app:sse-refresh'));
            });
        };

        const detachSse = () => {
            if (sseSource) {
                sseSource.close();
                sseSource = null;
            }
        };

        const onVisibilitySse = () => {
            if (document.visibilityState === 'hidden') {
                attachSse();
            } else {
                detachSse();
            }
        };

        window.addEventListener('visibilitychange', onVisibilitySse);
        attachSse(); // 최초 상태 반영
    }
});
</script>

<template>
    <n-config-provider :theme="theme.isDark ? darkTheme : null">
        <n-message-provider>
            <n-notification-provider>
                <div class="app-shell" :class="{ 'app-shell--ready': initReady || !auth.token }">

                <HeaderBar
                    v-if="route.name !== 'login'"
                    @open-drawer="drawerOpen = true"
                    @action="handleHeaderAction"
                />

                <!-- 드로어 오버레이 -->
                <n-drawer
                    v-model:show="drawerOpen"
                    :width="320"
                    placement="left"
                >
                    <div class="drawer-inner">
                        <div class="drawer-section">
                            <p class="drawer-section__title">탐색</p>
                            <button
                                v-for="item in navItems"
                                :key="item.name"
                                type="button"
                                class="drawer-nav-row"
                                :class="{ 'drawer-nav-row--active': route.name === item.name }"
                                @click="handleMenuAction(`nav:${item.name}`)"
                            >
                                <svg class="drawer-nav-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <template v-if="item.name === 'market'">
                                        <path d="M3 10.5L12 3l9 7.5" />
                                        <path d="M5 9.5V21h14V9.5" />
                                    </template>
                                    <template v-else-if="item.name === 'order-create'">
                                        <path d="M8 6h13M8 12h13M8 18h13" />
                                        <path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01" />
                                    </template>
                                    <template v-else-if="item.name === 'chat'">
                                        <path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3 8.6 8.6 0 0 1-3.9-.9L3 20l1.2-5.3a8.2 8.2 0 0 1-.7-3.2A8.4 8.4 0 0 1 12 3.2a8.4 8.4 0 0 1 9 8.3z" />
                                    </template>
                                    <template v-else-if="item.name === 'community'">
                                        <circle cx="9" cy="8" r="3.5" />
                                        <path d="M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5" />
                                        <circle cx="17.5" cy="9" r="2.5" />
                                        <path d="M15.5 15.2c2.6.2 4.6 1.5 5.5 4.3" />
                                    </template>
                                    <template v-else-if="item.name === 'profile'">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </template>
                                </svg>
                                <span>{{ item.label }}</span>
                            </button>
                        </div>

                        <div v-if="route.name === 'community'" class="drawer-section">
                            <p class="drawer-section__title">커뮤니티</p>
                            <button type="button" class="drawer-action-row" @click="handleMenuAction('community:my-posts')">
                                <span>{{ ui.communityMyPostsOnly ? '✓ 내 글만 보기' : '내 글만 보기' }}</span>
                            </button>
                            <button type="button" class="drawer-action-row" @click="handleMenuAction('community:sort-popular')">
                                <span>{{ ui.communitySort === 'popular' ? '✓ 인기순' : '인기순 정렬' }}</span>
                            </button>
                            <button type="button" class="drawer-action-row" @click="handleMenuAction('community:sort-latest')">
                                <span>{{ ui.communitySort === 'latest' ? '✓ 최신순' : '최신순 정렬' }}</span>
                            </button>
                        </div>

                        <div class="drawer-section">
                            <p class="drawer-section__title">통계</p>
                            <button type="button" class="drawer-action-row" :class="{ 'drawer-nav-row--active': route.name === 'dashboard' }" @click="handleMenuAction('nav:dashboard')">
                                <span>대시보드</span>
                            </button>
                        </div>

                        <div class="drawer-section">
                            <p class="drawer-section__title">계정</p>
                            <button type="button" class="drawer-action-row" @click="handleMenuAction('notifications')">
                                <span>{{ notifications.unreadCount > 0 ? `알림 (${notifications.unreadCount})` : '알림' }}</span>
                            </button>
                            <button type="button" class="drawer-action-row" @click="handleMenuAction('theme')">
                                <span>{{ theme.isDark ? '라이트 모드' : '다크 모드' }}</span>
                            </button>
                            <button type="button" class="drawer-action-row drawer-action-row--danger" @click="handleMenuAction('logout')">
                                <span>로그아웃</span>
                            </button>
                        </div>
                    </div>
                </n-drawer>

                <button
                    v-if="auth.isAuthenticated && notifications.unreadCount > 0"
                    type="button"
                    class="notify-banner"
                    @click="router.push({ name: 'notifications' })"
                >
                    알림 {{ notifications.unreadCount }}건이 도착했습니다 · 탭하여 확인
                </button>

                <main
                    class="app-content"
                    :class="{ 'app-content--full': isChatThread || route.name === 'login' }"
                >
                    <router-view />
                </main>

                <nav v-if="auth.isAuthenticated && !chats.activeId" class="bottom-nav">
                    <router-link
                        v-for="item in navItems"
                        :key="item.name"
                        :to="{ name: item.name }"
                        class="bottom-nav__item"
                        :class="{ 'bottom-nav__item--active': route.name === item.name }"
                    >
                        <n-badge
                            :value="item.name === 'chat' ? chats.unreadTotal : (item.name === 'profile' ? notifications.unreadCount : 0)"
                            :max="99"
                            class="nav-badge"
                            :show="(item.name === 'chat' && chats.unreadTotal > 0 && route.name !== 'chat') || (item.name === 'profile' && notifications.unreadCount > 0 && route.name !== 'notifications')"
                        >
                            <svg
                                class="bottom-nav__icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <template v-if="item.name === 'market'">
                                    <path d="M3 10.5L12 3l9 7.5" />
                                    <path d="M5 9.5V21h14V9.5" />
                                </template>
                                <template v-else-if="item.name === 'order-create'">
                                    <path d="M8 6h13M8 12h13M8 18h13" />
                                    <path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01" />
                                </template>
                                <template v-else-if="item.name === 'chat'">
                                    <path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3 8.6 8.6 0 0 1-3.9-.9L3 20l1.2-5.3a8.2 8.2 0 0 1-.7-3.2A8.4 8.4 0 0 1 12 3.2a8.4 8.4 0 0 1 9 8.3z" />
                                </template>
                                <template v-else-if="item.name === 'community'">
                                    <circle cx="9" cy="8" r="3.5" />
                                    <path d="M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5" />
                                    <circle cx="17.5" cy="9" r="2.5" />
                                    <path d="M15.5 15.2c2.6.2 4.6 1.5 5.5 4.3" />
                                </template>
                                <template v-else-if="item.name === 'profile'">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </template>
                            </svg>
                        </n-badge>
                        <span>{{ item.label }}</span>
                    </router-link>
                </nav>

                <NotificationListener v-if="auth.isAuthenticated" />
                <ChatListener v-if="auth.isAuthenticated" />
            </div>
            </n-notification-provider>
        </n-message-provider>
    </n-config-provider>
</template>

<style>
/* ── 대화방 전체 화면 — 상·하단 패딩 제거, window 스크롤 제거 ── */
.app-content--full {
    padding: 0;
    max-width: none;
}

/* ── 하단 네비 배지 등장 애니메이션 ── */
.nav-badge .n-badge-sup {
    animation: nav-badge-pop 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.4);
}

@keyframes nav-badge-pop {
    0% {
        transform: scale(0.2);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* ── 드로어 오버레이 ── */
.drawer-inner {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 4px 0;
}

.drawer-section {
    padding: 8px 0;
}

.drawer-section__title {
    margin: 0 0 8px 4px;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drawer-nav-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--text);
    font-size: 15px;
    text-align: left;
    cursor: pointer;
    padding: 12px 12px;
    transition: background 0.12s ease;
}

.drawer-nav-row:hover {
    background: rgba(0, 0, 0, 0.05);
}

.drawer-nav-row--active {
    background: rgba(54, 173, 255, 0.1);
    color: #36adff;
    font-weight: 700;
}

.drawer-nav-row__icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
}

.drawer-action-row {
    display: flex;
    align-items: center;
    width: 100%;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--text);
    font-size: 14px;
    text-align: left;
    cursor: pointer;
    padding: 10px 12px;
    transition: background 0.12s ease;
}

.drawer-action-row:hover {
    background: rgba(0, 0, 0, 0.05);
}

.drawer-action-row--danger {
    color: var(--danger);
}
</style>
