import { defineStore } from 'pinia';
import { apiChatMessages, apiChats, apiCreateChat, apiSendChatMessage } from '../api/chats';

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
        async send(body) {
            if (!this.activeId) {
                return;
            }

            await apiSendChatMessage(this.activeId, body);
            await this.reloadMessages();
            await this.loadConversations();
        },

        // 상대(userId)와 (오더 연동) 대화를 시작/열고, 대화방까지 로드한다
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
