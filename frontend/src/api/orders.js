import { apiClient } from './client';

export const apiOrders = (params) => apiClient.get('/orders', { params });

export const apiOrder = (id) => apiClient.get(`/orders/${id}`);

export const apiCreateOrder = (payload) => apiClient.post('/orders', payload);

export const apiCreateSetOrders = (payload) => apiClient.post('/orders/batch', payload);

export const apiUpdateOrder = (id, payload) => apiClient.patch(`/orders/${id}`, payload);

export const apiStructureOrder = (summary) => apiClient.post('/orders/structure', { summary });

export const apiClaimOrder = (id) => apiClient.post(`/orders/${id}/claim`);

export const apiDuplicateOrder = (id) => apiClient.post(`/orders/${id}/duplicate`);

export const apiTransitionOrder = (id, status) => apiClient.post(`/orders/${id}/status`, { status });

export const apiDetachOrder = (id) => apiClient.post(`/orders/${id}/detach`);

export const apiBatchSettle = (ids) => apiClient.post('/orders/batch-settle', { ids });

export const apiOrderOptions = () => apiClient.get('/options/orders');
export const apiReviewOrder = (id, payload) => apiClient.post(`/orders/${id}/review`, payload);
