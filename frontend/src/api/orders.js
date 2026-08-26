import { apiClient } from './client';

export const apiOrders = (params) => apiClient.get('/orders', { params });

// 왕복 노선 추천 — 내가 맡은 운행의 하차지 근처에서 시작하는 마켓 운행
export const apiReturnRoutes = () => apiClient.get('/orders/return-routes');

export const apiOrder = (id) => apiClient.get(`/orders/${id}`);

export const apiCreateOrder = (payload) => apiClient.post('/orders', payload);

export const apiCreateSetOrders = (payload) => apiClient.post('/orders/batch', payload);

export const apiUpdateOrder = (id, payload) => apiClient.patch(`/orders/${id}`, payload);

export const apiStructureOrder = (summary) => apiClient.post('/orders/structure', { summary });

export const apiClaimOrder = (id) => apiClient.post(`/orders/${id}/claim`);
export const apiApproveClaim = (id) => apiClient.post(`/orders/${id}/claim/approve`);
export const apiRejectClaim = (id) => apiClient.post(`/orders/${id}/claim/reject`);

// 요금 제안(오퍼) — 기사가 운임을 제안하고 등록자가 수락/거절
export const apiOrderOffers = (id) => apiClient.get(`/orders/${id}/offers`);
export const apiCreateOffer = (id, payload) => apiClient.post(`/orders/${id}/offers`, payload);
export const apiAcceptOffer = (orderId, offerId) => apiClient.post(`/orders/${orderId}/offers/${offerId}/accept`);
export const apiRejectOffer = (orderId, offerId) => apiClient.post(`/orders/${orderId}/offers/${offerId}/reject`);
export const apiDeleteOffer = (orderId, offerId) => apiClient.delete(`/orders/${orderId}/offers/${offerId}`);

// 제안 받은 편지함 — 내 공개 운행의 대기 제안 목록 (비교·수락용 허브)
export const apiOfferInbox = () => apiClient.get('/offers/inbox');

export const apiDuplicateOrder = (id) => apiClient.post(`/orders/${id}/duplicate`);

export const apiTransitionOrder = (id, status, cancelReason = '', actualRevenue = null) => apiClient.post(`/orders/${id}/status`, {
    status,
    cancel_reason: cancelReason,
    actual_revenue: actualRevenue,
});

export const apiDetachOrder = (id) => apiClient.post(`/orders/${id}/detach`);

export const apiBatchSettle = (ids) => apiClient.post('/orders/batch-settle', { ids });

export const apiOrderOptions = () => apiClient.get('/options/orders');
export const apiReviewOrder = (id, payload) => apiClient.post(`/orders/${id}/review`, payload);
