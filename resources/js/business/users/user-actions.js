
export function mountUserRowActions() {
    const detailModal = document.querySelector('[data-user-detail-modal]');
    const detailDataEl = document.querySelector('[data-user-detail-data]');

    if (!detailModal || !detailDataEl) {
        return;
    }

    let users = [];

    try {
        users = JSON.parse(detailDataEl.textContent || '[]');
    } catch (error) {
        users = [];
    }

    const roleActionUrl = detailModal.querySelector('[data-user-role-action]')?.value || '';
    const statusActionUrl = detailModal.querySelector('[data-user-status-action]')?.value || '';

    const openDetailModal = (userId) => {
        const user = users.find((item) => Number(item.id) === Number(userId));

        if (!user) {
            return;
        }

        Object.entries(user).forEach(([key, value]) => {
            const target = detailModal.querySelector(`[data-user-detail="${key}"]`);

            if (target) {
                target.textContent = value ?? '-';
            }
        });

        const initial = detailModal.querySelector('[data-user-detail-initial]');

        if (initial) {
            initial.textContent = user.name ? String(user.name).trim().charAt(0).toUpperCase() : '?';
        }

        const roleSelect = detailModal.querySelector('[data-user-role-select]');
        const statusSelect = detailModal.querySelector('[data-user-status-select]');

        if (roleSelect) {
            roleSelect.value = user.role;
        }

        if (statusSelect) {
            statusSelect.value = user.status;
        }

        const roleForm = detailModal.querySelector('[data-user-role-form]');
        const statusForm = detailModal.querySelector('[data-user-status-form]');

        if (roleForm) {
            roleForm.action = roleActionUrl.replace(':id', String(user.id));
        }

        if (statusForm) {
            statusForm.action = statusActionUrl.replace(':id', String(user.id));
        }

        detailModal.querySelectorAll('[data-user-manage-only]').forEach((element) => {
            element.hidden = !user.canManage;
        });

        const noManage = detailModal.querySelector('[data-user-detail-no-manage]');

        if (noManage) {
            noManage.hidden = !!user.canManage;
        }

        const permissionsLink = detailModal.querySelector('[data-user-detail-permissions]');

        if (permissionsLink) {
            permissionsLink.href = user.permissionsUrl;
        }

        detailModal.hidden = false;
        document.body.classList.add('overflow-hidden');
    };

    window.__nowhereOpenUserDetail = openDetailModal;

    const closeDetailModal = () => {
        detailModal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    };

    document.addEventListener('click', (event) => {
        const detailTrigger = event.target.closest('[data-user-detail-trigger]');

        if (detailTrigger) {
            event.preventDefault();
            openDetailModal(detailTrigger.dataset.userId);
        }
    });

    detailModal.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-close]') || event.target.classList.contains('modal__backdrop')) {
            closeDetailModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !detailModal.hidden) {
            closeDetailModal();
        }
    });
}
