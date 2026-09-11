import { apiClient } from './client';

// 기사 정산 — 원장 요약(출금 가능/이번 달/최근 내역/계좌)
export const apiSettlementSummary = () => apiClient.get('/me/settlement');

// 출금 계좌 등록/갱신
export const apiSaveBankAccount = (payload) => apiClient.post('/me/bank-account', payload);

// 출금 신청 — 출금 가능한 정산 전부를 한 건으로 묶는다
export const apiRequestPayout = () => apiClient.post('/me/payouts');

// 내 출금 신청 내역
export const apiMyPayouts = () => apiClient.get('/me/payouts');

// 등록자 정산(청구) — 입금 대기(미수금) 목록·합계·매입 계좌
export const apiMyPayables = () => apiClient.get('/me/payables');

// 관리자 — 입금 확인 대기(수금 전) 정산 원장 목록
export const apiAdminCollections = () => apiClient.get('/admin/settlements/pending-collection');

// 관리자 — 등록자 입금 확인(수금 확정)
export const apiAdminCollectSettlement = (settlementId, payload = {}) => apiClient.post(`/admin/settlements/${settlementId}/collect`, payload);
