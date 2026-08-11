# 독립 프론트엔드 · 백엔드 API 분리 설계 (API SPLIT)

## 1. 목적

비즈니스 모델의 독립 프론트엔드를 만들기 위해, 현재 모놀리식(Blade + Vue 마운트) 구조를 **백엔드 API + 완전 분리 SPA** 구조로 전환한다.

- **백엔드**: Laravel이 JSON API만 제공 (Laravel Sanctum 토큰 인증)
- **프론트엔드**: **모든 화면이 독립 SPA로 분리** — 운영 UI는 SPA 단일 (Vue 3 SPA가 API만 호출)
- 기존 Blade 앱은 **테스트·검증 도구로만 유지**한다. (운영 프론트엔드가 아님 — SPA로 전부 대체)

## 2. 아키텍처

```
┌──────────────────────────┐          ┌──────────────────────────┐
│  Frontend (Vue 3 SPA)    │  HTTPS   │  Backend (Laravel API)   │
│  frontend/               │ ───────▶ │  routes/api.php          │
│  ├─ Vite + Vue 3         │  JSON    │  ├─ Api\Controllers      │
│  ├─ Vue Router + Pinia   │  Bearer  │  ├─ Sanctum 토큰 인증    │
│  └─ src/api/ (axios)     │  토큰    │  ├─ 기존 Model/Policy    │
└──────────────────────────┘          │  └─ 기존 Services        │
                                      └──────────────────────────┘
```

### 저장소 구조 (현재 저장소 기준)

```
nowhere/
├── app/                            # ← 백엔드 (Laravel)
│   └── Http/Controllers/Api/       #    API 전용 컨트롤러 (신규)
│       ├── AuthController.php
│       └── OrderController.php
├── routes/api.php                  #    API 라우트 (신규)
├── frontend/                       # ← 독립 프론트엔드 (완전 분리)
│   ├── package.json                #    별도 의존성/빌드
│   ├── vite.config.js              #    /api 프록시 (개발)
│   └── src/
│       ├── router/ stores/ api/ views/ components/
└── resources/views/ ...            # 기존 Blade 앱 (병행 유지, 이후 정리)
```

## 3. 인증 설계 — Laravel Sanctum 토큰

완전 분리 프론트엔드는 도메인/오리진이 다르므로 **쿠키 세션 대신 토큰**을 쓴다.

| 항목 | 결정 |
|---|---|
| 패키지 | `laravel/sanctum` (`php artisan install:api`) |
| 토큰 방식 | Personal Access Token (`$user->createToken('frontend')->plainTextToken`) |
| 전달 | `Authorization: Bearer <token>` |
| 폐기 | 로그아웃 시 `$request->user()->currentAccessToken()->delete()` |
| 보안 | 토큰은 응답 시 1회만 노출, 클라이언트는 안전 저장(로컬스토리지 미사용 권장) |
| 401 처리 | 프론트 인터셉터에서 만료 감지 → 로그인 리다이렉트 |

### 인증 엔드포인트

| Method | URI | 설명 | 응답 |
|---|---|---|---|
| POST | `/api/auth/login` | 이메일·비밀번호 로그인 | `{ token, user }` |
| POST | `/api/auth/logout` | 토큰 폐기 | `204` |
| GET | `/api/auth/me` | 현재 사용자 + 권한 | `{ data: user }` |

### CORS

`config/cors.php` — 프론트엔드 오리진 허용:

```php
'paths' => ['api/*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```

## 4. API 설계 — Phase 1 (오더 중심)

기존 비즈니스 흐름(마켓 → 가져오기 → 상태 전이 → 내가 받은 오더)을 그대로 API로 이전한다.

### 오더 목록 — `GET /api/orders`

| 파라미터 | 값 | 설명 |
|---|---|---|
| `scope` | `market`(기본) / `mine` | market: 가져올 수 있는 남의 오더, mine: 내가 받은 오더 |
| `tab` | `진행중` / `완료` / `취소` | scope=mine일 때 상태 그룹 필터 |
| `search` | 문자열 | 오더번호·고객명·노선 LIKE |
| `page` | 정수 | 페이지네이션 (기본 20) |

- `market` 필터: `status IN (published, trading, acceptance_pending)` + `user_id != me`
- `mine` 필터: `user_id = me` + `claimed_at IS NOT NULL`
- 응답 행은 기존 `OrderListRowBuilder` 계약 재사용 (카드/테이블 공용)

### 오더 상세 — `GET /api/orders/{order}`

- `lineItems` 포함, 셋트면 그룹 전체 일정 포함 (기존 show 로직 재사용)

### 오더 등록/수정

| Method | URI | 설명 |
|---|---|---|
| POST | `/api/orders` | 직접 등록 (검증: 기존 OrderRequest 재사용) |
| PATCH | `/api/orders/{order}` | 수정 |
| POST | `/api/orders/structure` | AI 구조화 (기존 OrderSummaryAiStructurer 재사용) |

### 가져오기/상태 전이

| Method | URI | 설명 |
|---|---|---|
| POST | `/api/orders/{order}/claim` | 내 오더로 가져오기 (user_id=me, status=accepted, claimed_at=now) |
| POST | `/api/orders/{order}/status` | 상태 전이 `{ status }` (STATUS_FLOW + OrderPolicy) |

### 옵션 — `GET /api/options/orders`

- `statusOptions`, `serviceOptions`, `channelOptions`, `companyOptions` 등 프론트 드롭다운용 옵션 모음

## 5. 응답 · 에러 표준

기존 `resources/js/shared/api`의 규약을 API에도 동일 적용한다.

### 성공

```json
{
  "data": { ... },
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 30, "last_page": 2 }
  }
}
```

### 에러

```json
{ "message": "검증에 실패했습니다.", "errors": { "email": ["이메일 형식이 아닙니다."] } }
```

| 상태 | 의미 | 프론트 처리 |
|---|---|---|
| 401 | 인증 만료/실패 | 로그인 리다이렉트 |
| 403 | 권한 없음 | 안내 메시지 |
| 422 | 검증 실패 | 폼 필드 오류 표시 |
| 404 | 리소스 없음 | 안내 메시지 |
| 500 | 서버 오류 | 일반 오류 안내 |

## 6. 권한 검증

- 기존 `App\Policies`(OrderPolicy 등)와 라우트 `can:` 미들웨어를 **그대로 재사용**한다.
- API 라우트에 동일하게 적용:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/orders/{order}/claim', [OrderController::class, 'claim'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/{order}/status', [OrderController::class, 'transition'])->middleware('can:transition,order');
});
```

- Spatie 권한 전환(ARCHITECTURE.md 잔여 페이즈)은 API 분리와 독립적으로 진행한다. 전환 후 Policy 내부만 교체된다.

## 7. 프론트엔드 설계 (frontend/)

### 스택

- Vue 3 Composition API + `script setup` (기존 원칙 유지)
- Vite, Vue Router, Pinia
- axios (`src/api/client.js`)
- CSS Framework 사용 안 함 — 기존 plain CSS 기준 유지

### 프로젝트 구조

```
frontend/src/
├── main.js                    # 앱 부트스트랩
├── App.vue
├── api/
│   ├── client.js              # axios 인스턴스 + Bearer 헤더 + 401 인터셉터
│   ├── auth.js                # login/logout/me
│   └── orders.js              # 목록/상세/claim/status/options
├── stores/
│   ├── auth.js                # token, user, isAuthenticated
│   └── orders.js              # 목록 상태, 탭, 검색, 페이지네이션
├── router/
│   └── index.js               # 라우트 + 인증 가드
├── views/
│   ├── LoginView.vue
│   ├── MarketView.vue         # 가져올 수 있는 오더 (마켓)
│   ├── ReceivedOrdersView.vue # 내가 받은 오더 (진행중/완료/취소 탭)
│   ├── OrderDetailView.vue    # 상세 + 가져오기 + 상태 전이
│   └── OrderCreateView.vue    # 등록 (AI 구조화 포함)
└── components/
    └── orders/                # OrderCard / SetCard / TabMenu / ViewToggle ...
```

### 화면 흐름

```
로그인 → 마켓(가져올 수 있는 오더)
       → 상세 → "내 오더로 가져오기" → 수락 → [상태 전이: 운행중→완료→정산]
       → 내가 받은 오더 (진행중/완료/취소 탭 + 검색)
       → 오더 등록
```

### 공용 컴포넌트 이전

기존 `resources/js/shared/components`의 **TabMenu, ViewToggle, DataTable, BaseIcon** 등을 frontend로 이전(복사)하여 재사용한다. Blade 의존성이 있는 것은 순수 Vue로 재구성한다.

## 8. 개발 환경

### 백엔드

- `php artisan install:api` → `routes/api.php` + Sanctum
- API 컨트롤러: `app/Http/Controllers/Api/` (기존 웹 컨트롤러와 분리)
- Laravel 11+ 기본 `/api` 라우트 접두사 사용

### 프론트엔드 (개발 중)

`vite.config.js` 프록시로 API 호출을 백엔드로 전달:

```js
export default defineConfig({
  server: {
    proxy: { '/api': 'http://localhost:8000' },
  },
});
```

## 9. 배포 · 운영

| 영역 | 운영 방식 |
|---|---|
| 백엔드 | 기존 Laravel 서버 (배포 절차는 docs/DEPLOY.md 유지) |
| 프론트엔드 | `npm run build` → 정적 파일(nginx/S3 등) 배포, 별도 도메인 |
| CORS | 운영 오리진을 `config/cors.php`에 추가 |
| 환경 변수 | `FRONTEND_URL` (프론트 오리진), 프론트측 `VITE_API_BASE_URL` |

## 10. 단계별 마일스톤

| 단계 | 백엔드 | 프론트엔드 | 완료 기준 |
|---|---|---|---|
| **M1** | Sanctum 설치, `/api/auth/*`, `/api/orders`(목록/상세/claim/status), 정책 적용, API 테스트 | — | `php artisan test` 통과 (API Feature Test) |
| **M2** | — | SPA 골격 (Vite+Router+Pinia+client), 로그인, 마켓 목록, 상세+가져오기, 상태 전이 | 마켓→가져오기→상태전이 E2E 동작 |
| **M3** | `/api/orders` 페이지네이션·검색·mine 스코프 정리 | 내가 받은 오더 탭(진행중/완료/취소) + 검색 | 탭/검색 동작 |
| **M4** | `/api/orders` 등록/수정 + structure API | 등록/수정 폼 (AI 구조화 포함) | 등록→마켓 반영 |
| **M5** | — | 기능 이전 완료 → 운영 화면 라우트 폐쇄, **SPA만 서비스** (Blade는 테스트 도구로 유지) | 운영 전환 |

**현재 단계**: M1 (백엔드 API) — Sanctum 설치 + 인증/오더 API 구현.

## 11. 기존 앱 전환 전략 (독립형)

독립형(SPA)에서는 **모든 프론트엔드가 분리**된다. 운영 UI는 SPA 하나만 존재한다.

- **운영 화면 = SPA 전부** — 마켓/오더/관리자 화면 모두 SPA로 이전한다. Blade 화면은 운영에 사용하지 않는다.
- 기존 Blade 앱은 **테스트·검증 도구로만 남긴다.**
  - SPA 화면이 API 데이터를 기대한 대로 보여주는지 대조·확인하는 용도
  - 기능 이전이 끝나면 운영 경로(`routes/web.php` 화면 라우트)를 닫아 SPA만 서비스한다.
  - Blade 코드 자체는 당장 삭제하지 않되, **운영 진입점이 아니라는 점을 유지**한다.
- API는 새 컨트롤러 계층(`app/Http/Controllers/Api/`)에 추가하므로 기존 웹 컨트롤러와 충돌하지 않는다.
- 공용 로직(OrderListRowBuilder, Policies, Services, STATUS_FLOW)은 양쪽이 동일하게 사용하므로 중복하지 않는다.

## 12. 결정 사항 · 리스크

| 항목 | 결정/리스크 |
|---|---|
| 인증 | Sanctum 토큰 — 분리 FE에 적합, DB 조회 비용은 현재 규모에서 문제 없음 |
| 프론트 상태 | 로컬스토리지에 토큰 저장은 XSS 리스크 — 메모리(Pinia) + 필요시 쿠키(httponly) 재검토 |
| AI 구조화 | 기존 `OrderSummaryAiStructurer` 재사용 — 프론트는 요약 텍스트만 전달 |
| 파일 업로드 | Phase 1 범위 제외, 이후 공통 파일 모듈 설계에 포함 |
| 권한 | Spatie 전환과 별도로 진행, Policy 내부만 교체 |
| 기존 shared/api | SPA 전용 클라이언트를 새로 작성하고, Blade용 shared/api는 유지한다 |
