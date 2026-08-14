import { apiClient } from './client';

// 기사 운영 — 가용 상태 / 오늘 통계 / 차량
export const apiMyDriver = () => apiClient.get('/me/driver');

export const apiSetDriverStatus = (status) => apiClient.patch('/me/driver/status', { status });

export const apiDriverStats = () => apiClient.get('/me/driver/stats');

export const apiMyVehicles = () => apiClient.get('/me/vehicles');

export const apiCreateVehicle = (payload) => apiClient.post('/me/vehicles', payload);

export const apiUpdateVehicle = (id, payload) => apiClient.patch(`/me/vehicles/${id}`, payload);

export const apiDeleteVehicle = (id) => apiClient.delete(`/me/vehicles/${id}`);
