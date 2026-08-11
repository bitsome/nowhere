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
