import { apiClient } from './client';

// 운영 관리 — 관리자(Admin/Super Admin) 전용
export const apiAdminUsers = (params = {}) => apiClient.get('/admin/users', { params });
export const apiAdminUpdateVerification = (userId, payload) => apiClient.patch(`/admin/users/${userId}/verification`, payload);
