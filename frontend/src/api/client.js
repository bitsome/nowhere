import axios from 'axios';

/**
 * API 클라이언트 — 독립 SPA 전용.
 * Bearer 토큰을 자동 첨부하고, 401 발생 시 로그인으로 이동한다.
 *
 * 토큰은 localStorage에 보관한다(개발/프로토타입 단계).
 * 운영 전 XSS 대비 메모리(Pinia) + httponly 쿠키 조합으로 재검토한다.
 */
export const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: { Accept: 'application/json' },
});

apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && !error.config?.url?.includes('/auth/login')) {
            localStorage.removeItem('auth_token');

            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }

        return Promise.reject(error);
    },
);

/**
 * API 에러에서 사용자에게 보여줄 메시지를 추출한다.
 */
export function getApiErrorMessage(error, fallback = '요청을 처리하지 못했습니다.') {
    const data = error?.response?.data;

    if (typeof data?.message === 'string' && data.message !== '') {
        return data.message;
    }

    const firstError = Object.values(data?.errors ?? {})[0];

    if (Array.isArray(firstError) && firstError.length > 0) {
        return firstError[0];
    }

    return fallback;
}
