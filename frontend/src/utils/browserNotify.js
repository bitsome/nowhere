/**
 * 브라우저 알림 (Notification API) 헬퍼.
 * - 권한 요청
 * - 페이지가 숨겨져 있을 때만 알림 표시 (보이는 동안은 앱 토스트로 충분)
 */

export function requestNotifyPermission() {
    if (!('Notification' in window)) {
        return Promise.resolve(false);
    }
    if (Notification.permission === 'granted') {
        return Promise.resolve(true);
    }
    if (Notification.permission === 'denied') {
        return Promise.resolve(false);
    }

    return Notification.requestPermission().then((permission) => permission === 'granted');
}

// 프로필 설정: 브라우저 알림 켜기/끄기 (기본 켜짐)
export function isBrowserNotifyEnabled() {
    return localStorage.getItem('notify_enabled') !== '0';
}

export function setBrowserNotifyEnabled(enabled) {
    localStorage.setItem('notify_enabled', enabled ? '1' : '0');
}

export function showBrowserNotification(title, options = {}) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }
    // 사용자가 프로필에서 알림을 껐으면 표시하지 않는다
    if (!isBrowserNotifyEnabled()) {
        return;
    }
    // 탭이 보이는 동안에는 앱 토스트가 충분하므로, 숨김 상태에서만 브라우저 알림
    if (document.visibilityState !== 'hidden') {
        return;
    }

    try {
        new Notification(title, {
            body: options.body ?? '',
            tag: options.tag ?? 'nowhere',
            icon: options.icon ?? '',
        });
    } catch {
        /* 알림 표시 실패는 무시 */
    }
}
