import { apiClient } from './client';

// 기사 운영 — 가용 상태 / 오늘 통계 / 차량
export const apiMyDriver = () => apiClient.get('/me/driver');

export const apiSetDriverStatus = (status) => apiClient.patch('/me/driver/status', { status });

// 자동 매칭(콜링) 시작/중지
export const apiSetDriverMatchEnabled = (enabled) => apiClient.patch('/me/driver/match', { enabled });

export const apiDriverStats = () => apiClient.get('/me/driver/stats');

// 정산 내역 — 기간별 완료 운행 + 합계 (from/to: YYYY-MM-DD)
export const apiSettlements = (params) => apiClient.get('/me/settlements', { params });

export const apiMyVehicles = () => apiClient.get('/me/vehicles');

export const apiCreateVehicle = (payload) => apiClient.post('/me/vehicles', payload);

export const apiUpdateVehicle = (id, payload) => apiClient.patch(`/me/vehicles/${id}`, payload);

export const apiDeleteVehicle = (id) => apiClient.delete(`/me/vehicles/${id}`);
