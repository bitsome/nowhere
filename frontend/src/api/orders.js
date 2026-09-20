import { apiClient } from './client';

export const apiOrders = (params) => apiClient.get('/orders', { params });

// 왕복 노선 추천 — 내가 맡은 운행의 하차지 근처에서 시작하는 마켓 운행 (현재 마켓 필터 반영)
export const apiReturnRoutes = (params) => apiClient.get('/orders/return-routes', { params });

// 홈 강력추천 — 양방향 왕복(연결 체인/마켓 왕복 짝)을 내려주는 추천 목록
export const apiRecommendations = () => apiClient.get('/orders/recommendations');

export const apiOrder = (id) => apiClient.get(`/orders/${id}`);

// 찜(즐겨찾기) 토글 — 마켓 운행을 보관/해제한다 (응답: { favorited: bool })
export const apiToggleFavorite = (id) => apiClient.post(`/orders/${id}/favorite`);

// 왕복 체인 일괄 가져오기 요청 — 체인에 포함된 운행들을 등록자들에게 한 번에 요청한다
export const apiBatchClaim = (orderIds) => apiClient.post('/orders/batch-claim', { order_ids: orderIds });

// 보낸 가져오기 요청들의 현재 상태 요약 — 홈 일괄요청중 카드의 승인/거절/남은 시간 집계
export const apiClaimSummary = (orderIds) => apiClient.post('/orders/claims/summary', { order_ids: orderIds });

export const apiCreateOrder = (payload) => apiClient.post('/orders', payload);

export const apiCreateSetOrders = (payload) => apiClient.post('/orders/batch', payload);

// N건 일괄 등록 — 각 운행이 셋트로 묶이지 않는 독립 운행이 된다 (publish=true면 공개 가능한 건만 공개)
export const apiCreateBulkOrders = (payload) => apiClient.post('/orders/bulk', payload);

export const apiUpdateOrder = (id, payload) => apiClient.patch(`/orders/${id}`, payload);

export const apiStructureOrder = (summary) => apiClient.post('/orders/structure', { summary });

export const apiClaimOrder = (id) => apiClient.post(`/orders/${id}/claim`);
export const apiWithdrawClaim = (id) => apiClient.post(`/orders/${id}/claim/withdraw`);
// 만료된 요청 자동 철회 — 재신청 잠금 없이 운행을 마켓으로 돌려 다시 요청할 수 있게 한다
export const apiWithdrawExpiredClaim = (id) => apiClient.post(`/orders/${id}/claim/withdraw-expired`);
export const apiApproveClaim = (id, claimId) => apiClient.post(`/orders/${id}/claim/${claimId}/approve`);
export const apiRejectClaim = (id, claimId) => apiClient.post(`/orders/${id}/claim/${claimId}/reject`);

// 요금 제안(오퍼) — 기사가 운임을 제안하고 등록자가 수락/거절
export const apiOrderOffers = (id) => apiClient.get(`/orders/${id}/offers`);
export const apiCreateOffer = (id, payload) => apiClient.post(`/orders/${id}/offers`, payload);
export const apiAcceptOffer = (orderId, offerId) => apiClient.post(`/orders/${orderId}/offers/${offerId}/accept`);
// 거절 사유(선택) — 기록되어 제안한 기사에게 전달된다
export const apiRejectOffer = (orderId, offerId, payload = {}) => apiClient.post(`/orders/${orderId}/offers/${offerId}/reject`, payload);
export const apiDeleteOffer = (orderId, offerId) => apiClient.delete(`/orders/${orderId}/offers/${offerId}`);

// 처리할 일(액션 센터) — 가져오기 승인·요금 제안·채팅 요청을 한 페이지로
export const apiActions = () => apiClient.get('/actions');

export const apiDuplicateOrder = (id) => apiClient.post(`/orders/${id}/duplicate`);

// 운행 상태 전환 — 운행 시작(driving) 시에는 기사 기기 위치(coords)를 함께 보내 1회 기록한다.
// 위치는 없어도 되고, 없으면 좌표 없이 그대로 전이된다.
export const apiTransitionOrder = (id, status, cancelReason = '', actualRevenue = null, coords = null) => apiClient.post(`/orders/${id}/status`, {
    status,
    cancel_reason: cancelReason,
    actual_revenue: actualRevenue,
    ...(coords ? { latitude: coords.latitude, longitude: coords.longitude } : {}),
});

// 운행중 세부 단계 진행 — 카드 단계 스테퍼(운행시작→픽업 도착→승객 도착→출발→이동중→도착지 도착)
// 마지막 '도착지 도착'을 기록하면 운행이 자동으로 완료 처리된다 (actual_revenue 동시 전달 가능)
export const apiAdvanceRideStep = (id, payload = {}) => apiClient.post(`/orders/${id}/ride-step`, payload);

export const apiDetachOrder = (id) => apiClient.post(`/orders/${id}/detach`);

// 상세 정보 부족 시 등록자에게 더 자세한 입력 요청 (사유·메모와 함께 알림 발송)
export const apiRequestOrderDetails = (id, payload = {}) => apiClient.post(`/orders/${id}/request-details`, payload);

export const apiBatchSettle = (ids) => apiClient.post('/orders/batch-settle', { ids });

export const apiOrderOptions = () => apiClient.get('/options/orders');
export const apiReviewOrder = (id, payload) => apiClient.post(`/orders/${id}/review`, payload);

// 운행 공유 링크 발급 — 등록자가 카카오 오픈채팅·카페 등 외부에 뿌릴 공개 주소
export const apiShareOrder = (id) => apiClient.post(`/orders/${id}/share`);

// 공유된 운행 공개 조회 — 로그인 없이 토큰으로 접근 (고객 실명·연락처는 내려오지 않음)
export const apiPublicOrder = (token) => apiClient.get(`/public/orders/${token}`);
