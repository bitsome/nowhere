import { afterEach, describe, expect, it } from 'vitest';
import { isBrowserNotifyBlocked } from './browserNotify';

/**
 * 차단(denied) 뒤에는 권한 창이 다시 뜨지 않는다.
 * 켜기를 반복하면 "권한이 거부되었습니다"만 돌아오므로, 화면이 브라우저 설정을 풀도록
 * 안내하려면 차단 상태를 먼저 알아볼 수 있어야 한다.
 */
const setPermission = (permission) => {
    Object.defineProperty(window, 'Notification', {
        value: { permission },
        configurable: true,
        writable: true,
    });
};

const removeNotification = () => {
    delete window.Notification;
};

afterEach(removeNotification);

describe('isBrowserNotifyBlocked', () => {
    it('차단(denied) 상태를 알아본다', () => {
        setPermission('denied');

        expect(isBrowserNotifyBlocked()).toBe(true);
    });

    it('허용·미결정은 차단이 아니다', () => {
        setPermission('granted');
        expect(isBrowserNotifyBlocked()).toBe(false);

        setPermission('default');
        expect(isBrowserNotifyBlocked()).toBe(false);
    });

    it('Notification 자체가 없는 브라우저는 차단이 아니다', () => {
        removeNotification();

        expect(isBrowserNotifyBlocked()).toBe(false);
    });
});
