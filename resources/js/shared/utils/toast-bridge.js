import { h, ref } from 'vue';

import ToastContainer from '../components/Toast/ToastContainer.vue';

export function createToastBridge(target, autoCloseDelay = 2800) {
    const items = ref([]);
    let toastSequence = 0;

    createApp({
        setup() {
            const closeToast = (item) => {
                items.value = items.value.filter((toastItem) => toastItem.id !== item.id);
            };

            return () => h(ToastContainer, {
                autoCloseDelay,
                items: items.value,
                onClose: closeToast,
            });
        },
    }).mount(target);

    return ({ message, title, type = 'info' }) => {
        if (!message) {
            return;
        }

        items.value = [
            ...items.value,
            {
                id: `notification-toast-${toastSequence += 1}`,
                message,
                title: title || '',
                type,
            },
        ];
    };
}
