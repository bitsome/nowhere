import { apiClient } from './client';

// 고객지원(B-4) — 공지·FAQ 조회와 1:1 문의

// 공지·FAQ 목록 (kind: notice | faq, 생략 시 전체)
export const apiSupportPosts = (params = {}) => apiClient.get('/support/posts', { params });

// 1:1 문의 작성
export const apiCreateSupportTicket = (payload) => apiClient.post('/support/tickets', payload);

// 내 문의 목록
export const apiMySupportTickets = () => apiClient.get('/support/tickets/mine');

// 관리자 — 공지/FAQ 작성·수정·삭제
export const apiAdminSupportPosts = (params = {}) => apiClient.get('/admin/support/posts', { params });
export const apiAdminCreateSupportPost = (payload) => apiClient.post('/admin/support/posts', payload);
export const apiAdminUpdateSupportPost = (postId, payload) => apiClient.patch(`/admin/support/posts/${postId}`, payload);
export const apiAdminDeleteSupportPost = (postId) => apiClient.delete(`/admin/support/posts/${postId}`);

// 관리자 — 1:1 문의 목록·답변
export const apiAdminSupportTickets = (params = {}) => apiClient.get('/admin/support/tickets', { params });
export const apiAdminAnswerSupportTicket = (ticketId, payload) =>
    apiClient.patch(`/admin/support/tickets/${ticketId}/answer`, payload);
