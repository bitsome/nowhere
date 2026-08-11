import { createApp } from 'vue';

import OrderCardList from '../orders/OrderCardList.vue';
import OrderDataTable from '../orders/OrderDataTable.vue';
import TabMenu from '../../shared/components/Tab/TabMenu.vue';
import ViewToggle from '../../shared/components/Tab/ViewToggle.vue';

export function mountOrderDataTable() {
    const targets = document.querySelectorAll('[data-order-datatable]');

    if (!targets.length) {
        return;
    }

    const dataEl = document.querySelector('[data-order-rows]');
    let rows = [];

    try {
        rows = JSON.parse(dataEl?.textContent || '[]');
    } catch (error) {
        rows = [];
    }

    targets.forEach((target) => {
        createApp(OrderDataTable, { rows }).mount(target);
    });
}
export function mountOrderCardList() {
    const targets = document.querySelectorAll('[data-order-card-list]');

    if (!targets.length) {
        return;
    }

    const dataEl = document.querySelector('[data-order-rows]');
    let rows = [];

    try {
        rows = JSON.parse(dataEl?.textContent || '[]');
    } catch (error) {
        rows = [];
    }

    targets.forEach((target) => {
        createApp(OrderCardList, { rows, variant: target.dataset.orderCardVariant || 'grid' }).mount(target);
    });
}

/**
 * 그리드(카드) / 리스트(DataTable) 보기 전환.
 * 공용 ViewToggle 컴포넌트를 사용한다.
 */
export function mountOrderViewToggle() {
    const targets = document.querySelectorAll('[data-order-view-toggle]');

    if (!targets.length) {
        return;
    }

    const gridView = document.querySelector('[data-order-card-list]');
    const listView = document.querySelector('[data-order-datatable]');

    const setView = (view) => {
        if (!gridView || !listView) {
            return;
        }

        const isGrid = view === 'grid';

        gridView.classList.toggle('hidden', !isGrid);
        listView.classList.toggle('hidden', isGrid);
    };

    const prefersTable = window.matchMedia('(min-width: 768px)').matches;

    targets.forEach((target) => {
        createApp(ViewToggle, {
            initialView: prefersTable ? 'list' : 'grid',
            changeHandler: setView,
        }).mount(target);
    });

    setView(prefersTable ? 'list' : 'grid');
}

/**
 * 공용 TabMenu 컴포넌트로 탭 메뉴를 렌더링한다.
 */
export function mountOrderTabMenu() {
    const targets = document.querySelectorAll('[data-order-tabs]');

    if (!targets.length) {
        return;
    }

    targets.forEach((target) => {
        let items = [];

        try {
            items = JSON.parse(target.dataset.tabs || '[]');
        } catch (error) {
            items = [];
        }

        createApp(TabMenu, { items }).mount(target);
    });
}

/**
 * 데모용 ViewToggle — 그룹 내 카드/리스트 박스를 전환한다.
 */
export function mountViewToggleDemo() {
    const groups = document.querySelectorAll('[data-view-toggle-demo-group]');

    groups.forEach((group) => {
        const target = group.querySelector('[data-view-toggle-demo]');
        const cardView = group.querySelector('[data-card-view]');
        const listView = group.querySelector('[data-list-view]');

        if (!target) {
            return;
        }

        createApp(ViewToggle, {
            initialView: 'grid',
            changeHandler: (view) => {
                const isGrid = view === 'grid';

                cardView?.classList.toggle('hidden', !isGrid);
                listView?.classList.toggle('hidden', isGrid);
            },
        }).mount(target);
    });
}
