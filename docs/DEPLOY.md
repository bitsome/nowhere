# DEPLOY — 배포 절차

NoWhere는 **Laravel API(8080) + 독립 SPA 프론트엔드(dist)** 두 부분으로 구성된다.

```
브라우저 ──▶ SPA (dist, 정적) ──/api──▶ Laravel API (8080)
                  │                       ├─ 알림 (notifications)
                  │                       ├─ 채팅 (conversations/messages)
                  │                       └─ SSE 푸시 (/api/events)
```

## 1. 백엔드 (Laravel)

```bash
# 의존성
composer install --no-dev --optimize-autoloader

# 환경 변수
cp .env.example .env
php artisan key:generate

# .env 필수 설정
#   APP_URL, DB_*, FRONTEND_URL(SPA 주소), SESSION_DRIVER, CACHE_STORE

# DB 마이그레이션 + 데모 데이터
php artisan migrate --force
php artisan db:seed --force        # 데모: 운행/알림/채팅

# 캐시 정리
php artisan config:cache
php artisan route:cache
```

## 2. 프론트엔드 (SPA)

```bash
cd frontend
npm ci
npm run build                     # dist/ 생성
```

## 3. 웹 서버 연결 (nginx 예시)

```nginx
# SPA 정적 서빙 + /api 프록시
server {
    listen 80;
    server_name market.example.com;
    root /var/www/frontend/dist;

    location / {
        try_files $uri $uri/ /index.html;   # SPA 라우팅
    }

    location /api {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_buffering off;                 # SSE 스트리밍용
        proxy_read_timeout 3600s;
    }
}
```

- Laravel은 `php artisan serve` 대신 **nginx + php-fpm** (또는 Octane)로 서빙 권장.
- **SSE**(`/api/events`)를 사용하므로 프록시에서 `proxy_buffering off`가 필요하다.

## 4. 환경 변수 요약

| 변수 | 값 | 용도 |
|---|---|---|
| `FRONTEND_URL` | SPA 주소 | 메인 진입점(/)에서 SPA로 리다이렉트 |
| `APP_URL` | 백엔드 주소 | API 기준 URL |
| `DB_*` | 운영 DB | 마이그레이션 대상 |
| `VITE_API_PROXY_TARGET` | API 주소 | 프론트 프록시 대상(개발용) |

## 5. 검증

```bash
curl -s https://market.example.com/up        # 백엔드 헬스체크 (200)
curl -s https://market.example.com/          # SPA index.html (200)
```

- 로그인: `test@example.com / password` (로컬 시드 기준 데모 계정. 서버 배포본은 전 계정 비밀번호 `123456`로 통일)
- 알림/채팅 실시간 확인: 다른 계정에서 메시지/알림 전송 → 수신 화면 확인

## 6. 외부 접속 (임시 Quick Tunnel)

### 개발 중 (로컬)
로컬 vite dev 서버를 그대로 노출한다. `/api`는 vite proxy가 서버 API(114.132.240.52)로 연결되고 DB는 서버 MySQL을 사용한다.

```powershell
cloudflared.exe tunnel --url http://localhost:5174 --no-autoupdate
```

- 실행 시 출력된 `https://*.trycloudflare.com` URL을 안내하고 `/up`·루트 200으로 검증한다.
- Quick Tunnel URL은 실행마다 새로 발급되며 재사용할 수 없다.

### 상용 서버 (임시 검증)
서버에 systemd 서비스 `cloudflared-quick`가 등록되어 있어 `http://127.0.0.1:80`을 노출한다. 재시작 시 URL이 바뀐다. 상세 절차는 `.cursor/skills/nowhere-deployment/SKILL.md` 섹션 5를 참조한다.

- **실시간 구조 (기본: SSE once 모드)**:
  - 프론트는 `/api/events?once=1`로 **상태 스냅샷을 받고 즉시 연결을 닫은 뒤 5초 후 재연결**한다.
  - `once=1`은 단일 워커(`php artisan serve`)에서 SSE가 워커를 점유해 다른 요청을 막는 문제를 피하기 위한 기본값이다 (Windows의 PHP 내장 서버는 멀티 워커 미지원).
  - 실시간 알림 배지·채팅 미확인은 이 방식으로 **최대 5초 내 갱신**된다.
- **실서버 (nginx + php-fpm 등 다중 워커)**: 더 즉각적인 실시간이 필요하면 `frontend/src/utils/eventStream.js`의 `&once=1`을 제거해 20초 스트림 유지 모드로 전환한다.
  - 스트림 유지 모드에서는 nginx `proxy_buffering off`, `proxy_read_timeout`이 반드시 설정돼 있어야 한다.
- 고정 도메인이 필요하면 Cloudflare Named Tunnel + 사용자 소유 도메인을 제안한다.
