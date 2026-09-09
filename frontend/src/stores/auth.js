import { defineStore } from 'pinia';
import { apiLogin, apiLogout, apiMe, apiRegister } from '../api/auth';
import { ADMIN_ROLES, ROLE_DRIVER } from '../data/roles';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('auth_token') || '',
        user: null,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        isAdmin: (state) => ADMIN_ROLES.includes(state.user?.role),
    },
    actions: {
        async login(login, password) {
            const { data } = await apiLogin(login, password);

            this.token = data.data.token;
            this.user = data.data.user;
            localStorage.setItem('auth_token', this.token);
            localStorage.setItem('auth_user_role', this.user?.role ?? '');
        },
        async register(name, email, password, role = ROLE_DRIVER) {
            const { data } = await apiRegister(name, email, password, role);

            this.token = data.data.token;
            this.user = data.data.user;
            localStorage.setItem('auth_token', this.token);
            localStorage.setItem('auth_user_role', this.user?.role ?? '');
        },
        async fetchMe() {
            if (!this.isAuthenticated) {
                return;
            }

            const { data } = await apiMe();

            this.user = data.data;
            localStorage.setItem('auth_user_role', this.user?.role ?? '');
        },
        async logout() {
            try {
                await apiLogout();
            } catch {
                // 토큰 폐기 실패해도 로컬 상태는 정리한다.
            }

            this.token = '';
            this.user = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user_role');
        },
    },
});
