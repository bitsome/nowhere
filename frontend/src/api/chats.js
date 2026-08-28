import { apiClient } from './client';

export const apiChats = () => apiClient.get('/chats');

export const apiChatMessages = (id) => apiClient.get(`/chats/${id}`);

// 메시지 전송 — 여러 장 이미지는 image_paths(지문) 배열로 한 개 말풍선에 묶어 보낸다.
// 새 사진은 먼저 apiChatImageUpload로 업로드해 지문을 확보한 뒤 이 함수로 전송한다.
export const apiSendChatMessage = (id, body, imagePaths = []) =>
    apiClient.post(`/chats/${id}/messages`, { body, image_paths: imagePaths });

export const apiCreateChat = (payload) => apiClient.post('/chats', payload);

// 채팅 첨부 이미지 업로드 — 전송 전 단계. 지문(image_path)을 반환해 묶음 전송에 재사용한다.
export const apiChatImageUpload = (file) => {
    const form = new FormData();

    form.append('image', file);

    return apiClient.post('/chats/images', form);
};

// 내가 채팅으로 보낸 이미지 보관함 (사용자별)
// 보관함 조회 — 24장씩 페이지네이션 (page: 1부터)
export const apiChatImageArchive = (page = 1) => apiClient.get('/chats/images/archive', { params: { page } });

// 보관함 이미지 삭제 — 파일까지 함께 정리. 다중 말풍선에서는 지울 이미지(image_path)를 함께 지정한다.
export const apiChatImageDelete = (messageId, imagePath = '') =>
    apiClient.delete(`/chats/images/archive/${messageId}`, { params: { image_path: imagePath } });

// 구조화된 운행 요청 (승인·시간·경로·요금·취소)
export const apiSendChatRequest = (id, type, payload) =>
    apiClient.post(`/chats/${id}/requests`, { type, payload });
