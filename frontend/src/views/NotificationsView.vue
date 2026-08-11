<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationsStore } from '../stores/notifications';
import { useUiStore } from '../stores/ui';

const router = useRouter();
const store = useNotificationsStore();
const ui = useUiStore();

// 필터: 전체 / 안읽음
const filter = ref('all');
const filteredItems = computed(() =>
    filter.value === 'unread' ? store.items.filter((notification) => !notification.read) : store.items,
);

// 한글 상대 시간 포맷 (API가 "10 minutes ago" 형식 반환 대응)
function formatTime(val) {
    if (!val) return '';
    const s = String(val);
    const map = [
        [/^just now$/i, '방금'],
        [/^(\d+) minute(s)? ago$/i, (_, n) => `${n}분 전`],
        [/^(\d+) hour(s)? ago$/i, (_, n) => `${n}시간 전`],
        [/^(\d+) day(s)? ago$/i, (_, n) => `${n}일 전`],
        [/^(\d+) week(s)? ago$/i, (_, n) => `${n}주 전`],
        [/^(\d+) month(s)? ago$/i, (_, n) => `${n}개월 전`],
    ];
    for (const [pattern, replacement] of map) {
        const match = s.match(pattern);
        if (match) return typeof replacement === 'function' ? replacement(...match) : replacement;
    }
    const d = new Date(val);
    if (!isNaN(d.getTime())) {
        const month = d.getMonth() + 1;
        return `${month}월 ${d.getDate()}일`;
    }
    return s;
}

onMounted(() => {
    store.load().catch(() => {});
});

// 헤더 '···' 메뉴 — 모두 읽음 / 새로고침 수신
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'notifications:read-all') {
            store.markAllRead();
        } else if (ui.actionName === 'refresh') {
            store.load().catch(() => {});
        }
    },
);

const openNotification = async (notification) => {
    if (!notification.read) {
        await store.markRead([notification.id]);
    }

    if (notification.order_id) {
        router.push({ name: 'order-detail', params: { id: notification.order_id } });
    }
};
</script>

<template>
    <div>
        <n-spin :show="!store.loaded" class="notify-body">
            <!-- 필터 탭 -->
            <div v-if="store.loaded && store.items.length > 0" class="notify-tabs">
                <button
                    type="button"
                    class="notify-tabs__btn"
                    :class="{ 'notify-tabs__btn--active': filter === 'all' }"
                    @click="filter = 'all'"
                >
                    전체
                    <span class="notify-tabs__count">{{ store.items.length }}</span>
                </button>
                <button
                    type="button"
                    class="notify-tabs__btn"
                    :class="{ 'notify-tabs__btn--active': filter === 'unread' }"
                    @click="filter = 'unread'"
                >
                    안읽음
                    <span class="notify-tabs__count" :class="{ 'notify-tabs__count--hot': store.unreadCount > 0 }">
                        {{ store.unreadCount }}
                    </span>
                </button>
            </div>

            <n-empty
                v-if="store.loaded && filteredItems.length === 0"
                :description="filter === 'unread' ? '안읽은 알림이 없습니다.' : '알림이 없습니다.'"
                :image-size="80"
            />

            <div v-else-if="store.loaded" class="notify-card-list">
            <div
                v-for="notification in filteredItems"
                :key="notification.id"
                class="notify-card"
                :class="{ 'notify-card--unread': !notification.read }"
                @click="openNotification(notification)"
            >
                <span class="notify-card__icon" :class="{ 'notify-card__icon--order': notification.order_id }">
                    <svg v-if="notification.order_id" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17a1 1 0 0 0 2 0M16 17a1 1 0 0 0 2 0M4 17V11l2.5-4h11L20 11v6h-1M4.5 11h15" /></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                </span>
                <div class="notify-card__body">
                    <div class="notify-card__title-row">
                        <span class="notify-card__title">{{ notification.title }}</span>
                        <span v-if="!notification.read" class="notify-card__badge">새 알림</span>
                    </div>
                    <div class="notify-card__message">{{ notification.message }}</div>
                    <div class="notify-card__time">
                        <span v-if="notification.order_id" class="notify-card__order-chip">오더</span>
                        {{ notification.created_at }}
                    </div>
                </div>
                <span class="notify-card__arrow">›</span>
            </div>
            </div>
        </n-spin>
    </div>
</template>

<style scoped>
.notify-tabs {
    display: flex;
    gap: 8px;
    max-width: 600px;
    margin: 0 auto 12px;
}

.notify-tabs__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.notify-tabs__btn--active {
    border-color: var(--accent);
    background: var(--accent);
    color: #fff;
}

.notify-tabs__count {
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(128, 128, 128, 0.14);
    font-size: 11px;
    font-weight: 700;
}

.notify-tabs__btn--active .notify-tabs__count {
    background: rgba(255, 255, 255, 0.25);
}

.notify-tabs__count--hot {
    background: rgba(226, 76, 76, 0.14);
    color: #e24c4c;
}

.notify-card-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 600px;
    margin: 0 auto;
}

.notify-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    cursor: pointer;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.notify-card:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    border-color: rgba(54, 173, 255, 0.3);
}

.notify-card--unread {
    border-color: rgba(54, 173, 255, 0.4);
    background: rgba(54, 173, 255, 0.04);
}

.notify-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(128, 128, 128, 0.12);
    color: var(--text-muted);
    flex-shrink: 0;
}

.notify-card__icon svg {
    width: 18px;
    height: 18px;
}

.notify-card__icon--order {
    background: rgba(54, 173, 255, 0.14);
    color: #36adff;
}

.notify-card__body {
    flex: 1;
    min-width: 0;
}

.notify-card__title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.notify-card__title {
    font-size: 14px;
    font-weight: 600;
}

.notify-card--unread .notify-card__title {
    font-weight: 800;
}

.notify-card__badge {
    flex-shrink: 0;
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(226, 76, 76, 0.12);
    color: #e24c4c;
    font-size: 11px;
    font-weight: 700;
}

.notify-card__message {
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 13px;
    word-break: break-word;
    line-height: 1.5;
}

.notify-card__time {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 12px;
}

.notify-card__order-chip {
    padding: 1px 6px;
    border-radius: 6px;
    background: rgba(54, 173, 255, 0.12);
    color: #36adff;
    font-size: 11px;
    font-weight: 700;
}

.notify-card__arrow {
    color: var(--text-muted);
    font-size: 20px;
    font-weight: 300;
    align-self: center;
    flex-shrink: 0;
}
</style>
