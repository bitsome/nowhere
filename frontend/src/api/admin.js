import { apiClient } from './client';

// 운영 관리 — 관리자(Admin/Super Admin) 전용
export const apiAdminUsers = (params = {}) => apiClient.get('/admin/users', { params });
export const apiAdminUpdateVerification = (userId, payload) => apiClient.patch(`/admin/users/${userId}/verification`, payload);
// 역할 변경 — 기사↔등록자 전환(테스트용)과 관리자 지정 (지정 가능 여부는 서버 검증)
export const apiAdminSetUserRole = (userId, role) => apiClient.patch(`/admin/users/${userId}/role`, { role });
// 등록자 개별 수수료율 지정/해제 — null이면 전역 정책 요율을 따른다
export const apiAdminSetUserFeeRate = (userId, feeRate) => apiClient.patch(`/admin/users/${userId}/fee-rate`, { fee_rate: feeRate });

// 기사 운영 — 관리자 화면
export const apiAdminDrivers = (params = {}) => apiClient.get('/admin/drivers', { params });
export const apiAdminSetDriverStatus = (userId, status) => apiClient.patch(`/admin/drivers/${userId}/status`, { status });

// 자동 운행 등록 — 관리자 화면 (중지/시작·건수·등록 계정)
export const apiAdminAutoOrderSettings = () => apiClient.get('/admin/auto-order-settings');
export const apiAdminUpdateAutoOrderSettings = (payload) => apiClient.patch('/admin/auto-order-settings', payload);

// 자동 등록 운행 이력/삭제 — 관리자 화면
export const apiAdminAutoOrders = () => apiClient.get('/admin/auto-orders');
export const apiAdminDeleteAutoOrders = (payload) => apiClient.post('/admin/auto-orders/delete', payload);

// 출금 처리 — 관리자 화면 (기사 출금 신청 지급/거절)
export const apiAdminPayouts = () => apiClient.get('/admin/payouts');
export const apiAdminPayoutPay = (payoutId) => apiClient.post(`/admin/payouts/${payoutId}/pay`);
export const apiAdminPayoutReject = (payoutId, reason = '') =>
    apiClient.post(`/admin/payouts/${payoutId}/reject`, { reason });

// 미매핑 용어 관리 — 사전에 없던 중국어 표기를 한국어로 매핑
export const apiAdminOrderTerms = (params = {}) => apiClient.get('/admin/order-terms', { params });
export const apiAdminSaveOrderTerm = (termId, payload) => apiClient.patch(`/admin/order-terms/${termId}`, payload);
// 용어 직접 등록 — 아직 유입되지 않은 표기도 미리 사전에 넣어 둔다
export const apiAdminCreateOrderTerm = (payload) => apiClient.post('/admin/order-terms', payload);
