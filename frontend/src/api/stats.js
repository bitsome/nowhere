import { apiClient } from './client';

export const apiOrderStats = (days) => apiClient.get('/stats/orders', { params: { days } });
