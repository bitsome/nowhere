import { apiClient } from './client';

export const apiChats = () => apiClient.get('/chats');

export const apiChatMessages = (id) => apiClient.get(`/chats/${id}`);

export const apiSendChatMessage = (id, body, image = null) => {
    // 이미지 첨부 시 multipart/form-data, 아니면 JSON
    if (image) {
        const form = new FormData();

        form.append('body', body);
        form.append('image', image);

        return apiClient.post(`/chats/${id}/messages`, form);
    }

    return apiClient.post(`/chats/${id}/messages`, { body });
};

export const apiCreateChat = (payload) => apiClient.post('/chats', payload);

// 구조화된 운행 요청 (승인·시간·경로·요금·취소)
export const apiSendChatRequest = (id, type, payload) =>
    apiClient.post(`/chats/${id}/requests`, { type, payload });
