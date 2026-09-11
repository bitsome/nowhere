import { afterEach, describe, expect, it } from 'vitest';
import { getActivePushSubscription, isPushSupported, subscribeToPush } from './push';

/**
 * 푸시는 보안 컨텍스트(HTTPS·localhost)에서만 동작한다.
 * 정적 IP(http://)로 접속한 기사가 알림을 켜려 할 때
 * '권한 거부'로 잘못 안내하거나 영원히 대기하지 않도록 하는 회귀 방지 테스트.
 */
const setSecureContext = (secure) => {
    Object.defineProperty(window, 'isSecureContext', {
        value: secure,
        configurable: true,
        writable: true,
    });
};

const setServiceWorker = (value) => {
    Object.defineProperty(navigator, 'serviceWorker', {
        value,
        configurable: true,
        writable: true,
    });
};

const setPushManager = (present) => {
    if (present) {
        window.PushManager = function PushManager() {};
    } else {
        delete window.PushManager;
    }
};

afterEach(() => {
    setServiceWorker(undefined);
    setPushManager(false);
    setSecureContext(true);
});

describe('isPushSupported — 보안 컨텍스트를 요구한다', () => {
    it('HTTP(비보안 컨텍스트)에서는 지원하지 않는다고 본다', () => {
        setSecureContext(false);
        setServiceWorker({ ready: new Promise(() => {}) });
        setPushManager(true);

        expect(isPushSupported()).toBe(false);
    });

    it('HTTPS여도 PushManager가 없으면 지원하지 않는다', () => {
        setSecureContext(true);
        setServiceWorker({ ready: new Promise(() => {}) });
        setPushManager(false);

        expect(isPushSupported()).toBe(false);
    });

    it('HTTPS + serviceWorker + PushManager면 지원한다', () => {
        setSecureContext(true);
        setServiceWorker({ ready: new Promise(() => {}) });
        setPushManager(true);

        expect(isPushSupported()).toBe(true);
    });
});

describe('비보안 컨텍스트에서 푸시 API는 대기하지 않고 즉시 끝난다', () => {
    it('getActivePushSubscription은 serviceWorker.ready를 기다리지 않고 null을 반환한다', async () => {
        setSecureContext(false);
        // ready가 영원히 pending — 이걸 기다리면 알림 설정 화면이 멈춘다
        setServiceWorker({ ready: new Promise(() => {}) });
        setPushManager(true);

        await expect(getActivePushSubscription()).resolves.toBeNull();
    });

    it('subscribeToPush는 구독을 시도하지 않고 바로 실패한다', async () => {
        setSecureContext(false);
        setServiceWorker({ ready: new Promise(() => {}) });
        setPushManager(true);

        await expect(subscribeToPush()).rejects.toThrow();
    });
});
