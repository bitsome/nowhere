import { createRouter, createWebHistory } from 'vue-router';
import { ADMIN_ROLES } from '../data/roles';

// 라우트별 lazy import — 초기 번들을 분할해 첫 로딩을 줄인다
const LandingView = () => import('../views/LandingView.vue');
const SharedOrderView = () => import('../views/SharedOrderView.vue');
const LoginView = () => import('../views/auth/LoginView.vue');
const RegisterView = () => import('../views/auth/RegisterView.vue');
const ForgotPasswordView = () => import('../views/auth/ForgotPasswordView.vue');
const HomeView = () => import('../views/HomeView.vue');
const DashboardView = () => import('../views/DashboardView.vue');
const MarketView = () => import('../views/MarketView.vue');
const OrderDetailView = () => import('../views/orders/OrderDetailView.vue');
const OrderCreateView = () => import('../views/orders/OrderCreateView.vue');
const NotificationsView = () => import('../views/NotificationsView.vue');
const ChatView = () => import('../views/ChatView.vue');
const CommunityView = () => import('../views/community/CommunityView.vue');
const CommunityPostView = () => import('../views/community/CommunityPostView.vue');
const UserPageView = () => import('../views/community/UserPageView.vue');
const ProfileView = () => import('../views/ProfileView.vue');
const SettingsView = () => import('../views/settings/SettingsView.vue');
const SettingsProfileView = () => import('../views/settings/SettingsProfileView.vue');
const SettingsVehiclesView = () => import('../views/settings/SettingsVehiclesView.vue');
const SettingsNotificationsView = () => import('../views/settings/SettingsNotificationsView.vue');
const SettingsVerificationView = () => import('../views/settings/SettingsVerificationView.vue');
const SettingsAppearanceView = () => import('../views/settings/SettingsAppearanceView.vue');
const MyMarketView = () => import('../views/orders/MyMarketView.vue');
const ActionCenterView = () => import('../views/orders/ActionCenterView.vue');
const AdminView = () => import('../views/AdminView.vue');
const MoreView = () => import('../views/MoreView.vue');
const SettlementView = () => import('../views/SettlementView.vue');
const RegistrantSettlementView = () => import('../views/RegistrantSettlementView.vue');
const NotFoundView = () => import('../views/NotFoundView.vue');
const RideHistoryView = () => import('../views/orders/RideHistoryView.vue');
const ReviewsView = () => import('../views/ReviewsView.vue');
const SupportView = () => import('../views/SupportView.vue');
const OrderFavoritesView = () => import('../views/OrderFavoritesView.vue');

// 로그인·가입·랜딩 화면 — 로그인 상태로 열면 마켓으로 돌리고, 비로그인 딥링크의 복귀 목적지로는 쓰지 않는다
export const AUTH_PAGES = ['welcome', 'login', 'register', 'password-reset'];

const routes = [
    { path: '/welcome', name: 'welcome', component: LandingView },
    // 공유된 운행 공개 화면 — 로그인 없이 접근 (토큰을 아는 사람만)
    { path: '/share/order/:token', name: 'shared-order', component: SharedOrderView },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: RegisterView },
    { path: '/password/reset', name: 'password-reset', component: ForgotPasswordView },
    { path: '/', name: 'home', component: HomeView, meta: { requiresAuth: true } },
    { path: '/market', name: 'market', component: MarketView, meta: { requiresAuth: true } },
    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/chat', name: 'chat', component: ChatView, meta: { requiresAuth: true } },
    { path: '/notifications', name: 'notifications', component: NotificationsView, meta: { requiresAuth: true } },
    { path: '/community', name: 'community', component: CommunityView, meta: { requiresAuth: true } },
    { path: '/community/posts/:id(\\d+)', name: 'community-post', component: CommunityPostView, meta: { requiresAuth: true } },
    { path: '/users/:id(\\d+)', name: 'user-page', component: UserPageView, meta: { requiresAuth: true } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true } },
    { path: '/settings', name: 'settings', component: SettingsView, meta: { requiresAuth: true } },
    { path: '/settings/profile', name: 'settings-profile', component: SettingsProfileView, meta: { requiresAuth: true } },
    { path: '/settings/vehicles', name: 'settings-vehicles', component: SettingsVehiclesView, meta: { requiresAuth: true } },
    { path: '/settings/notifications', name: 'settings-notifications', component: SettingsNotificationsView, meta: { requiresAuth: true } },
    { path: '/settings/verification', name: 'settings-verification', component: SettingsVerificationView, meta: { requiresAuth: true } },
    { path: '/settings/appearance', name: 'settings-appearance', component: SettingsAppearanceView, meta: { requiresAuth: true } },
    { path: '/my-market', name: 'my-market', component: MyMarketView, meta: { requiresAuth: true } },
    { path: '/favorites', name: 'order-favorites', component: OrderFavoritesView, meta: { requiresAuth: true } },
    { path: '/actions', name: 'actions', component: ActionCenterView, meta: { requiresAuth: true } },
    { path: '/more', name: 'more', component: MoreView, meta: { requiresAuth: true } },
    { path: '/history', name: 'history', component: RideHistoryView, meta: { requiresAuth: true } },
    { path: '/settlement', name: 'settlement', component: SettlementView, meta: { requiresAuth: true } },
    { path: '/registrant-settlement', name: 'registrant-settlement', component: RegistrantSettlementView, meta: { requiresAuth: true } },
    { path: '/reviews', name: 'reviews', component: ReviewsView, meta: { requiresAuth: true } },
    { path: '/support', name: 'support', component: SupportView, meta: { requiresAuth: true } },
    { path: '/admin', name: 'admin', component: AdminView, meta: { requiresAuth: true, adminOnly: true } },
    { path: '/orders/create', name: 'order-create', component: OrderCreateView, meta: { requiresAuth: true } },
    { path: '/orders/:id(\\d+)/edit', name: 'order-edit', component: OrderCreateView, meta: { requiresAuth: true } },
    { path: '/orders/:id(\\d+)', name: 'order-detail', component: OrderDetailView, meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
];

const router = createRouter({
    // 서버 배포 시 VITE_BASE(/spa/)에 맞춰 모든 라우트가 /spa 하위에서 동작한다
    history: createWebHistory(import.meta.env.VITE_BASE || '/'),
    routes,
    // 페이지 진입 시 스크롤 초기화 — 헤더에 콘텐츠 상단이 가려지지 않게
    // 뒤로가기(savedPosition)는 기존 위치 복원, 탭/페이지 이동은 맨 위로
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;

        return { top: 0 };
    },
});

/**
 * 라우터 진입 가드.
 *
 * 비로그인 상태로 로그인 필요 화면(딥링크)에 들어오면 목적지를 기억해 두었다가
 * 로그인·가입을 마친 뒤 그 화면으로 돌려보낸다. 목적지 기억이 없으면
 * 위챗방 등에서 받은 링크로 들어온 기사가 로그인 후 그 운행을 잃는다.
 */
export const authGuard = (to) => {
    const token = localStorage.getItem('auth_token');

    if (to.meta.requiresAuth && !token) {
        // 홈은 랜딩(서비스 소개)으로 보낸다 — 홍보 링크로 들어온 사람이
        // 곧바로 로그인 화면을 만나 이탈하는 것을 막는다.
        if (to.name === 'home') {
            return { name: 'welcome' };
        }

        return { name: 'login', query: { redirect: to.fullPath } };
    }

    // 이미 로그인한 사람이 로그인·가입·랜딩 화면을 열면 마켓으로 보낸다
    if (AUTH_PAGES.includes(to.name) && token) {
        return { name: 'market' };
    }

    // 관리자 전용 화면 — 역할이 Admin/Super Admin이 아니면 마켓으로
    if (to.meta.adminOnly) {
        const role = localStorage.getItem('auth_user_role') ?? '';

        if (!ADMIN_ROLES.includes(role)) {
            return { name: 'market' };
        }
    }
};

router.beforeEach(authGuard);

export default router;
