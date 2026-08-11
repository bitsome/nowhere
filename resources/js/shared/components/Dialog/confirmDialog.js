import { createApp, h, reactive } from 'vue';
import BaseDialog from './BaseDialog.vue';

/**
 * 공용 확인 다이얼로그.
 *
 * window.confirm 대신 사용한다. Promise<boolean>을 반환하며
 * 확인 시 true, 취소/닫기 시 false로 resolve 된다.
 *
 * @param {object} options
 * @param {string} [options.title='확인']
 * @param {string} [options.description='']
 * @param {string} [options.confirmLabel='확인']
 * @param {string} [options.cancelLabel='취소']
 * @param {boolean} [options.danger=false] 확인 버튼을 위험 스타일로 표시
 * @returns {Promise<boolean>}
 */
export function confirmDialog(options = {}) {
    const state = reactive({
        cancelLabel: '취소',
        confirmLabel: '확인',
        danger: false,
        description: '',
        title: '확인',
        ...options,
    });

    const container = document.createElement('div');
    document.body.appendChild(container);

    const close = (result) => {
        app.unmount();
        container.remove();

        return result;
    };

    const app = createApp({
        render() {
            return h(BaseDialog, {
                cancelLabel: state.cancelLabel,
                confirmLabel: state.confirmLabel,
                description: state.description,
                open: true,
                size: 'sm',
                title: state.title,
                variant: state.danger ? 'danger' : 'confirm',
                onClose: () => resolve(false),
                onConfirm: () => resolve(true),
            });
        },
    });

    let resolve;
    const promise = new Promise((r) => {
        resolve = r;
    });

    promise.then((result) => close(result));

    app.mount(container);

    return promise;
}

/**
 * 삭제 확인 다이얼로그 단축 함수.
 *
 * @param {string} description 삭제 대상 설명 메시지
 * @returns {Promise<boolean>}
 */
export function confirmDelete(description) {
    return confirmDialog({
        title: '삭제 확인',
        confirmLabel: '삭제',
        danger: true,
        description,
    });
}
