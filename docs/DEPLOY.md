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
| `FRONTEND_URL` | SPA 주소 | 메인 진입점(/)에서 SPA로 리다이렉트 + CORS 허용 오리진 |
| `APP_URL` | 백엔드 주소 | API 기준 URL |
| `DB_*` | 운영 DB | 마이그레이션 대상 |
| `VITE_API_PROXY_TARGET` | API 주소 | 프론트 프록시 대상(개발용) |
| `VITE_SITE_URL` | 도메인 | 프론트 빌드 시 OG·canonical을 절대 URL로 채움 (미설정 시 상대 경로) |

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

## 7. 고정 도메인 + HTTPS 전환 (도메인 확보 후)

홍보 유입·iOS 홈 화면 설치·웹 푸시·GPS가 **모두 HTTPS 전용**이라 도메인 확보가 첫 수익의 실질 선행 조건이다. 아래 순서대로 진행한다.

### 7-1. Cloudflare Named Tunnel (서버 상시 주소)

1. 도메인을 Cloudflare에 등록(DNS 네임서버 변경) → Zone 활성화
2. `cloudflared tunnel login` → `cloudflared tunnel create nowhere`
3. `cloudflared tunnel route dns nowhere market.<도메인>`
4. `~/.cloudflared/config.yml`에 ingress(호스트 → `http://127.0.0.1:80`) + catch-all 404 작성
5. systemd 등록 후 `cloudflared tunnel run nowhere`
   - 기존 **Quick Tunnel 서비스(`cloudflared-quick`)는 중지**해 두 주소가 겹치지 않게 한다

### 7-2. 서버 환경 변수

```bash
# .env
APP_URL=https://market.<도메인>
FRONTEND_URL=https://market.<도메인>
SESSION_SECURE_COOKIE=true
```

```bash
php artisan config:clear
```

- `trustProxies(at: '*')`가 이미 설정돼 있어 Cloudflare 뒤에서도 scheme이 https로 인식된다 (`bootstrap/app.php`)
- `config/cors.php`의 허용 오리진은 `FRONTEND_URL`을 그대로 쓴다 — SPA와 API가 같은 오리진이면 CORS는 개입하지 않는다

### 7-3. 프론트엔드 재빌드

```powershell
cd frontend
$env:VITE_SITE_URL="https://market.<도메인>"; npm run build
```

- 이 값이 `index.html`의 `%SITE_URL%`(OG·canonical)을 절대 URL로 채운다 — 위챗·카카오 미리보기 카드용
- 미설정이면 종전처럼 상대 경로로 빌드된다

### 7-4. 전환 후 확인 (아이폰 우선)

- [ ] `https://market.<도메인>/up` 200 · 루트 SPA 200 · 인증서 정상
- [ ] 아이폰 Safari 설정 → 화면에 **공유 → 홈 화면에 추가** 순서가 뜬다
- [ ] 홈 화면 아이콘 이름이 `NoWhere`로 짧게 표시된다 (문서 제목으로 잘리지 않음)
- [ ] 홈 화면 앱으로 열면 설정 → 알림에 **토글이** 보인다 (설치 안내가 아니라)
- [ ] 알림 켜기 → 권한 허용 → 실제 푸시 수신 · 알림 탭 시 해당 화면 이동(404 아님)
- [ ] 위챗방 공유 링크(`/s/order/{token}`) 미리보기 카드에 제목·이미지가 뜬다
- [ ] **기존 웹 푸시 구독은 오리진이 바뀌면 무효다** — 기사에게 알림 재설정을 안내하고 `push_subscriptions`의 옛 도메인 행을 정리한다

### 7-5. 알려진 제약

- Quick Tunnel(임시) 주소에서 만든 푸시 구독은 도메인 전환 후 동작하지 않는다 (오리진 종속)
- iOS는 **홈 화면에 추가한 앱에서만** 웹 푸시가 동작한다 — 일반 Safari 탭에서는 알림을 켤 수 없다
- 위챗·카카오 인앱 브라우저에서는 홈 화면 추가가 막힌다 → "Safari로 열기" 안내 필요
