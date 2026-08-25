/* NoWhere PWA 서비스 워커 — 설치 가능 + 방문한 앱 셸 오프라인 캐시.
 * 주의: /api(동적 데이터)와 외부 오리진은 절대 캐시하지 않는다.
 * 내비게이션은 네트워크 우선 — 배포(새 index.html/해시 assets)가 캐시에 가려지지 않는다. */
const CACHE = 'nowhere-v3';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // API·외부 요청은 항상 네트워크로 보낸다 (실시간 데이터는 캐시하지 않음)
    if (url.origin !== self.location.origin || url.pathname.startsWith('/api')) {
        return;
    }

    // 페이지 이동(앱 셸) — 네트워크 우선. 성공 시 캐시를 갱신해 항상 최신 버전을 보여준다.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                    }

                    return response;
                })
                .catch(() => caches.match(request).then((shell) => shell || Response.error())),
        );
        return;
    }

    // 정적 자산(해시 파일명) — 캐시 우선, 미스 시 네트워크 후 저장
    event.respondWith(
        caches.match(request).then((hit) => {
            if (hit) {
                return hit;
            }

            return fetch(request).then((response) => {
                if (response.ok && url.pathname.includes('/assets/')) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            });
        }),
    );
});

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
            icon: data.icon || '/spa/icons/icon.svg',
            badge: data.badge || '/spa/icons/icon.svg',
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
