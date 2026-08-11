import { createApp } from 'vue';

import DropdownTableActions from '../shared/components/Dropdown/DropdownTableActions.vue';
import { confirmDelete } from '../shared/components/Dialog/confirmDialog.js';

export function submitDeleteForm(url, csrfToken) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = csrfToken;

    const methodInput = document.createElement('input');
    methodInput.type = 'hidden';
    methodInput.name = '_method';
    methodInput.value = 'DELETE';

    form.appendChild(tokenInput);
    form.appendChild(methodInput);
    document.body.appendChild(form);
    form.submit();
}

export function resolveDeleteActions(items, csrfToken) {
    return items.map((item) => {
        if (item.type === 'delete' && item.url) {
            return {
                ...item,
                action: async () => {
                    const confirmed = await confirmDelete(item.confirm || '삭제하시겠습니까?');

                    if (confirmed) {
                        submitDeleteForm(item.url, csrfToken);
                    }
                },
            };
        }

        return item;
    });
}
export function mountTableActions() {
    const targets = document.querySelectorAll('[data-table-actions]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        let items = [];

        try {
            items = JSON.parse(target.dataset.items || '[]');
        } catch (error) {
            items = [];
        }

        createApp(DropdownTableActions, {
            items: resolveDeleteActions(items, target.dataset.csrfToken || ''),
            triggerLabel: target.dataset.triggerLabel || '액션',
        }).mount(target);
    });
}
