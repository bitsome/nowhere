import { createRouter, createWebHistory } from 'vue-router';

// 라우트별 lazy import — 초기 번들을 분할해 첫 로딩을 줄인다
const LoginView = () => import('../views/LoginView.vue');
const RegisterView = () => import('../views/RegisterView.vue');
const HomeView = () => import('../views/HomeView.vue');
const DashboardView = () => import('../views/DashboardView.vue');
const MarketView = () => import('../views/MarketView.vue');
const OrderDetailView = () => import('../views/OrderDetailView.vue');
const OrderCreateView = () => import('../views/OrderCreateView.vue');
const NotificationsView = () => import('../views/NotificationsView.vue');
const ChatView = () => import('../views/ChatView.vue');
const CommunityView = () => import('../views/CommunityView.vue');
const CommunityPostView = () => import('../views/CommunityPostView.vue');
const UserPageView = () => import('../views/UserPageView.vue');
const ProfileView = () => import('../views/ProfileView.vue');
const SettingsView = () => import('../views/SettingsView.vue');
const SettingsProfileView = () => import('../views/SettingsProfileView.vue');
const SettingsVehiclesView = () => import('../views/SettingsVehiclesView.vue');
const SettingsNotificationsView = () => import('../views/SettingsNotificationsView.vue');
const SettingsVerificationView = () => import('../views/SettingsVerificationView.vue');
const SettingsAppearanceView = () => import('../views/SettingsAppearanceView.vue');
const MyMarketView = () => import('../views/MyMarketView.vue');
const ActionCenterView = () => import('../views/ActionCenterView.vue');
const MatchView = () => import('../views/MatchView.vue');
const AdminView = () => import('../views/AdminView.vue');
const MoreView = () => import('../views/MoreView.vue');
const NotFoundView = () => import('../views/NotFoundView.vue');
const RideHistoryView = () => import('../views/RideHistoryView.vue');
const ReviewsView = () => import('../views/ReviewsView.vue');
const SettlementView = () => import('../views/SettlementView.vue');

const routes = [
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: RegisterView },
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
    { path: '/actions', name: 'actions', component: ActionCenterView, meta: { requiresAuth: true } },
    { path: '/more', name: 'more', component: MoreView, meta: { requiresAuth: true } },
    { path: '/history', name: 'history', component: RideHistoryView, meta: { requiresAuth: true } },
    { path: '/reviews', name: 'reviews', component: ReviewsView, meta: { requiresAuth: true } },
    { path: '/settlements', name: 'settlements', component: SettlementView, meta: { requiresAuth: true } },
    { path: '/match', name: 'match', component: MatchView, meta: { requiresAuth: true } },
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

router.beforeEach((to) => {
    const token = localStorage.getItem('auth_token');

    if (to.meta.requiresAuth && !token) {
        return { name: 'login' };
    }

    if (to.name === 'login' || to.name === 'register') {
        if (token) {
            return { name: 'market' };
        }
    }

    // 관리자 전용 화면 — 역할이 Admin/Super Admin이 아니면 마켓으로
    if (to.meta.adminOnly) {
        const role = localStorage.getItem('auth_user_role') ?? '';
        const isAdmin = ['Admin', 'Super Admin'].includes(role);

        if (!isAdmin) {
            return { name: 'market' };
        }
    }
});

export default router;
