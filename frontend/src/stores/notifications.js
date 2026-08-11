import { defineStore } from 'pinia';
import { apiMarkNotificationsRead, apiNotifications } from '../api/notifications';

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        items: [],
        unreadCount: 0,
        total: 0,
        loaded: false,
    }),
    getters: {
        unreadItems: (state) => state.items.filter((notification) => !notification.read),
    },
    actions: {
        async load() {
            const { data } = await apiNotifications({ limit: 30 });

            this.items = data.data;
            this.unreadCount = data.unread_count;
            this.total = data.total;
            this.loaded = true;
        },
        async markRead(ids) {
            await apiMarkNotificationsRead({ ids });
            await this.load();
        },
        async markAllRead() {
            await apiMarkNotificationsRead({ all: true });
            await this.load();
        },
    },
});
