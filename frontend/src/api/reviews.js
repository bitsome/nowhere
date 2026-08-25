import { apiClient } from './client';

// 받은 리뷰 목록 + 평점 요약 (reviewee 기준)
export const apiReviews = (userId) => apiClient.get('/reviews', { params: { user_id: userId } });

// 내가 쓴 리뷰 목록 (reviewer 기준)
export const apiMyReviews = (userId) => apiClient.get('/reviews', { params: { reviewer_id: userId } });
