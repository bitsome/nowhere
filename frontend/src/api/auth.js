import { apiClient } from './client';

// 아이디(이메일) 또는 전화번호로 로그인
export const apiLogin = (login, password) => apiClient.post('/auth/login', { login, password });

// 신규 가입 — 이름·이메일·비밀번호로 계정 생성 (역할은 드라이버)
export const apiRegister = (name, email, password) => apiClient.post('/auth/register', { name, email, password });

export const apiLogout = () => apiClient.post('/auth/logout');

export const apiMe = () => apiClient.get('/auth/me');

export const apiUpdateProfile = (payload) => apiClient.patch('/auth/me', payload);
