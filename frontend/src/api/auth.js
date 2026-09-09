import { apiClient } from './client';
import { ROLE_DRIVER } from '../data/roles';

// 아이디(이메일) 또는 전화번호로 로그인
export const apiLogin = (login, password) => apiClient.post('/auth/login', { login, password });

// 신규 가입 — 이름·이메일·비밀번호로 계정 생성. 역할: Driver(기사)/Customer(등록자), 기본 기사
export const apiRegister = (name, email, password, role = ROLE_DRIVER) =>
    apiClient.post('/auth/register', { name, email, password, role });

export const apiLogout = () => apiClient.post('/auth/logout');

export const apiMe = () => apiClient.get('/auth/me');

export const apiUpdateProfile = (payload) => apiClient.patch('/auth/me', payload);

// 비밀번호 찾기 — 이메일로 6자리 인증코드 전송
export const apiForgotPassword = (email) => apiClient.post('/auth/password/forgot', { email });

// 비밀번호 재설정 — 인증코드 확인 후 새 비밀번호로 변경
export const apiResetPassword = (payload) => apiClient.post('/auth/password/reset', payload);
