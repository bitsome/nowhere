import { apiClient } from './client';

// 운영 관리 — 관리자(Admin/Super Admin) 전용
export const apiAdminUsers = (params = {}) => apiClient.get('/admin/users', { params });
export const apiAdminUpdateVerification = (userId, payload) => apiClient.patch(`/admin/users/${userId}/verification`, payload);

// 기사 운영 — 관리자 화면
export const apiAdminDrivers = (params = {}) => apiClient.get('/admin/drivers', { params });
export const apiAdminSetDriverStatus = (userId, status) => apiClient.patch(`/admin/drivers/${userId}/status`, { status });

// 자동 운행 등록 — 관리자 화면 (중지/시작·건수·등록 계정)
export const apiAdminAutoOrderSettings = () => apiClient.get('/admin/auto-order-settings');
export const apiAdminUpdateAutoOrderSettings = (payload) => apiClient.patch('/admin/auto-order-settings', payload);

// 자동 등록 운행 이력/삭제 — 관리자 화면
export const apiAdminAutoOrders = () => apiClient.get('/admin/auto-orders');
export const apiAdminDeleteAutoOrders = (payload) => apiClient.post('/admin/auto-orders/delete', payload);
