import { defineStore } from 'pinia';
import { apiChatMessages, apiChatMessagesSync, apiChats, apiCreateChat, apiDeleteChatMessage, apiMarkAllChatsRead, apiResolveChatRequest, apiSendChatMessage, apiSendChatRequest } from '../api/chats';

export const useChatsStore = defineStore('chats', {
    state: () => ({
        conversations: [],
        activeId: null,
        messages: [],
        // 증분 동기화 기준점 — 마지막으로 받은 메시지 id (0이면 아직 전체를 안 받은 상태)
        lastMessageId: 0,
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
            this.lastMessageId = data.data.reduce((max, m) => Math.max(max, m.id), 0);

            // 대화를 열면 상대 메시지가 읽음 처리되므로 목록의 안 읽음 수를 갱신한다
            await this.loadConversations();
        },
        // 모든 대화방 읽음 — 서버에서 일괄 읽음 처리하고, 목록·탭 배지를 즉시 0으로 정리한다
        async markAllRead() {
            await apiMarkAllChatsRead();

            for (const conversation of this.conversations) {
                conversation.unread_count = 0;
            }
        },
        async reloadMessages() {
            if (!this.activeId) {
                return;
            }

            // 증분 동기화 — 기준점 이후의 새 메시지와 읽음 변화만 받아 가볍게 유지한다.
            // (기준점이 없으면 처음이므로 전체를 받아 기준점을 만든다)
            if (this.lastMessageId > 0) {
                const { data } = await apiChatMessagesSync(this.activeId, this.lastMessageId);
                const { messages: fresh, read_ids: readIds } = data.data;

                // 상대가 읽은 내 메시지는 '읽음' 표시를 즉시 갱신한다
                for (const id of readIds ?? []) {
                    const existing = this.messages.find((m) => m.id === id);

                    if (existing && !existing.read) {
                        existing.read = true;
                    }
                }

                if (fresh.length > 0) {
                    this.messages = [...this.messages, ...fresh];
                    this.lastMessageId = fresh.reduce((max, m) => Math.max(max, m.id), this.lastMessageId);
                }

                return;
            }

            const { data } = await apiChatMessages(this.activeId);
            this.messages = data.data;
            this.lastMessageId = data.data.reduce((max, m) => Math.max(max, m.id), 0);
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

        // 받은 요청 카드 확정 — 수락·거절 후 카드 상태(payload.status)가 갱신되도록 목록을 다시 읽는다
        async resolveRequest(messageId, action, reason = '') {
            if (!this.activeId) {
                return;
            }

            await apiResolveChatRequest(this.activeId, messageId, action, reason);
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
