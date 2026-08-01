const STORAGE_KEY = 'nowhere:notifications';
const CHANGE_EVENT = 'nowhere:notifications-changed';

const defaultNotifications = () => {
    return [];
};

const normalizeItem = (item, index = 0) => {
    const message = String(item?.message || item?.title || '알림 내용이 없습니다.');
    const time = String(item?.time || '방금 전');
    const type = String(item?.type || 'info').toLowerCase();
    const read = Boolean(item?.read);
    const id = String(item?.id || `${Date.now()}-${index}-${message.slice(0, 12)}`);

    return {
        id,
        message,
        read,
        time,
        type,
    };
};

export function readNotifications() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return defaultNotifications();
        }

        const parsed = JSON.parse(raw);

        if (!Array.isArray(parsed)) {
            return defaultNotifications();
        }

        return parsed.map((item, index) => normalizeItem(item, index));
    } catch (error) {
        return defaultNotifications();
    }
}

export function writeNotifications(items) {
    const normalized = Array.isArray(items)
        ? items.map((item, index) => normalizeItem(item, index))
        : defaultNotifications();

    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
    window.dispatchEvent(new CustomEvent(CHANGE_EVENT, {
        detail: {
            items: normalized,
        },
    }));

    return normalized;
}

export function appendNotification(item) {
    const current = readNotifications();
    const next = [normalizeItem(item), ...current];

    return writeNotifications(next);
}

export function markAllNotificationsRead() {
    const current = readNotifications();
    const next = current.map((item) => ({
        ...item,
        read: true,
    }));

    return writeNotifications(next);
}

export function clearNotifications() {
    return writeNotifications([]);
}

export function onNotificationChange(listener) {
    const wrappedCustomEvent = (event) => {
        const items = event?.detail?.items;
        listener(Array.isArray(items) ? items : readNotifications());
    };

    const wrappedStorageEvent = (event) => {
        if (event?.key && event.key !== STORAGE_KEY) {
            return;
        }

        listener(readNotifications());
    };

    window.addEventListener(CHANGE_EVENT, wrappedCustomEvent);
    window.addEventListener('storage', wrappedStorageEvent);

    return () => {
        window.removeEventListener(CHANGE_EVENT, wrappedCustomEvent);
        window.removeEventListener('storage', wrappedStorageEvent);
    };
}
