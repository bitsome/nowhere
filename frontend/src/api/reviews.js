import { apiClient } from './client';

// 사용자 리뷰 목록 + 평점 요약 (reviewee 기준)
export const apiReviews = (userId) => apiClient.get('/reviews', { params: { user_id: userId } });
