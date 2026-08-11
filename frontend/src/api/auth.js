import { apiClient } from './client';

export const apiLogin = (email, password) => apiClient.post('/auth/login', { email, password });

export const apiLogout = () => apiClient.post('/auth/logout');

export const apiMe = () => apiClient.get('/auth/me');

export const apiUpdateProfile = (payload) => apiClient.patch('/auth/me', payload);
