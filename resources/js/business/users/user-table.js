import { createApp } from 'vue';

import UserDataTable from './UserDataTable.vue';

export function mountUserDataTable() {
    const targets = document.querySelectorAll('[data-user-datatable]');

    if (!targets.length) {
        return;
    }

    const dataEl = document.querySelector('[data-user-detail-data]');
    let rows = [];

    try {
        rows = JSON.parse(dataEl?.textContent || '[]');
    } catch (error) {
        rows = [];
    }

    targets.forEach((target) => {
        createApp(UserDataTable, { rows }).mount(target);
    });
}
