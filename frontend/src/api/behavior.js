import { apiClient } from './client';

// 행동 이벤트 일괄 저장 — 홈 추천·마켓 카드의 노출/클릭 신호
export const apiTrackEvents = (events) => apiClient.post('/behavior-events', { events });
