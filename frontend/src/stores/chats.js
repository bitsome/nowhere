import { defineStore } from 'pinia';
import { apiChatMessages, apiChats, apiCreateChat, apiDeleteChatMessage, apiSendChatMessage, apiSendChatRequest } from '../api/chats';

export const useChatsStore = defineStore('chats', {
    state: () => ({
        conversations: [],
        activeId: null,
        messages: [],
        loaded: false,
    }),
    getters: {
        activeConversation: (state) =>
            state.conversations.find((conversation) => conversation.id === state.activeId) ?? null,
        unreadTotal: (state) => state.conversations.reduce((sum, c) => sum + (c.unread_count ?? 0), 0),
    },
    actions: {
        async loadConversations() {
            const { data } = await apiChats();

            this.conversations = data.data;
            this.loaded = true;
        },
        async open(id) {
            const { data } = await apiChatMessages(id);

            this.activeId = id;
            this.messages = data.data;

            // 대화를 열면 상대 메시지가 읽음 처리되므로 목록의 안 읽음 수를 갱신한다
            await this.loadConversations();
        },
        async reloadMessages() {
            if (!this.activeId) {
                return;
            }

            const { data } = await apiChatMessages(this.activeId);

            // 증분 병합 — 이미 있는 메시지는 유지하고 새 메시지만 추가한다.
            // 전체 교체를 하면 스크롤 위치가 튀고 재렌더가 과해진다.
            const seen = new Set(this.messages.map((m) => m.id));
            const fresh = data.data.filter((m) => !seen.has(m.id));

            if (fresh.length > 0) {
                this.messages = [...this.messages, ...fresh];
            }
        },
        // 여러 장 이미지는 image_paths(지문) 배열로 한 개 말풍선에 묶어 전송한다
        async send(body, imagePaths = []) {
            if (!this.activeId) {
                return;
            }

            await apiSendChatMessage(this.activeId, body, imagePaths);
            await this.reloadMessages();
            await this.loadConversations();
        },

        // 내가 보낸 메시지 삭제 — 목록에서 제거하고 대화 목록도 갱신한다
        async deleteMessage(messageId) {
            if (!this.activeId) {
                return;
            }

            await apiDeleteChatMessage(this.activeId, messageId);
            this.messages = this.messages.filter((m) => m.id !== messageId);
            await this.loadConversations();
        },

        // 구조화된 운행 요청 전송 (승인·시간·경로·요금·취소)
        async sendRequest(type, payload) {
            if (!this.activeId) {
                return;
            }

            await apiSendChatRequest(this.activeId, type, payload);
            await this.reloadMessages();
            await this.loadConversations();
        },

        // 상대(userId)와 (운행 연동) 대화를 시작/열고, 대화방까지 로드한다
        async openWith(userId, orderId) {
            const { data } = await apiCreateChat({ user_id: userId, order_id: orderId ?? null });
            await this.open(data.data.id);
        },

        close() {
            this.activeId = null;
            this.messages = [];
        },
    },
});
