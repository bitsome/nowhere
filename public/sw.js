/* NoWhere PWA 서비스 워커 — 개발 단계에서는 캐싱을 끈다.
 * fetch를 가로채지 않으므로 모든 요청이 네트워크로 가며,
 * 항상 최신 빌드·데이터를 사용한다 (캐시로 인한 구버전 노출 방지).
 * 설치·푸시 알림 수신만 유지하고, 상용 전환 시 캐시 전략을 다시 도입한다. */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // 개발 중 캐시 미사용 — 이전 버전이 남긴 캐시는 모두 정리한다
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// ── fetch 핸들러 없음: 모든 요청은 브라우저 기본(네트워크) 동작으로 처리된다 ──

// ── 웹 푸시 알림 — 앱이 닫혀 있어도 새 알림을 시스템 알림으로 보여준다 ──
self.addEventListener('push', (event) => {
    let data = { title: 'NoWhere', message: '새 알림이 도착했습니다.', url: '/spa/notifications' };

    try {
        const parsed = event.data ? event.data.json() : null;

        if (parsed) {
            data = { ...data, ...parsed };
        }
    } catch {
        /* payload가 JSON이 아니면 기본 문구 사용 */
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.message,
            icon: data.icon || '/spa/icons/icon-192.png',
            badge: data.badge || '/spa/icons/icon-192.png',
            tag: data.tag || 'nowhere-push',
            data: { url: data.url },
        }),
    );
});

// 알림 클릭 — 해당 화면으로 이동 (이미 열린 창을 포커스하고 이동)
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const raw = event.notification.data?.url || '/spa/';
    const target = raw.startsWith('/spa') || raw.startsWith('http') ? raw : `/spa${raw}`;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if ('focus' in client) {
                    return client
                        .navigate(target)
                        .then(() => client.focus())
                        .catch(() => clients.openWindow(target));
                }
            }

            return clients.openWindow(target);
        }),
    );
});
