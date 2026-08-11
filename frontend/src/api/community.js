import { apiClient } from './client';

export const apiCommunityPosts = (page = 1) => apiClient.get('/community/posts', { params: { page } });

export const apiCommunityPost = (id) => apiClient.get(`/community/posts/${id}`);

export const apiCommunityUser = (id) => apiClient.get(`/community/users/${id}`);

export const apiCreateCommunityPost = (payload) => {
    const form = new FormData();

    form.append('content', payload.content);
    if (payload.image) {
        form.append('image', payload.image);
    }

    return apiClient.post('/community/posts', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

export const apiDeleteCommunityPost = (id) => apiClient.delete(`/community/posts/${id}`);

export const apiToggleCommunityLike = (id) => apiClient.post(`/community/posts/${id}/like`);

export const apiCommentCommunity = (id, content) =>
    apiClient.post(`/community/posts/${id}/comments`, { content });
