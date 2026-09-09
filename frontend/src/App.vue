<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { darkTheme } from 'naive-ui';
import { useAuthStore } from './stores/auth';
import { useChatsStore } from './stores/chats';
import { useDriverStore } from './stores/driver';
import { useNotificationsStore } from './stores/notifications';
import { useThemeStore } from './stores/theme';
import { useUiStore } from './stores/ui';
import { naiveThemeOverrides } from './utils/colors';
import ChatListener from './components/layout/ChatListener.vue';
import HeaderBar from './components/layout/HeaderBar.vue';
import NotificationListener from './components/layout/NotificationListener.vue';
import BaseIcon from './components/common/BaseIcon.vue';
import ScrollTopButton from './components/common/ScrollTopButton.vue';
import { connectEventStream } from './utils/eventStream';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const theme = useThemeStore();
const notifications = useNotificationsStore();
const chats = useChatsStore();
const driver = useDriverStore();
const ui = useUiStore();
const initReady = ref(false);

// 대화방 열림 — 전체 화면 모드 (상·하단 패딩 제거, 하단 네비 숨김)
const isChatThread = computed(() => route.name === 'chat' && chats.activeId);

// 집중 화면 — 운행 상세·등록/수정 폼에서는 하단 탭을 숨긴다
const isFocusedScreen = computed(() =>
    route.name === 'order-detail'
    || route.name === 'order-edit'
    || (route.name === 'order-create' && ui.orderFormActive),
);

// 채팅 화면을 벗어나면(뒤로가기 등) 대화방을 닫아 하단 메뉴를 복원한다
watch(
    () => route.name,
    (name, prevName) => {
        if (name !== 'chat' && chats.activeId) {
            chats.close();
        }

        // 채팅을 읽고 다른 탭으로 나가면 하단 채팅 배지가 옛 안 읽음 수를
        // 계속 보여주지 않도록, 채팅 화면을 벗어난 직후 목록을 한 번 갱신한다.
        if (prevName === 'chat' && name !== 'chat' && chats.conversations.length > 0) {
            chats.loadConversations().catch(() => {});
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
    if (driverTimer) {
        clearInterval(driverTimer);
    }
    if (notifyTimer) {
        clearTimeout(notifyTimer);
    }
});

// ── 알림 배너: 알려주고 몇 초 뒤 자동으로 사라진다 ──
const notifyVisible = ref(false);
let notifyTimer = null;

watch(
    () => notifications.unreadCount,
    (count) => {
        if (count > 0) {
            notifyVisible.value = true;

            if (notifyTimer) {
                clearTimeout(notifyTimer);
            }

            notifyTimer = setTimeout(() => {
                notifyVisible.value = false;
            }, 6000);
        } else {
            notifyVisible.value = false;
        }
    },
    { immediate: true },
);

const dismissNotify = () => {
    notifyVisible.value = false;

    if (notifyTimer) {
        clearTimeout(notifyTimer);
    }
};

// ── 하단 네비 탭 ──
const navItems = [
    { name: 'home', label: '홈' },
    { name: 'market', label: '마켓' },
    { name: 'community', label: '커뮤니티' },
    { name: 'chat', label: '채팅' },
    { name: 'more', label: '더보기', isMore: true },
];

// 더보기 탭의 활성 판정 — 더보기 페이지 및 그 메뉴가 열리는 화면들
const moreActive = computed(() => ['more', 'dashboard', 'profile', 'admin', 'history', 'reviews'].includes(route.name));

// 탭 활성 판정 — 내 마켓은 '마켓' 탭 소속으로 본다
const tabActive = (item) => {
    if (item.name === 'market') {
        return ['market', 'my-market'].includes(route.name);
    }

    return route.name === item.name;
};

// 탭 전환 시 다시 마운트·재조회하는 버벅임을 없애기 위해 캐시할 화면
// (상세/수정/채팅 쓰레드 등 상태가 복잡한 화면은 매번 새로 만든다)
const keepAliveViews = ['HomeView', 'MarketView', 'NotificationsView', 'ProfileView', 'CommunityView', 'MyMarketView', 'MoreView'];

// 탭 화면 청크를 우선순위에 따라 미리 내려받아 첫 전환 시 다운로드 지연을 없앤다.
// 자주 열리는 핵심 화면만 즉시 받고, 나머지는 브라우저가 한가할 때 하나씩 순차적으로 받아
// 초기 동시 다운로드로 인한 첫 화면 부하를 줄인다.
const preloadTabViews = () => {
    const preload = (loader) => loader().catch(() => {});

    const core = [
        () => import('./views/HomeView.vue'),
        () => import('./views/MarketView.vue'),
        () => import('./views/orders/MyMarketView.vue'),
        () => import('./views/MoreView.vue'),
    ];

    // 핵심 다음으로 자주 진입하는 화면 — 첫 렌더 후 여유가 생기면 순차 프리로드해
    // 이후 상세/이력/정산 진입 시 청크 다운로드 지연이 없게 한다
    const rest = [
        () => import('./views/orders/OrderDetailView.vue'),
        () => import('./views/orders/OrderCreateView.vue'),
        () => import('./views/orders/RideHistoryView.vue'),
        () => import('./views/orders/ActionCenterView.vue'),
        () => import('./views/SettlementView.vue'),
        () => import('./views/ReviewsView.vue'),
        () => import('./views/NotificationsView.vue'),
        () => import('./views/ProfileView.vue'),
        () => import('./views/community/CommunityView.vue'),
        () => import('./views/ChatView.vue'),
        () => import('./views/SupportView.vue'),
    ];

    // 브라우저 유휴 시 한 번만 실행 (없으면 setTimeout 폴백)
    const idle = (callback) => {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(callback, { timeout: 4000 });
        } else {
            setTimeout(callback, 1500);
        }
    };

    Promise.all(core.map(preload)).finally(() => {
        const schedule = () => {
            const next = rest.shift();

            if (next) {
                preload(next);
                idle(schedule);
            }
        };

        idle(schedule);
    });
};

// 헤더 emit 액션 처리
const handleHeaderAction = (key) => {
    if (key === 'community:write') {
        ui.emitAction('community:write');
    } else if (key === 'orders:filter') {
        ui.emitAction('orders:filter');
    } else if (key === 'filter') {
        ui.emitAction('filter');
    } else if (key === 'order-form:back') {
        ui.emitAction('order-form:back');
    } else if (key === 'thread:back') {
        chats.close();
    }
};

onMounted(async () => {
    theme.init();
    await auth.fetchMe().catch(() => {});
    initReady.value = true;
    // 첫 화면 렌더링이 끝난 뒤 여유를 두고 탭 화면을 미리 내려받는다
    setTimeout(preloadTabViews, 1200);

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

    // 기사 상태 로드 + 실시간/주기 동기화 (하단 '운행 중' 필로트 반영)
    if (auth.user && auth.user.role === 'Driver') {
        driver.load();
        window.addEventListener('app:sse-refresh', driverSync);
        driverTimer = setInterval(driverSync, 60000);
    }
});

// 기사 상태를 최신으로 유지 (운행 수락/완료 시 자동 전환 반영)
const driverSync = () => {
    driver.load();
};

let driverTimer = null;
</script>

<template>
    <n-config-provider
        :theme="theme.isDark ? darkTheme : null"
        :theme-overrides="naiveThemeOverrides(theme.isDark)"
    >
        <n-message-provider>
            <n-dialog-provider>
            <n-notification-provider>
                <div class="app-shell" :class="{ 'app-shell--ready': initReady || !auth.token }">

                <HeaderBar
                    v-if="!['login', 'register', 'password-reset'].includes(route.name)"
                    @action="handleHeaderAction"
                />

                <transition name="notify-banner-fade">
                    <button
                        v-if="auth.isAuthenticated && notifyVisible && notifications.unreadCount > 0"
                        type="button"
                        class="notify-banner"
                        @click="dismissNotify(); router.push({ name: 'notifications' })"
                    >
                        알림 {{ notifications.unreadCount }}건이 도착했습니다 · 탭하여 확인
                    </button>
                </transition>

                <main
                    class="app-content"
                    :class="{
                        'app-content--full':
                            isChatThread
                            || ['login', 'register', 'password-reset'].includes(route.name),
                    }"
                >
                    <!-- keep-alive만 사용: 트랜지션은 iOS에서 사라지는 화면이 남아 클릭을 막는 문제가 있어 제거 -->
                    <router-view v-slot="{ Component }">
                        <keep-alive :include="keepAliveViews">
                            <component :is="Component" />
                        </keep-alive>
                    </router-view>
                </main>

                <!-- 긴 목록에서 맨 위로 복귀 -->
                <ScrollTopButton />

                <nav v-if="auth.isAuthenticated && !chats.activeId && !isFocusedScreen" class="bottom-nav">
                    <router-link
                        v-for="item in navItems.filter((i) => !i.isMore)"
                        :key="item.name"
                        :to="{ name: item.name }"
                        class="bottom-nav__item"
                        :class="{ 'bottom-nav__item--active': tabActive(item) }"
                    >
                        <n-badge
                            :value="item.name === 'chat' ? chats.unreadTotal : 0"
                            :max="99"
                            class="nav-badge"
                            :show="item.name === 'chat' && chats.unreadTotal > 0 && route.name !== 'chat'"
                        >
                            <BaseIcon class="bottom-nav__icon" :name="item.icon ?? item.name" :size="22" />
                        </n-badge>
                        <span>{{ item.label }}</span>
                    </router-link>

                    <!-- 더보기 — 우측 맨끝 -->
                    <router-link
                        :to="{ name: 'more' }"
                        class="bottom-nav__item"
                        :class="{ 'bottom-nav__item--active': moreActive }"
                    >
                        <BaseIcon class="bottom-nav__icon" name="more" :size="22" />
                        <span>더보기</span>
                    </router-link>
                </nav>

                <NotificationListener v-if="auth.isAuthenticated" />
                <ChatListener v-if="auth.isAuthenticated" />
            </div>
            </n-notification-provider>
            </n-dialog-provider>
        </n-message-provider>
    </n-config-provider>
</template>

<style>
/* ── 대화방 전체 화면 — 상·하단 패딩 제거, window 스크롤 제거 ── */
/* 특이도를 높여(0-1-1) base.css의 .app-content 패딩/폭 제한을 확실히 덮어쓴다 */
main.app-content--full {
    padding: 0;
    max-width: none;
    margin: 0;
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
</style>
