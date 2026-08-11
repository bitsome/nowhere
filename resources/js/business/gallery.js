import { createApp } from 'vue';

import GalleryTable from '../shared/components/DataTable/GalleryTable.vue';
import { resolveDeleteActions } from './row-actions.js';

export function mountUploadGallery() {
    const targets = document.querySelectorAll('[data-upload-gallery]');

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

        const csrfToken = target.dataset.csrfToken || '';

        const galleryItems = items.map((item) => ({
            ...item,
            actions: resolveDeleteActions(item.actions || [], csrfToken),
        }));

        createApp(GalleryTable, {
            items: galleryItems,
            emptyTitle: target.dataset.emptyTitle || '업로드된 항목이 없습니다.',
            emptyDescription: target.dataset.emptyDescription || '',
        }).mount(target);
    });
}
