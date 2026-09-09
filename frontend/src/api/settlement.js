import { apiClient } from './client';

// 기사 정산 — 원장 요약(출금 가능/이번 달/최근 내역/계좌)
export const apiSettlementSummary = () => apiClient.get('/me/settlement');

// 출금 계좌 등록/갱신
export const apiSaveBankAccount = (payload) => apiClient.post('/me/bank-account', payload);

// 출금 신청 — 출금 가능한 정산 전부를 한 건으로 묶는다
export const apiRequestPayout = () => apiClient.post('/me/payouts');

// 내 출금 신청 내역
export const apiMyPayouts = () => apiClient.get('/me/payouts');
