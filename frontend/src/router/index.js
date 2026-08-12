import { createRouter, createWebHistory } from 'vue-router';

// 라우트별 lazy import — 초기 번들을 분할해 첫 로딩을 줄인다
const LoginView = () => import('../views/LoginView.vue');
const DashboardView = () => import('../views/DashboardView.vue');
const MarketView = () => import('../views/MarketView.vue');
const OrderDetailView = () => import('../views/OrderDetailView.vue');
const OrderCreateView = () => import('../views/OrderCreateView.vue');
const NotificationsView = () => import('../views/NotificationsView.vue');
const ChatView = () => import('../views/ChatView.vue');
const CommunityView = () => import('../views/CommunityView.vue');
const UserPageView = () => import('../views/UserPageView.vue');
const ProfileView = () => import('../views/ProfileView.vue');
const AdminView = () => import('../views/AdminView.vue');

const routes = [
    { path: '/login', name: 'login', component: LoginView },
    { path: '/', name: 'market', component: MarketView, meta: { requiresAuth: true } },
    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/chat', name: 'chat', component: ChatView, meta: { requiresAuth: true } },
    { path: '/notifications', name: 'notifications', component: NotificationsView, meta: { requiresAuth: true } },
    { path: '/community', name: 'community', component: CommunityView, meta: { requiresAuth: true } },
    { path: '/users/:id(\\d+)', name: 'user-page', component: UserPageView, meta: { requiresAuth: true } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true } },
    { path: '/admin', name: 'admin', component: AdminView, meta: { requiresAuth: true, adminOnly: true } },
    { path: '/orders/create', name: 'order-create', component: OrderCreateView, meta: { requiresAuth: true } },
    { path: '/orders/:id(\\d+)/edit', name: 'order-edit', component: OrderCreateView, meta: { requiresAuth: true } },
    { path: '/orders/:id(\\d+)', name: 'order-detail', component: OrderDetailView, meta: { requiresAuth: true } },
];

const router = createRouter({
    // 서버 배포 시 VITE_BASE(/spa/)에 맞춰 모든 라우트가 /spa 하위에서 동작한다
    history: createWebHistory(import.meta.env.VITE_BASE || '/'),
    routes,
});

router.beforeEach((to) => {
    const token = localStorage.getItem('auth_token');

    if (to.meta.requiresAuth && !token) {
        return { name: 'login' };
    }

    if (to.name === 'login' && token) {
        return { name: 'market' };
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
