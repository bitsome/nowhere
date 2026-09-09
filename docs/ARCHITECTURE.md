# 시스템 아키텍처

## 현재 구조 (AI 참조용)

현재 구현된 실제 구조를 기준으로 한 빠른 파악용 정리다. 아래 트리와 라우트 맵을 먼저 읽으면 코드 탐색이 쉬워진다.

### 개요

- **Laravel 13 (PHP 8.3)** 백엔드 + **독립 프론트엔드 SPA (Vue 3, Vite)** 병행 구조다.
- 백엔드는 `routes/api.php`의 JSON API(Sanctum Bearer 인증)만 제공하고, `routes/web.php`는 SPA 진입점과 폴백만 담당한다.
- 프론트는 `frontend/` 독립 프로젝트로, `/api` 요청을 백엔드로 프록시한다.
- 데이터베이스는 로컬 개발 시 SQLite(`database/database.sqlite`), 서버 배포 시 MySQL을 사용한다.

### 계층 원칙

| 계층 | 위치 | 특징 |
|---|---|---|
| 프레젠테이션 | `frontend/src/views`, `frontend/src/components` | Vue 화면/컴포넌트, 상태·UI에 집중 |
| 프론트 상태 | `frontend/src/stores` (Pinia) | 인증/드라이버/채팅/알림/테마/UI 전역 상태 |
| 프론트 API | `frontend/src/api` | 도메인별 API 클라이언트 (axios) |
| 비즈니스 로직 | `app/Services` | 도메인 서비스 계층 — 주문/채팅/커뮤니티/드라이버/매칭 등 |
| API 컨트롤러 | `app/Http/Controllers/Api` | 요청 검증·권한 확인 후 서비스 호출, JSON 응답 |
| 모델 | `app/Models` | Eloquent 모델 (도메인별) |
| 지원 | `app/Support` | row builder 등 화면 데이터 조립·포맷 규칙 분리 계층 |

### 디렉토리 구조

```
nowhere/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                     # JSON API 컨트롤러 전부
│   │   │   │   ├── AuthController.php   # 로그인/회원가입/me/프로필/로그아웃
│   │   │   │   ├── OrderController.php  # 운행 목록/상세/등록/상태 전이/가져오기
│   │   │   │   ├── OrderOfferController.php   # 요금 제안(오퍼)
│   │   │   │   ├── OrderTemplateController.php # 운행 템플릿
│   │   │   │   ├── MatchController.php  # 빠른매칭 설정/자동 매칭
│   │   │   │   ├── DriverController.php # 기사 상태/통계/차량/정산
│   │   │   │   ├── ChatController.php   # 채팅 대화/메시지/이미지
│   │   │   │   ├── CommunityController.php     # 커뮤니티 피드/글/댓글
│   │   │   │   ├── NotificationController.php  # 알림 목록/읽음
│   │   │   │   ├── ReviewController.php # 리뷰
│   │   │   │   ├── ActionCenterController.php  # 처리할 일(승인/제안/채팅 요청)
│   │   │   │   ├── AdminController.php  # 운영 관리(회원/기사/자동 등록)
│   │   │   │   ├── StatsController.php  # 통계
│   │   │   │   ├── StreamController.php # SSE 실시간 이벤트 스트림
│   │   │   │   ├── VerificationController.php  # 기사 인증
│   │   │   │   └── PushSubscriptionController.php # 웹 푸시 구독
│   │   │   └── Controller.php           # 공통 베이스
│   │   └── Requests/                    # 폼 검증 (Order/Profile 등)
│   ├── Models/                          # User, Order, OrderGroup, OrderLineItem, OrderClaim,
│   │                                    # OrderOffer, OrderTemplate, Driver, Vehicle, Conversation,
│   │                                    # Message, ChatImageUpload, CommunityPost, CommunityComment,
│   │                                    # Review, MatchPreference, PushSubscription, AutoOrderSetting,
│   │                                    # UserLevelEvent
│   ├── Services/                        # 도메인 서비스
│   │   ├── Order/                       # OrderListService, OrderTransitionService, OrderClaimService,
│   │   │                                # OrderOfferService, OrderCreator, ActionCenterService
│   │   ├── Chat/ChatService.php         # 대화·메시지·읽음 처리
│   │   ├── Community/                   # CommunityPostService, UserProfileService
│   │   ├── Driver/                      # DriverService, VehicleService
│   │   ├── Push/WebPushService.php      # 웹 푸시 발송
│   │   ├── MatchService.php             # 빠른매칭 조건 판정(isEligible)
│   │   ├── OrderGroupService.php        # 셋트(묶음) 운행
│   │   └── OrderSummaryAiStructurer.php # AI 구조화 (요약 → 구조 데이터)
│   ├── Support/
│   │   ├── Orders/                      # OrderListRowBuilder, OrderWorkspaceListBuilder
│   │   └── Leveling/LevelTable.php      # 레벨 테이블
│   ├── Notifications/                   # OrderNotification, PasswordResetCodeNotification, WebPushChannel
│   ├── Policies/                        # OrderPolicy, UserPolicy
│   ├── Console/Commands/                # AutoRegisterOrders, SendRideReminders, SendServerNotification,
│   │                                    # SendTestPush, SimulateTraffic
│   └── Providers/AppServiceProvider.php
├── routes/
│   ├── api.php                          # 전체 API 라우트 (Sanctum 인증)
│   └── web.php                          # SPA 진입점(`/`) + 폴백(index.html 서빙)
├── frontend/                            # 독립 Vue 3 + Vite SPA
│   └── src/
│       ├── api/                         # 도메인별 API 클라이언트 (orders, chats, driver, match, ...)
│       ├── components/                  # 공용/도메인 컴포넌트
│       │   ├── ui/                      # UiCard, UiChip, UiListRow, UiSection
│       │   ├── common/                  # BaseIcon, EmptyState, LevelBadge, SegmentedGroup, ...
│       │   ├── layout/                  # HeaderBar, ChatListener, NotificationListener
│       │   ├── orders/                  # OrderCard, OrderDetailChat, SetGroupCard, ...
│       │   ├── chat/                    # ChatThread, MessageBubble, ...
│       │   └── community/               # CommunityPostEditor
│       ├── composables/                 # Vue 컴포저블 (useOrderForm, useOrderSchedule, useOrderDetail, ...)
│       ├── stores/                      # Pinia (auth, chats, driver, notifications, theme, ui)
│       ├── router/index.js              # Vue Router (lazy import, 인증 가드)
│       ├── utils/                       # colors, icons, eventStream, koreanHolidays, ...
│       ├── views/                       # 화면 (도메인별 하위 폴더)
│       │   ├── auth/                    # LoginView, RegisterView
│       │   ├── orders/                  # OrderDetailView, OrderCreateView, MyMarketView, RideHistoryView,
│       │   │                            # SettlementView, ActionCenterView
│       │   ├── community/               # CommunityView, CommunityPostView, UserPageView
│       │   ├── settings/                # SettingsView, SettingsProfileView, SettingsVehiclesView, ...
│       │   └── ...                      # HomeView, MarketView, MatchView, ChatView, MoreView, ...
│       ├── App.vue                      # 루트 레이아웃 (하단 탭, 헤더, 이벤트 리스너)
│       └── main.js
├── database/                            # 마이그레이션, 팩토리, 시더 (+ SQLite 개발용)
├── resources/views/                     # welcome.blade.php (비로그인 안내) 정도만 유지
├── public/                              # SPA 빌드 산출물(index.html + assets) 서빙 위치
├── tests/
│   └── Feature/Api/                     # Pest 기반 API 기능 테스트 (도메인별 파일)
└── docs/                                # 설계/규약 문서 (아래 인덱스)
```

### 라우트 맵

#### 웹 (`routes/web.php`)

| 경로 | 역할 |
|---|---|
| `/` | 비로그인 → `welcome` 안내, 로그인 상태 → `config('app.frontend_url')` 리다이렉트 |
| `*` (폴백) | SPA의 `index.html` 서빙 (`Cache-Control: no-cache` — 모바일에서 항상 최신 빌드 참조) |

#### API (`routes/api.php`) — 일부

| 그룹 | 대표 엔드포인트 | 비고 |
|---|---|---|
| 인증 | `POST /api/auth/{register,login}` `GET/PATCH /api/auth/me` `POST /api/auth/logout` | public(register/login) |
| 실시간 | `GET /api/events` | SSE — `?token=` 인증 (EventSource 헤더 제약) |
| 운행 | `GET /api/orders` `GET /api/orders/{order}` `POST /api/orders` `POST /api/orders/{order}/status` | 목록/상세/등록/상태 전이 |
| 가져오기 | `POST /api/orders/{order}/claim` `/approve` `/reject` `/withdraw` | 기사만 claim 가능 |
| 오퍼 | `GET /api/offers/inbox` `POST /api/orders/{order}/offers` `/accept` `/reject` | 요금 제안 |
| 매칭 | `GET/POST/PATCH/DELETE /api/me/match-preferences` | 자동 매칭 설정 |
| 기사 | `GET /api/me/driver` `PATCH /api/me/driver/status` `GET /api/me/settlements` `GET/POST/PATCH/DELETE /api/me/vehicles` | 기사 상태/통계/정산/차량 |
| 채팅 | `GET/POST /api/chats` `GET /api/chats/{conversation}/sync` `POST /api/chats/{conversation}/messages` | 증분 동기화(`after_id`) |
| 처리할 일 | `GET /api/actions` | 승인 요청/제안/채팅 요청 집계 |
| 알림 | `GET /api/notifications` `POST /api/notifications/read` | |
| 커뮤니티 | `GET/POST /api/community/posts` `/comments` `/like` | |
| 운영 | `GET /api/admin/users` `/drivers` `/auto-order-settings` | Admin 전용 |
| 통계 | `GET /api/stats/orders` | |

#### 프론트 (Vue Router) — 주요 라우트

| 경로 | 이름 | 화면 |
|---|---|---|
| `/` | home | 홈 (추천/빠른 메뉴) |
| `/market` | market | 운행 마켓 |
| `/match` | match | 빠른매칭 |
| `/my-market` | my-market | 내 마켓 (등록 운행 관리) |
| `/history` | history | 운행 기록 (전체/진행중/완료/정산) |
| `/actions` | actions | 처리할 일 |
| `/settlements` | settlements | 정산 내역 (목록/캘린더) |
| `/chat` | chat | 채팅 |
| `/community` | community | 커뮤니티 |
| `/more` | more | 더보기 |
| `/profile` | profile | 회원정보 |
| `/orders/:id` | order-detail | 운행 상세 |
| `/orders/create` | order-create | 운행 등록 |
| `/admin` | admin | 운영 관리 (Admin 전용) |

### 의존 흐름

```
모바일/브라우저(SPA) → Vue Router → view/component
        │
        └── frontend/src/api (axios) → /api/* → api.php 라우트
                │
                └── Api 컨트롤러 → Requests(검증) → Services(비즈니스) → Models
                        │
                        └── Support/*Builder → JSON 응답 (row 조립·포맷)
실시간: SSE(/api/events) + 채팅 증분 폴링(/sync?after_id) + 알림 리스너
```

### 인증 구조

- 인증 방식: **Laravel Sanctum Bearer 토큰** (SPA 전용 API)
- 로그인 성공 시 액세스 토큰을 발급하고 프론트는 `localStorage('auth_token')`에 보관
- API 라우트는 `auth:sanctum` 미들웨어로 보호 (register/login/SSE 제외)
- 프론트 라우터 가드: `meta.requiresAuth` / `meta.adminOnly` 기준으로 로그인·권한 분기

### 권한 구조

- 권한 시스템의 공식 기준은 `App\Models\User::permissionOptions()` 단일이다.
- **역할값 단일 소스**: `User::ROLE_*` 상수(5개: Super Admin / Admin / Operator / Driver / Customer). 라벨·순위는 `User::roleLabels()`, `User::roleRanks()`가 각각 담당한다.
  - 관리자 기능 접근 역할 묶음: `User::ADMIN_ROLES` (Admin / Super Admin). 관리자 전용 API 컨트롤러는 공통 `AuthorizesAdmin` 트레이트(`authorizeAdmin()`/`assertAdmin()` 하위 호환)로 인가한다.
- 현재 임시 컬럼 기반(`users.role`, `users.permissions`)이다. Spatie 전환 검토 결과 **현 단계 미도입** — 역할 수가 적고 실질 권한 검사 지점(OrderPolicy 등)이 한정적이라 패키지 의존성·테이블 전환 비용 대비 이득이 크지 않다. (성장 후 검토)
- `Super Admin`은 고유번호 `1`(시스템 루트, `User::ROOT_USER_ID`) 사용자만 허용한다.
  - 고유번호 `1` 이외 사용자는 다른 사용자에게 `Admin` 권한 이상을 부여할 수 없다. (`canAssignRole`, `roleRank`)
- 권한 위임은 항상 `부여하는 사용자 > 부여받는 사용자` 관계를 만족해야 한다. (`canManageUser`)
- **Hard Constraint: 기사(Driver) 역할만 운행 가져오기(claim) 및 요금 제안(offer)이 가능하다.**
- 프런트는 `frontend/src/data/roles.js`가 동일 역할 상수·라벨의 단일 소스다. (백엔드와 함께 수정)

### 핵심 도메인 흐름

#### 운행 상태 전이 (OrderTransitionService — docs/ORDER_FLOW.md가 단일 소스)

```
초안(draft) → 공개(published) → 수락 대기(acceptance_pending) → 예약(accepted)
    → 운행중(driving) → 완료(completed) → 정산(settled)
    └ 취소(cancelled) — 초안~예약까지 가능, 거래중(trading)은 과거·데모 대응용 레거시
```
- 진행/완료는 운행자·수행자만, **완료→정산은 등록자(원 등록자)만** 가능하다.
- 완료(정산 전) 상태는 진행자 화면에서 **'정산 대기중'** 라벨로 표시된다.

#### 가져오기(Claim) 흐름
- 기사가 마켓 운행에 claim → 등록자는 `처리할 일(ActionCenter)`에서 승인/거절
- 승인 시 `user_id`가 기사로 변경되고 `original_owner_id`에 등록자 보존

#### 요금 제안(Offer) 흐름
- 기사가 운행에 제안 → 등록자가 여러 제안 비교 후 수락/거절
- 수락 전까지 기사 연락처는 비공개

#### 채팅
- 운행 상세에서 등록자·기사 간 1:1 대화 (Conversation/Message)
- 최초 전체 로드 후 `after_id` 기반 증분 동기화로 경량화
- SSE(탭 숨김 시) + 3초 폴링 병행

#### 빠른매칭 (MatchService)
- `isEligible`: 기사 온라인 + match_enabled + 설정 조건(날짜/지역/시간/요일/인원/수익) + 시간 중복 제외
- on_trip(운행중) 상태는 매칭 후보에서 제외

### UI 규칙 요약

- 스타일: 프로젝트 내부 plain CSS (`frontend/src/assets/base.css` + 컴포넌트 scoped) + Naive UI
- 상태 배지: padding `1px 6px`, font-size `10px`, font-weight `400`(bold 제거)
- 배지 색상: 중앙 팔레트 `frontend/src/utils/colors.js` (`statusColorVar`) 참조
- 목록 row 조립·포맷 규칙은 화면이 아닌 `app/Support/Orders/*Builder` 계층에서 처리한다.

### 문서 인덱스

| 문서 | 내용 |
|---|---|
| `docs/ARCHITECTURE.md` | 아키텍처/인증/권한 설계 (본 문서) |
| `docs/PROJECT.md` | 프로젝트 개요 |
| `docs/API.md` / `docs/API_SPLIT.md` | API 통신 규약/분리 설계 |
| `docs/DATABASE.md` | 데이터베이스 설계 |
| `docs/UI.md` / `docs/COLORS.md` | UI/디자인 기준, 색상 팔레트 |
| `docs/RULES.md` | 작업 규칙 (수정 금지 규칙 등) |
| `docs/DEPLOY.md` | 배포 절차 (터널/서버 동기화) |
| `docs/CHANGELOG.md` | 변경 이력 |
