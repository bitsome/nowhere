import { apiClient } from './client';

// 관리자 개입(B-2) — 문제 운행·사용자만 관리자가 처리하는 운영 업무
// 운행 숨김/보류/강제취소 · 사용자 제재 · 채팅 운영(확인·중재) · 정산 보류 · 일일 요약

// 관리자 화면 옵션 — 제재 상태·운행 상태 라벨 (단일 소스)
export const apiAdminOperationMeta = () => apiClient.get('/admin/operations/meta');

// 일일 운영 요약 — 🔴 즉시 처리 > 🟡 확인 필요 > 🟢 정상
export const apiAdminOperationDaily = () => apiClient.get('/admin/operations/daily');

// 운영 지표 — 정산·매칭·신고·운행 파이프라인 요약 (Q-6)
export const apiAdminOperationMetrics = () => apiClient.get('/admin/operations/metrics');

// 관리자 감사 로그 — 정산·신고·제재·운행 개입·역할 변경 행위 이력
export const apiAdminOperationAudit = () => apiClient.get('/admin/operations/audit');

// 사용자 제재 — 기사·등록자 상태(정상/주의/운행 제한/정지)를 사유와 함께 변경
export const apiAdminModerateUser = (userId, payload) => apiClient.patch(`/admin/users/${userId}/moderation`, payload);

// 운행 목록 — 관리자 개입 대상 검색 (노선·번호·기사·등록자 키워드, 상태 필터)
export const apiAdminOperationOrders = (params = {}) => apiClient.get('/admin/operations/orders', { params });

// 운행 숨김 토글 — 마켓·추천에서 제외 (등록자·관리자는 그대로 확인)
export const apiAdminOrderHide = (orderId, payload) => apiClient.post(`/admin/orders/${orderId}/hide`, payload);

// 운행 보류 토글 — 상태 진행 동결 (일반 사용자 상태 변경 차단)
export const apiAdminOrderHold = (orderId, payload) => apiClient.post(`/admin/orders/${orderId}/hold`, payload);

// 운행 강제 취소 — 진행 단계(승인 대기~운행중) 문제 운행을 즉시 종료
export const apiAdminOrderForceCancel = (orderId, payload) =>
    apiClient.post(`/admin/orders/${orderId}/force-cancel`, payload);

// 대화 목록 — 문제 확인용 (최근 메시지순, 참가자·연결 운행 포함)
export const apiAdminOperationConversations = (params = {}) => apiClient.get('/admin/operations/conversations', { params });

// 대화 내용 읽기 — 관리자는 참가자 메시지를 그대로 확인
export const apiAdminConversationMessages = (conversationId) =>
    apiClient.get(`/admin/conversations/${conversationId}/messages`);

// 관리자 중재 메시지 — 대화에 운영팀 메시지로 기록 (참가자에게 전달)
export const apiAdminModerateConversation = (conversationId, payload) =>
    apiClient.post(`/admin/conversations/${conversationId}/moderate`, payload);

// 미지급 정산 목록 (보류 포함) — 출금 처리와 별개로 확인
export const apiAdminOperationSettlements = () => apiClient.get('/admin/operations/settlements');

// 정산 보류 토글 — 보류된 정산은 출금 신청 대상에서 제외
export const apiAdminSettlementHold = (settlementId, payload) =>
    apiClient.post(`/admin/settlements/${settlementId}/hold`, payload);
