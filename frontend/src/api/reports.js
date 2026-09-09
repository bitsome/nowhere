import { apiClient } from './client';

// 신고/분쟁 — 대상별 유형 옵션 (신고 다이얼로그)
export const apiReportOptions = () => apiClient.get('/reports/options');

// 신고 접수 — 대상(order/user/chat)·유형·사유
export const apiCreateReport = (payload) => apiClient.post('/reports', payload);

// 관리자 — 신고 목록(상태 필터) / 처리 단계 진행
export const apiAdminReports = (params) => apiClient.get('/admin/reports', { params });
export const apiAdminAdvanceReport = (id, payload) => apiClient.patch(`/admin/reports/${id}`, payload);
