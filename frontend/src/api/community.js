import { apiClient } from './client';

export const apiCommunityPosts = (page = 1, params = {}) => apiClient.get('/community/posts', { params: { page, ...params } });

export const apiCommunityPost = (id) => apiClient.get(`/community/posts/${id}`);

export const apiCommunityUser = (id) => apiClient.get(`/community/users/${id}`);

export const apiCreateCommunityPost = (payload) => {
    const form = new FormData();

    form.append('content', payload.content);
    form.append('category', payload.category ?? 'free');
    if (payload.image) {
        form.append('image', payload.image);
    }

    return apiClient.post('/community/posts', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

export const apiDeleteCommunityPost = (id) => apiClient.delete(`/community/posts/${id}`);

export const apiUpdateCommunityPost = (id, payload) => {
    const form = new FormData();

    form.append('content', payload.content);
    form.append('category', payload.category ?? 'free');
    if (payload.image) {
        form.append('image', payload.image);
    }
    if (payload.video_url) {
        form.append('video_url', payload.video_url);
    }

    return apiClient.put(`/community/posts/${id}`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

export const apiToggleCommunityLike = (id) => apiClient.post(`/community/posts/${id}/like`);

export const apiCommentCommunity = (id, content) =>
    apiClient.post(`/community/posts/${id}/comments`, { content });

export const apiDeleteCommunityComment = (postId, commentId) =>
    apiClient.delete(`/community/posts/${postId}/comments/${commentId}`);
