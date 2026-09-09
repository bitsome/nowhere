import { apiClient } from './client';

// 증빙 심사(B-3) — 차량·면허 인증 사진 신청과 관리자 승인/거절

// 인증 신청 — 증빙 사진(multipart) 업로드
export const apiSubmitVerification = (type, image, note = '') => {
    const form = new FormData();

    form.append('type', type);
    form.append('image', image);
    if (note.trim()) {
        form.append('note', note.trim());
    }

    return apiClient.post('/verification/request', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

// 내 인증 현황 — 차량·면허별 최신 심사 상태
export const apiMyVerificationRequests = () => apiClient.get('/verification/requests/mine');

// 관리자 심사 목록 (상태·이름 필터)
export const apiAdminVerifications = (params = {}) => apiClient.get('/admin/verifications', { params });

// 심사 처리 — 승인/거절 (거절 시 사유 필수)
export const apiAdminReviewVerification = (requestId, payload) =>
    apiClient.post(`/admin/verifications/${requestId}/review`, payload);
