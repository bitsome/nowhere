import { apiClient } from './client';

export const apiChats = () => apiClient.get('/chats');

export const apiChatMessages = (id) => apiClient.get(`/chats/${id}`);

export const apiSendChatMessage = (id, body) => apiClient.post(`/chats/${id}/messages`, { body });

export const apiCreateChat = (payload) => apiClient.post('/chats', payload);
