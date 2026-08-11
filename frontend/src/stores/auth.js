import { defineStore } from 'pinia';
import { apiLogin, apiLogout, apiMe } from '../api/auth';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('auth_token') || '',
        user: null,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.token),
    },
    actions: {
        async login(email, password) {
            const { data } = await apiLogin(email, password);

            this.token = data.data.token;
            this.user = data.data.user;
            localStorage.setItem('auth_token', this.token);
        },
        async fetchMe() {
            if (!this.isAuthenticated) {
                return;
            }

            const { data } = await apiMe();

            this.user = data.data;
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
        },
    },
});
