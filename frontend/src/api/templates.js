import { apiClient } from './client';

// 운행 등록 템플릿
export const apiTemplates = () => apiClient.get('/order-templates');
export const apiCreateTemplate = (payload) => apiClient.post('/order-templates', payload);
export const apiDeleteTemplate = (id) => apiClient.delete(`/order-templates/${id}`);
