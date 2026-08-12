import { apiClient } from './client';

// mine=true → 로그인 사용자 본인의 운행 기준 통계 (드라이버 대시보드)
export const apiOrderStats = (days, mine = true) => apiClient.get('/stats/orders', { params: { days, mine } });
