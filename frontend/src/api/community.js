import { apiClient } from './client';

export const apiCommunityPosts = (page = 1, params = {}) => apiClient.get('/community/posts', { params: { page, ...params } });

export const apiCommunityPost = (id) => apiClient.get(`/community/posts/${id}`);

export const apiCommunityUser = (id) => apiClient.get(`/community/users/${id}`);

// 설문 선택지 — 서버가 2~10개를 검증하므로 2개 이상일 때만 보낸다 (그 미만은 일반 글로 저장)
const appendSurvey = (form, payload) => {
    if ((payload.survey_options ?? []).length < 2) {
        return;
    }

    payload.survey_options.forEach((option) => form.append('survey_options[]', option));

    if (payload.survey_closes_at) {
        form.append('survey_closes_at', payload.survey_closes_at);
    }
};

// 여행지 맛집 카드 — 값이 있는 항목만 보낸다
const appendPlace = (form, payload) => {
    for (const key of ['place_name', 'place_region', 'place_address', 'place_map_url']) {
        if (payload[key]) {
            form.append(key, payload[key]);
        }
    }
};

export const apiCreateCommunityPost = (payload) => {
    const form = new FormData();

    form.append('content', payload.content);
    form.append('category', payload.category ?? 'free');
    if (payload.image) {
        form.append('image', payload.image);
    }

    appendSurvey(form, payload);
    appendPlace(form, payload);

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

    appendPlace(form, payload);

    return apiClient.put(`/community/posts/${id}`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

export const apiToggleCommunityLike = (id) => apiClient.post(`/community/posts/${id}/like`);

export const apiVoteCommunityPost = (id, optionId) =>
    apiClient.post(`/community/posts/${id}/vote`, { option_id: optionId });

export const apiCommentCommunity = (id, content) =>
    apiClient.post(`/community/posts/${id}/comments`, { content });

export const apiDeleteCommunityComment = (postId, commentId) =>
    apiClient.delete(`/community/posts/${postId}/comments/${commentId}`);
