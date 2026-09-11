/**
 * 웹 푸시(Push API) 헬퍼 — 구독 등록/해제와 서버(push_subscriptions) 동기화.
 * VAPID 공개 키는 서버에서 생성한 값 (공개 키라 소스에 노출돼도 안전).
 */
import { apiClient } from '../api/client';

export const VAPID_PUBLIC_KEY =
    'BL3c7DZD44LzBxXLD7YNwS5A_ekjoG1jooO5uy6rPuSYBe0Kp7xJxCBCAJ2ApfTO2vp-gWqBqo9u_3SETf8ZC-A';

// Base64 URL(URL-safe) → Uint8Array (pushManager.subscribe applicationServerKey용)
export function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const base64Url = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64Url);
    const arr = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i++) {
        arr[i] = raw.charCodeAt(i);
    }

    return arr;
}

// Uint8Array(키) → base64 문자열
function arrayToBase64(array) {
    let binary = '';

    for (let i = 0; i < array.length; i++) {
        binary += String.fromCharCode(array[i]);
    }

    return btoa(binary);
}

// 푸시 지원 여부 — Service Worker/Push 는 보안 컨텍스트(HTTPS·localhost)에서만 동작한다.
// HTTP 로 접속하면 'serviceWorker' in navigator 는 true 지만 등록이 실패해서
// navigator.serviceWorker.ready 가 영원히 대기하므로, 지원하지 않는 것으로 본다.
export function isPushSupported() {
    return (
        window.isSecureContext === true &&
        'serviceWorker' in navigator &&
        'PushManager' in window
    );
}

// 이미 활성화된 구독이 있는지
export async function getActivePushSubscription() {
    if (!isPushSupported()) return null;

    const reg = await navigator.serviceWorker.ready;

    return reg.pushManager.getSubscription();
}

// 브라우저 구독 생성 + 서버에 저장
export async function subscribeToPush() {
    if (!isPushSupported()) {
        throw new Error('이 브라우저는 푸시 알림을 지원하지 않습니다.');
    }

    const reg = await navigator.serviceWorker.ready;
    const existing = await reg.pushManager.getSubscription();

    const sub = existing ?? await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
    });

    await apiClient.post('/push-subscriptions', {
        endpoint: sub.endpoint,
        public_key: sub.getKey('p256dh') ? arrayToBase64(sub.getKey('p256dh')) : null,
        auth_token: sub.getKey('auth') ? arrayToBase64(sub.getKey('auth')) : null,
    });

    return sub;
}

// 서버 구독 제거 + 브라우저 구독 해제
export async function unsubscribeFromPush() {
    if (!isPushSupported()) return false;

    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();

    if (!sub) return false;

    // 서버 엔드포인트 제거 — 실패해도 로컬 구독 해제는 진행
    await apiClient.delete('/push-subscriptions', { data: { endpoint: sub.endpoint } }).catch(() => {});

    await sub.unsubscribe();

    return true;
}
