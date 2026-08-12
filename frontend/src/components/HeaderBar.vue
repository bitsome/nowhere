<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useChatsStore } from '../stores/chats';
import { useNotificationsStore } from '../stores/notifications';
import { useThemeStore } from '../stores/theme';
import { useUiStore } from '../stores/ui';

const emit = defineEmits(['open-drawer', 'action']);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const chats = useChatsStore();
const notifications = useNotificationsStore();
const theme = useThemeStore();
const ui = useUiStore();

// 스크롤 여부 — glass 헤더에 그림자를 씌워 경계를 명확히 한다.
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
    market: '운행 마켓', chat: '채팅',
    community: '커뮤니티', profile: '회원정보', notifications: '알림',
    'user-page': '유저 정보', 'order-create': '내 운행',
    'order-edit': '운행 수정', 'order-detail': '운행 상세',
};

const activeConv = computed(() =>
    chats.activeId ? chats.conversations.find((c) => c.id === chats.activeId) : null,
);

// 내 운행 등록/수정 폼 — 집중 화면 (뒤로가기 + 하단 탭 숨김)
const isOrderForm = computed(() => route.name === 'order-create' && ui.orderFormActive);

const label = computed(() => {
    if (route.name === 'chat' && chats.activeId) return activeConv.value?.counterpart?.name ?? '';
    if (isOrderForm.value) return '운행 등록';
    return PAGE_LABEL[route.name] ?? '';
});
// 뒤로가기 + 둥근 유리 헤더 여부
// 메인페이지(마켓·내운행·채팅목록·커뮤니티·내정보): 플랫 헤더
// 서브페이지(대화방·운행상세·등록 등): 둥근 유리 헤더
const showBack = computed(() => {
    if (!route.name) return false;                           // 초기 렌더링 전
    if (route.name === 'chat' && chats.activeId) return true; // 대화방은 glass
    if (route.name === 'chat') return false;                  // 채팅목록은 flat
    if (route.name === 'notifications') return true;          // 알림은 sub-page
    if (isOrderForm.value) return true;                       // 운행 등록/수정 폼은 glass
    return !['market', 'my-orders', 'community', 'profile', 'order-create'].includes(route.name);
});

const goBack = () => {
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

// 알림 벨 — 모든 상위 탭에서 동일하게 노출 (집중 폼에서는 숨김)
const showBell = computed(() => {
    if (isOrderForm.value) return false;

    return ['market', 'order-create', 'chat', 'community', 'profile'].includes(route.name);
});

const profileDotMenu = computed(() => {
    const menus = [
        { label: theme.isDark ? '라이트 모드' : '다크 모드', key: 'theme' },
    ];

    if (auth.isAdmin) {
        menus.push({ label: '운영 관리', key: 'admin' });
    }

    return menus;
});

function doMenu(key) {
    switch (key) {
        case 'admin': router.push({ name: 'admin' }); break;
        case 'theme': theme.toggle(); break;
        default: emit('action', key); break;
    }
}
</script>

<template>
    <!-- 대화방 전용 헤더 -->
    <header v-if="route.name === 'chat' && chats.activeId" class="hb hb--glass hb--solid" :class="{ 'hb--scrolled': scrolled }">
        <div class="hb__inner">
            <button type="button" class="hb-btn" aria-label="뒤로" @click="$emit('action', 'thread:back')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </button>
            <span class="hb__center">{{ label }}</span>
        </div>
    </header>

    <!-- 일반 헤더 -->
    <header v-else class="hb" :class="{ 'hb--glass': showBack, 'hb--scrolled': showBack && scrolled }">
        <div class="hb__inner">
            <div class="hb__left">
                <button v-if="showBack" type="button" class="hb-btn" aria-label="뒤로" @click="goBack()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <button v-else type="button" class="hb-btn" aria-label="전체메뉴" @click="$emit('open-drawer')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="4" y1="6" x2="20" y2="6" /><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="18" x2="20" y2="18" />
                    </svg>
                </button>

                <template v-if="route.name === 'chat'">
                    <span class="hb__label" />
                </template>
                <span v-else-if="showBack" class="hb__label">{{ label }}</span>
            </div>

            <div class="hb__right">
                <template v-if="route.name === 'market' || (route.name === 'order-create' && !isOrderForm.value)">
                    <button type="button" class="hb-btn" aria-label="필터" @click="$emit('action', 'filter')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                        <span v-if="ui.filterActive" class="hb-filter-dot" />
                    </button>
                </template>

                <template v-if="route.name === 'community'">
                    <button type="button" class="hb-btn" aria-label="글쓰기" @click="$emit('action', 'community:write')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" /></svg>
                    </button>
                </template>

                <!-- 알림 벨 — 모든 상위 탭 공통 -->
                <router-link v-if="showBell" to="/notifications" class="hb-btn" aria-label="알림">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                    <span v-if="notifications.unreadCount > 0" class="hb-badge">{{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}</span>
                </router-link>

                <template v-if="route.name === 'profile'">
                    <n-dropdown trigger="click" :options="profileDotMenu" @select="doMenu">
                        <button type="button" class="hb-btn hb-btn--dots" aria-label="더보기">
                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="12" cy="19" r="1.6" /></svg>
                        </button>
                    </n-dropdown>
                </template>
            </div>
        </div>
    </header>
</template>

<style scoped>
.hb{position:sticky;top:0;z-index:10;background:var(--surface);border-bottom:1px solid var(--border)}
.hb--glass{background:transparent;border-bottom:0;padding:10px 8px 0}
.hb--glass .hb__inner{border-radius:20px;border:1px solid rgba(0,0,0,.06);background:rgba(255,255,255,.72);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 2px 12px rgba(0,0,0,.04)}
.hb--scrolled .hb__inner{border-color:rgba(0,0,0,.1);box-shadow:0 6px 20px rgba(0,0,0,.1)}
html.dark .hb--scrolled .hb__inner{border-color:rgba(255,255,255,.12);box-shadow:0 6px 24px rgba(0,0,0,.45)}
html.dark .hb--glass .hb__inner{background:rgba(28,28,34,.88);border-color:rgba(255,255,255,.08)}
.hb__inner{max-width:880px;margin:0 auto;display:flex;align-items:center;gap:6px;padding:8px 14px}
.hb__left{display:flex;align-items:center;gap:6px;flex:1;min-width:0}
.hb__right{display:flex;align-items:center;gap:4px;flex-shrink:0}
.hb__center{font-size:15px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;text-align:center;min-width:0}
.hb__label{font-size:15px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hb-btn{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border:0;border-radius:50%;background:transparent;color:var(--text);cursor:pointer;flex-shrink:0;text-decoration:none;transition:background .12s ease;position:relative}
.hb-btn:hover{background:rgba(0,0,0,.06)}
html.dark .hb-btn:hover{background:rgba(255,255,255,.08)}
html.dark .hb-btn{color:#e2e2e2}
html.dark .hb__label{color:#e2e2e2}
html.dark .hb__center{color:#e2e2e2}
.hb-btn svg{width:19px;height:19px}

/* 알림 미확인 배지 */
.hb-badge{position:absolute;top:0;right:0;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#e24c4c;color:#fff;font-size:10px;font-weight:700;line-height:16px;text-align:center;box-shadow:0 0 0 2px var(--surface)}
.hb-btn--dots{width:32px;height:32px}
.hb-btn--dots svg{width:16px;height:16px}

/* 필터 활성 점 */
.hb-filter-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#36adff;box-shadow:0 0 0 2px var(--surface)}
.hb-avatar{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border:0;border-radius:50%;background:var(--accent);color:#fff;font-size:13px;font-weight:700;cursor:pointer;flex-shrink:0}

/* 대화방 헤더 — 메시지가 뒤로 비치지 않도록 불투명 (마지막에 정의해 glass 규칙을 덮음) */
.hb--glass.hb--solid{background:var(--surface);border-bottom:1px solid var(--border);padding:0}
.hb--glass.hb--solid .hb__inner{border-radius:0;border:0;background:transparent;backdrop-filter:none;-webkit-backdrop-filter:none;box-shadow:none}
.hb--glass.hb--solid.hb--scrolled .hb__inner{box-shadow:0 2px 10px rgba(0,0,0,.06)}
</style>
