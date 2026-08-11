import { apiClient } from './client';

export const apiNotifications = (params) => apiClient.get('/notifications', { params });

export const apiMarkNotificationsRead = (payload) => apiClient.post('/notifications/read', payload);
