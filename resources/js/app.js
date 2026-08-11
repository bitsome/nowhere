import { createApp, h, ref } from 'vue';

import DataTablePlayground from './shared/components/DataTable/DataTablePlayground.vue';
import DialogPlayground from './modules/dialog/DialogPlayground.vue';
import ButtonPlayground from './shared/components/Button/ButtonPlayground.vue';
import IconPlayground from './shared/components/Icon/IconPlayground.vue';
import ModalPlayground from './shared/components/Modal/ModalPlayground.vue';
import DropdownActionMenu from './shared/components/Dropdown/DropdownActionMenu.vue';
import DropdownPlayground from './shared/components/Dropdown/DropdownPlayground.vue';
import DropdownTypesPlayground from './shared/components/Dropdown/DropdownTypesPlayground.vue';
import BaseLoading from './shared/components/Loading/BaseLoading.vue';
import ToastContainer from './shared/components/Toast/ToastContainer.vue';
import ToastEditorField from './shared/components/ToastEditor/ToastEditorField.vue';
import ToastEditorPlayground from './shared/components/ToastEditor/ToastEditorPlayground.vue';
import ToastViewerField from './shared/components/ToastEditor/ToastViewerField.vue';
import { createToastBridge } from './shared/utils/toast-bridge.js';
import HeaderNotificationMount from './shared/layouts/AppLayout/components/Header/Notification/HeaderNotificationMount.vue';
import {
    appendNotification,
    clearNotifications,
    markAllNotificationsRead,
    onNotificationChange,
    readNotifications,
} from './shared/notifications/notificationStore.js';
import { initializeOrderAiStructuring } from './business/order/ai-structuring.js';
import { mountOrderGroupTypeSync, mountOrderLineItemEditors } from './business/order/line-items.js';
import { mountOrderCardList, mountOrderDataTable, mountOrderViewToggle, mountOrderTabMenu, mountViewToggleDemo } from './business/order/workspace.js';
import { mountUserDataTable } from './business/users/user-table.js';
import { mountUserRowActions } from './business/users/user-actions.js';
import { mountTableActions } from './business/row-actions.js';
import { mountUploadGallery } from './business/gallery.js';

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function buildNotificationItem(notification) {
    const type = String(notification.type || 'info').toLowerCase();
    const dotColor = {
        success: 'bg-[#4ade80]',
        warning: 'bg-[#facc15]',
        info: 'bg-[#60a5fa]',
    }[type] || 'bg-[#60a5fa]';

    const message = escapeHtml(notification.message || '알림 내용이 없습니다.');
    const time = escapeHtml(notification.time || '방금 전');
    const statusLabel = notification.read ? '읽음' : '읽지 않음';
    const statusClass = notification.read
        ? 'border-[#d8d8d8] bg-[#f1f1f1] text-[#6a6a6a] dark:border-[#343434] dark:bg-[#202020] dark:text-[#9ea1a8]'
        : 'border-[#d0d0d0] bg-[#ececec] text-[#1f1f1f] dark:border-[#3a3a3a] dark:bg-[#252526] dark:text-[#f3f3f3]';

    return `
        <article class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="mt-2 inline-flex h-2.5 w-2.5 flex-shrink-0 rounded-full ${dotColor}"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium leading-6 text-gray-900 dark:text-gray-100">${message}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">${time}</p>
                    </div>
                </div>
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${statusClass}">${statusLabel}</span>
            </div>
        </article>
    `;
}


function initializeDashboardNotificationTest() {
    const root = document.querySelector('[data-dashboard-notification-test]');

    if (!root) {
        return;
    }

    const form = root.querySelector('[data-notification-form]');
    const receiveButton = root.querySelector('[data-notification-receive]');
    const readAllButton = root.querySelector('[data-notification-read-all]');
    const resetButton = root.querySelector('[data-notification-reset]');
    const listElement = root.querySelector('[data-notification-list]');
    const emptyElement = root.querySelector('[data-notification-empty]');
    const totalElement = root.querySelector('[data-notification-total]');
    const unreadElement = root.querySelector('[data-notification-unread]');
    const badgeElement = root.querySelector('[data-notification-badge]');
    const lastActionElement = root.querySelector('[data-notification-last-action]');
    const toastElement = document.querySelector('[data-notification-toast]');

    if (
        !form
        || !receiveButton
        || !readAllButton
        || !resetButton
        || !listElement
        || !emptyElement
        || !totalElement
        || !unreadElement
        || !badgeElement
        || !lastActionElement
        || !toastElement
    ) {
        return;
    }

    const sampleNotifications = [
        { message: '새 오더가 등록되었습니다.', time: '1분 전', type: 'success', read: false },
        { message: '배차가 변경되었습니다.', time: '10분 전', type: 'warning', read: false },
        { message: '기사님이 온라인 상태입니다.', time: '20분 전', type: 'info', read: false },
    ];

    let notifications = readNotifications();
    const pushToast = createToastBridge(toastElement);

    const render = () => {
        const unreadCount = notifications.filter((notification) => !notification.read).length;

        totalElement.textContent = String(notifications.length);
        unreadElement.textContent = String(unreadCount);
        badgeElement.textContent = `${notifications.length}건`;
        listElement.innerHTML = notifications.map(buildNotificationItem).join('');
        emptyElement.classList.toggle('hidden', notifications.length > 0);
        readAllButton.disabled = notifications.length === 0;
    };

    const updateLastAction = (message) => {
        lastActionElement.textContent = message;
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const message = String(formData.get('message') || '').trim();
        const time = String(formData.get('time') || '').trim() || '방금 전';
        const type = String(formData.get('type') || 'info');

        if (!message) {
            return;
        }

        notifications = appendNotification({
            message,
            time,
            type,
            read: false,
        });

        form.reset();
        form.querySelector('[name="type"]').value = 'info';
        updateLastAction(`테스트 알림을 보냈습니다: ${message}`);
        pushToast({
            message: `테스트 알림을 보냈습니다: ${message}`,
            title: '저장되었습니다.',
            type: type === 'success' ? 'success' : 'info',
        });
        render();
    });

    receiveButton.addEventListener('click', () => {
        const sample = sampleNotifications[Math.floor(Math.random() * sampleNotifications.length)];

        notifications = appendNotification({
            ...sample,
            read: false,
        });

        updateLastAction(`샘플 알림을 받았습니다: ${sample.message}`);
        pushToast({
            message: `샘플 알림을 받았습니다: ${sample.message}`,
            title: '알림이 도착했습니다.',
            type: sample.type === 'success' ? 'success' : 'info',
        });
        render();
    });

    readAllButton.addEventListener('click', () => {
        notifications = markAllNotificationsRead();

        updateLastAction('모든 알림을 읽음 처리했습니다.');
        pushToast({
            message: '모든 알림을 읽음 처리했습니다.',
            title: '저장되었습니다.',
            type: 'success',
        });
        render();
    });

    resetButton.addEventListener('click', () => {
        notifications = clearNotifications();
        updateLastAction('알림 목록을 초기화했습니다.');
        pushToast({
            message: '알림 목록을 초기화했습니다.',
            title: '삭제되었습니다.',
            type: 'success',
        });
        render();
    });

    const detachListener = onNotificationChange((items) => {
        notifications = items;
        render();
    });

    window.addEventListener('beforeunload', detachListener, { once: true });
    render();
}

function getDropdownPlaygroundProps(target) {
    return {
        csrfToken: target.dataset.csrfToken || '',
        dashboardUrl: target.dataset.dashboardUrl || '#',
        description: target.dataset.description || '',
        logoutUrl: target.dataset.logoutUrl || '#',
        myOrdersUrl: target.dataset.myOrdersUrl || '#',
        notificationUrl: target.dataset.notificationUrl || '/dashboard/modules/notification',
        profileUrl: target.dataset.profileUrl || '#',
        title: target.dataset.title || 'Dashboard',
        triggerLabel: target.dataset.triggerLabel || '바로가기 메뉴',
    };
}

function mountDropdownPlaygrounds() {
    const targets = document.querySelectorAll('[data-dropdown-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(DropdownPlayground, getDropdownPlaygroundProps(target)).mount(target);
    });
}

function mountDropdownTypesPlaygrounds() {
    const targets = document.querySelectorAll('[data-dropdown-types-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(DropdownTypesPlayground, {
            variant: target.dataset.dropdownTypesPlayground || 'trigger',
        }).mount(target);
    });
}

function mountToastPlaygrounds() {
    const targets = document.querySelectorAll('[data-toast-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        const pushToast = createToastBridge(target);

        document.querySelectorAll('[data-toast-push]').forEach((button) => {
            button.addEventListener('click', () => {
                pushToast({
                    message: button.dataset.toastMessage || '토스트 메시지입니다.',
                    title: button.dataset.toastTitle || '',
                    type: button.dataset.toastPush || 'info',
                });
            });
        });
    });
}

function mountLoadingPlaygrounds() {
    const targets = document.querySelectorAll('[data-loading-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        const active = ref(false);

        createApp({
            setup() {
                return () => h(BaseLoading, { active: active.value });
            },
        }).mount(target);

        document.querySelectorAll('[data-loading-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                active.value = true;

                window.setTimeout(() => {
                    active.value = false;
                }, 2000);
            });
        });
    });
}

function mountDialogPlaygrounds() {
    const targets = document.querySelectorAll('[data-dialog-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(DialogPlayground).mount(target);
    });
}

function mountDialogs() {
    const targets = document.querySelectorAll('[data-dialog]');

    if (!targets.length) {
        return;
    }

    const closeDialog = (dialog) => {
        dialog.hidden = true;
    };

    document.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-dialog-open]');

        if (openTrigger) {
            const dialog = document.querySelector(openTrigger.dataset.dialogOpen);

            if (dialog) {
                dialog.hidden = false;
            }

            return;
        }

        const closeTrigger = event.target.closest('[data-dialog-close]');

        if (closeTrigger) {
            const dialog = closeTrigger.closest('[data-dialog]');

            if (dialog) {
                closeDialog(dialog);
            }

            return;
        }

        const confirmTrigger = event.target.closest('[data-dialog-confirm]');

        if (confirmTrigger) {
            const dialog = confirmTrigger.closest('[data-dialog]');

            if (dialog) {
                dialog.dispatchEvent(new CustomEvent('dialog:confirm', { detail: { dialog } }));
                closeDialog(dialog);
            }
        }
    });

    targets.forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog || event.target.classList.contains('dialog__backdrop')) {
                closeDialog(dialog);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        targets.forEach((dialog) => {
            if (!dialog.hidden) {
                closeDialog(dialog);
            }
        });
    });
}

function mountDataTablePlaygrounds() {
    const targets = document.querySelectorAll('[data-datatable-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(DataTablePlayground).mount(target);
    });
}

function mountButtonPlaygrounds() {
    const targets = document.querySelectorAll('[data-button-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(ButtonPlayground).mount(target);
    });
}

function mountIconPlaygrounds() {
    const targets = document.querySelectorAll('[data-icon-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(IconPlayground).mount(target);
    });
}

function mountModalPlaygrounds() {
    const targets = document.querySelectorAll('[data-modal-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(ModalPlayground).mount(target);
    });
}


function mountToastEditorPlaygrounds() {
    const targets = document.querySelectorAll('[data-toast-editor-playground]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(ToastEditorPlayground).mount(target);
    });
}

function mountToastEditorFields() {
    const targets = document.querySelectorAll('[data-toast-editor-field]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        const source = target.querySelector('[data-toast-editor-source]');

        createApp(ToastEditorField, {
            allowImages: target.dataset.allowImages === 'true',
            height: target.dataset.height || '520px',
            initialValue: source?.value || '',
            inputId: target.dataset.inputId || '',
            inputName: target.dataset.inputName || 'content',
            libraryUrl: target.dataset.libraryUrl || '',
            placeholder: target.dataset.placeholder || '문서 내용을 입력하세요.',
            uploadUrl: target.dataset.uploadUrl || '',
        }).mount(target);
    });
}

function mountToastViewerFields() {
    const targets = document.querySelectorAll('[data-toast-viewer-field]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        const source = target.querySelector('[data-toast-viewer-source]');

        createApp(ToastViewerField, {
            content: source?.value || '',
        }).mount(target);
    });
}

function mountDropdownActionMenus() {
    const targets = document.querySelectorAll('[data-dashboard-action-dropdown], [data-header-action-dropdown]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(DropdownActionMenu, getDropdownPlaygroundProps(target)).mount(target);
    });
}






function mountAlerts() {
    document.querySelectorAll('[data-alert-close]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('.alert')?.remove();
        });
    });
}

function mountConfirmForms() {
    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            confirmDialog({
                title: '확인',
                confirmLabel: '확인',
                danger: true,
                description: form.dataset.confirmMessage || '계속하시겠습니까?',
            }).then((confirmed) => {
                if (confirmed) {
                    form.submit();
                }
            });
        });
    });
}

function mountFlashToasts() {
    const targets = document.querySelectorAll('[data-toast-flash]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        const items = JSON.parse(target.dataset.toastItems || '[]');

        createApp(ToastContainer, {
            autoCloseDelay: 3600,
            items,
        }).mount(target);
    });
}

function mountHeaderNotifications() {
    const targets = document.querySelectorAll('[data-header-notification]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        createApp(HeaderNotificationMount).mount(target);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initializeDashboardNotificationTest();
    initializeOrderAiStructuring();
    mountOrderGroupTypeSync();
    mountOrderLineItemEditors();
    mountFlashToasts();
    mountAlerts();
    mountConfirmForms();
    mountDataTablePlaygrounds();
    mountButtonPlaygrounds();
    mountIconPlaygrounds();
    mountModalPlaygrounds();
    mountToastEditorFields();
    mountToastEditorPlaygrounds();
    mountToastViewerFields();
    mountHeaderNotifications();
    mountDropdownActionMenus();
    mountDropdownPlaygrounds();
    mountDropdownTypesPlaygrounds();
    mountToastPlaygrounds();
    mountLoadingPlaygrounds();
    mountDialogPlaygrounds();
    mountDialogs();
    mountUserDataTable();
    mountUserRowActions();
    mountOrderDataTable();
    mountOrderCardList();
    mountOrderViewToggle();
    mountOrderTabMenu();
    mountViewToggleDemo();
    mountTableActions();
    mountUploadGallery();
});
