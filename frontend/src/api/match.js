import { apiClient } from './client';

// 자동 매칭 설정 — 기사가 시간대/지역/가격 조건을 등록하면 운행이 제안된다
export const apiMatchPreferences = () => apiClient.get('/me/match-preferences');

export const apiCreateMatchPreference = (payload) => apiClient.post('/me/match-preferences', payload);

export const apiUpdateMatchPreference = (id, payload) => apiClient.patch(`/me/match-preferences/${id}`, payload);

export const apiDeleteMatchPreference = (id) => apiClient.delete(`/me/match-preferences/${id}`);
