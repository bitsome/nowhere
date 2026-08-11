# 시스템 아키텍처

## 현재 구조 (AI 참조용)

현재 구현된 실제 구조를 기준으로 한 빠른 파악용 정리다. 아래 트리와 라우트 맵을 먼저 읽으면 코드 탐색이 쉬워진다.

### 계층 원칙

| 계층 | 위치 | 특징 |
|---|---|---|
| 공통(Shared) | `app/Shared`, `resources/js/shared`, `resources/views/components` | 안정 기준, 언제든 재사용 |
| 비즈니스 | `app/Http/Controllers/*Management*`, `app/Services`, `resources/views/dashboard/business`, `resources/js/business` | 언제든 재개발 대상, 공통과 분리 |
| 데모(Modules) | `resources/views/dashboard/modules`, `resources/js/modules` | 컴포넌트/피드백 유형 데모 페이지 |

### 디렉토리 구조

```
nowhere/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                      # 로그인/회원가입/비밀번호
│   │   │   ├── DashboardWorkspaceController.php   # 데모 페이지(모듈) + 허브
│   │   │   ├── OrderManagementController.php      # 비즈니스: 오더 CRUD + AI 구조화
│   │   │   ├── BoardManagementController.php      # 비즈니스: 게시판
│   │   │   ├── FileManagementController.php       # 비즈니스: 파일
│   │   │   ├── UserManagementController.php       # 비즈니스: 회원/권한
│   │   │   └── ProfileController.php
│   │   └── Requests/                      # 폼 검증 (Order/Profile/Auth)
│   ├── Models/                            # User, Order, OrderGroup, OrderLineItem, Board
│   ├── Services/                          # FileService, OrderGroupService, OrderSummaryAiStructurer
│   ├── Support/Orders/                    # OrderListRowBuilder, OrderWorkspaceListBuilder
│   └── Notifications/
├── routes/web.php                         # 라우트 전체 (아래 맵 참고)
├── resources/
│   ├── views/
│   │   ├── components/                    # 공용 Blade: layouts/app, alert, dialog, modal
│   │   ├── dashboard/
│   │   │   ├── business/                  # 비즈니스 화면 (order/orders/boards/users/files/nowhere)
│   │   │   ├── modules/                   # 컴포넌트 데모 (06-x) + notification
│   │   │   └── partials/sidebar-nav.blade.php
│   │   ├── dashboard.blade.php            # 허브 (비즈니스 모듈 + 컴포넌트 데모)
│   │   ├── welcome.blade.php / market.blade.php / my-orders.blade.php
│   │   └── auth/ · errors/
│   └── js/
│       ├── app.js                         # 진입점 (데모/공용 마운트, 비즈니스는 import)
│       ├── business/                      # 비즈니스 JS/Vue (언제든 재개발)
│       │   ├── order/                     # ai-structuring, line-items, workspace
│       │   ├── orders/                    # OrderDataTable/OrderCardList/OrderTableCells.vue
│       │   ├── users/                     # UserDataTable.vue + user-table/user-actions
│       │   ├── row-actions.js             # 테이블 행 액션 (삭제 확인)
│       │   └── gallery.js                 # 파일 업로드 갤러리
│       ├── shared/                        # 공통 (안정)
│       │   ├── api/                       # axios/request/response/interceptor/error (STEP 2-5)
│       │   ├── components/                # Base*/DataTable/Dropdown/Form/Modal/Dialog/Toast/ToastEditor/Icon
│       │   ├── layouts/                   # AppLayout/AuthLayout/BlankLayout
│       │   ├── notifications/             # notificationStore
│       │   └── utils/toast-bridge.js      # createToastBridge
│       └── modules/dialog/                # 데모 전용 (DialogPlayground)
├── public/build/                          # Vite 빌드 산출물
├── tests/Feature/                         # 기능 테스트 (도메인별 파일)
└── docs/                                  # 설계/규약 문서 (아래 인덱스)
```

### 라우트 맵 (routes/web.php)

| 네임스페이스 | 경로 | 담당 | 비고 |
|---|---|---|---|
| (루트) | `/`, `/login`, `/register`, `/forgot-password` | Auth | guest 미들웨어 |
| `dashboard` | `/dashboard` | 허브 | auth |
| `dashboard.market` | `/market` | 마켓 화면 | auth |
| `dashboard.my-orders` | `/my-orders` | 내 오더 | auth |
| `dashboard.business.*` | `/dashboard/business/{files,boards,users,nowhere,order...}` | 비즈니스 CRUD | auth, 재개발 대상 |
| `dashboard.modules.*` | `/dashboard/modules/{notification,dropdown,datatable,editor,dialog,components,buttons,modal,cards,lists,forms,toast,loading,alert}` | 컴포넌트 데모 | auth, 안정 |
| `profile.*` / `logout` | `/profile`, `/logout` | 프로필/로그아웃 | auth |

### 비즈니스 모듈 라우트

```
dashboard.business.files.*           # 파일 목록/업로드/다운로드/삭제
dashboard.business.boards.*          # 게시판 목록/등록/상세/수정/삭제
dashboard.business.users.*           # 회원 목록/상세/권한/역할/상태
dashboard.business.nowhere           # NoWhere 비즈니스 허브
dashboard.business.order             # 오더 워크스페이스
dashboard.business.order.create      # 예약 등록 (AI 구조화 포함)
dashboard.business.order.store / update / show / edit
dashboard.business.order.structure   # AI 구조화 (POST)
dashboard.business.order.storeStructured
dashboard.business.order.status.transition
```

### 의존 흐름

```
웹 요청 → 라우트 → 컨트롤러
  ├─ 데모: DashboardWorkspaceController → view('dashboard.modules.*')
  └─ 비즈니스: *ManagementController → Services/Models → view('dashboard.business.*')
Vue 마운트 → resources/js/app.js → business/* (비즈니스) / shared/* (공통)
서버 통신 → shared/api 헬퍼 (postData/getData + getApiErrorMessage)
```

### 문서 인덱스

| 문서 | 내용 |
|---|---|
| `docs/ROADMAP.md` | 단계별 로드맵 (STEP 2 Shared 완료, 다음은 Order Module) |
| `docs/TASKS.md` | 현재 작업 기준 (Current = Order Module) |
| `docs/FOUNDATION.md` | 공통 컴포넌트 규약 명세 |
| `docs/API.md` | shared/api 통신 규약 |
| `docs/ARCHITECTURE.md` | 아키텍처/권한/인증 설계 (본 문서) |
| `docs/DATABASE.md` | 데이터베이스 설계 |
| `docs/UI.md` | UI/디자인 기준 |
| `docs/RULES.md` | 작업 규칙 (수정 금지 규칙 등) |

---

## 전체 구조
- 애플리케이션은 Laravel 13 기반의 모놀리식 웹 구조를 사용한다.
- 서버 렌더링 중심의 Blade UI와 Laravel 라우팅, 컨트롤러, 세션 인증으로 구성한다.
- 현재는 인증과 기본 대시보드를 시작점으로 두고, 이후 도메인 기능을 모듈 단위로 확장하는 구조를 목표로 한다.
- 상위 구조 기준은 `Application > Core / Shared / Modules`다.

## Backend
- 프레임워크: Laravel 13
- 언어: PHP 8.3
- 주요 계층
  - `routes/`: 웹 요청 진입점
  - `app/Http/Controllers/`: 요청 처리 및 응답 반환
  - `app/Http/Requests/`: 입력 검증
  - `app/Models/`: Eloquent 모델
  - `database/`: 마이그레이션, 팩토리, 시더
- 현재 백엔드는 세션 인증, 뷰 반환, 폼 검증 중심으로 구성되어 있다.
- 공통으로 재사용되는 화면 데이터 조립 규칙, 포맷 규칙, row builder 규칙은 컨트롤러나 Blade에 장기 보관하지 않고 분리 가능한 PHP class 계층으로 이동하는 것을 우선한다.

## Frontend
- 렌더링 방식: Blade 기반 서버 렌더링
- 스타일링: 프로젝트 내부 plain CSS
- 번들러: Vite
- 프런트 구조 원칙: `Application > Core / Shared / Modules` 기준 해석
- 주요 화면
  - 랜딩 페이지
  - 로그인 페이지
  - 인증 후 대시보드
- 공용 레이아웃과 재사용 스타일 클래스를 통해 UI 일관성을 유지한다.
- 현재 공통 UI는 `resources/js/shared/` 기준으로 유지한다.
- 현재 `Core`, `Shared`, `Modules`는 상위 설계 기준으로 사용한다.
- 실제 업무 기능은 장기적으로 `Modules/{Domain}` 구조로 확장한다.
- 각 업무 모듈은 내부에서 `Models`와 `UI` 계층을 분리한다.
- 비즈니스 모델과 비즈니스 UI는 같은 폴더에 섞지 않는다.
- NoWhere 비즈니스 UI는 공통 Foundation 위에서 동작하지만, `shared`와 구분되는 독립적인 디자인과 스타일 레이어를 가진다.
- 프론트엔드 화면은 렌더링과 사용자 상호작용에 집중하고, 반복되는 데이터 조립 규칙이나 재사용 가능한 뷰 모델 생성 규칙은 공통 계층으로 분리한다.
- Blade와 Vue는 최종 출력 계층이며, 공통 기능 규칙을 직접 소유하지 않는 방향을 기본으로 한다.

## Redis
- 현재 프로젝트에는 Redis가 활성 사용되고 있지 않다.
- 향후 사용 가능 영역
  - 캐시 저장소
  - 큐 드라이버
  - 실시간 상태 관리
  - 배차 후보 계산용 임시 데이터 저장
- Redis 도입 시 `config/cache.php`, `config/database.php`, `config/queue.php` 정책을 함께 정리한다.

## Queue
- 현재 기본 jobs 테이블 마이그레이션은 존재하지만, 실제 비즈니스 큐 작업은 아직 없다.
- 향후 큐 적용 후보
  - 알림 발송
  - 대용량 배차 계산
  - 로그/이벤트 후처리
  - 비동기 리포트 생성
- 큐 사용 시 재시도 정책, 실패 처리, 모니터링 기준을 정의해야 한다.

## Notification
- 현재 알림 기능은 구현되어 있지 않다.
- Laravel Notification 구조를 기준으로 확장 가능하다.
- 적용 후보
  - 로그인 관련 보안 알림
  - 배차 상태 변경 알림
  - 운영자 경고 알림
  - 사용자 대상 시스템 메시지

## Storage
- 현재 기본 Laravel 스토리지 구조를 사용한다.
- 주요 역할
  - 로그 저장
  - 세션/캐시 파일 저장
  - 업로드 파일 저장 확장 가능
- 파일 업로드 기능 도입 시 로컬/클라우드 스토리지 전략을 별도로 정의한다.
- 파일 업로드/첨부 저장 엔진은 `spatie/laravel-medialibrary`를 공식 기준으로 사용한다.
- 파일 업로드 기능은 특정 도메인 모듈에 종속시키지 않고 공통 `File Module`로 분리하는 것을 기본 원칙으로 한다.
- `Board`, `Profile`, `Order`, `Driver` 등 파일이 필요한 모든 모듈은 공통 파일 모듈을 재사용한다.
- 역할은 `Upload`와 `Manager`로 분리하는 것을 우선 검토한다.

## 인증 구조
- 인증 방식: Laravel 세션 기반 인증
- 로그인 시 `Auth::attempt()`로 인증
- 성공 시 세션 재생성
- 로그아웃 시 세션 무효화 및 CSRF 토큰 재생성
- 인증이 필요한 화면은 `auth` 미들웨어로 보호한다.
- 비로그인 사용자는 `guest` 미들웨어 기준으로 로그인 페이지 접근이 허용된다.

## 권한 구조
- 권한 시스템의 공식 기준은 `spatie/laravel-permission`이다.
- 현재 `users.role`, `users.permissions`는 임시 구조이며, Spatie 설치 및 전환 완료 전까지 한시적으로 유지한다.
- 권한의 목적은 회원관리 자체가 아니라 운영 권한 부여와 메뉴/기능 접근 제어다.

### Role
- 1차 Role은 아래 4개만 사용한다.
  - `Super Admin`
  - `Admin`
  - `Operator`
  - `Driver`

### Super Admin 규칙
- `Super Admin`은 고유번호 `1` 사용자만 허용한다.
- 고유번호 `1` 이외 사용자에게 `Super Admin`을 부여하지 않는다.
- `Super Admin` Role은 다른 사용자에게 신규 부여하지 않고, 고유번호 `1` 사용자에게만 유지한다.
- Role 변경 로직과 Seeder 모두 이 규칙을 동일하게 따라야 한다.

### 권한 위임 규칙
- 고유번호 `1` 사용자는 `Super Admin`을 제외한 모든 하위 Role과 Permission을 부여할 수 있다.
- 고유번호 `1` 이외 사용자는 다른 사용자에게 `Admin` 권한 이상을 부여할 수 없다.
- 현재 권한이 `Admin`인 사용자는 `Admin` 하위 권한만 부여할 수 있다.
- 사용자는 자신의 권한과 동등한 권한 또는 상위 권한을 다른 사용자에게 부여할 수 없다.
- 권한 부여는 항상 `부여하는 사용자 > 부여받는 사용자` 관계를 만족해야 한다.
- 이 규칙은 UI 노출, 요청 검증, 저장 처리, Seeder 정책에서 동일하게 유지한다.

### Permission 네이밍 규칙
- 모든 Permission은 `모듈.기능` 형식으로 정의한다.
- **실제 사용 중인 Permission은 `App\Models\User::permissionOptions()`가 단일 기준이다.**
  - `board.view`, `board.create`, `board.update`, `board.delete`, `board.comment`
  - `order.create`, `order.status.update`
  - `dispatch.assign`
- 권한 검증은 `App\Policies`(BoardPolicy/OrderPolicy/UserPolicy)를 통해 라우트 `can:` 미들웨어와 컨트롤러에서 공용으로 사용한다.
- 예시
  - `dashboard.view`
  - `user.view`
  - `user.create`
  - `user.update`
  - `user.delete`
  - `user.permission`
  - `role.view`
  - `role.create`
  - `role.update`
  - `role.delete`
  - `board.view`
  - `board.create`
  - `board.update`
  - `board.delete`
  - `order.view`
  - `order.create`
  - `order.update`
  - `order.delete`
  - `order.status.update`
  - `dispatch.view`
  - `dispatch.assign`
  - `dispatch.cancel`
  - `driver.view`
  - `driver.create`
  - `driver.update`
  - `driver.delete`
  - `setting.view`
  - `setting.update`

### 메뉴 권한 분리
- 메뉴 접근 권한은 기능 권한과 분리하여 `menu.*` 규칙으로 관리한다.
- 예시
  - `menu.dashboard`
  - `menu.user`
  - `menu.role`
  - `menu.board`
  - `menu.order`
  - `menu.dispatch`
  - `menu.driver`
  - `menu.setting`
- 프런트에서는 `can('menu.user')` 같은 방식으로 사이드바 노출을 제어한다.

### 화면 액션 권한 분리
- 메뉴 권한과 화면 액션 권한은 혼합하지 않는다.
- 예시 (`User` 화면)
  - 회원목록 보기: `user.view`
  - 회원추가 버튼: `user.create`
  - 회원삭제 버튼: `user.delete`
  - 회원 권한 변경: `user.permission`

### 전환 전략
- 완료: 권한 검증 일원화 — `App\Policies`(Board/Order/User) + 라우트 `can:` 미들웨어 적용 (임시 컬럼 기반 `hasPermission` 사용).
- 잔여(다음 페이즈): `spatie/laravel-permission` 전환
  - 1단계: `composer require spatie/laravel-permission`
  - 2단계: config/migration publish + migrate
  - 3단계: Permission/Role Seeder 작성
  - 4단계: `User`에 `HasRoles` 적용, `hasPermission` → `hasPermissionTo`/`can()` 교체
  - 5단계: UserFactory·컨트롤러·뷰(권한/역할 관리) 교체
  - 6단계: 테스트를 Spatie 기반으로 갱신
  - 7단계: `menu.*` 메뉴 권한 + Vue `can()` 적용
- 전환 완료 전까지는 현재 임시 컬럼 구조를 유지한다.

## 이벤트 구조
- 현재 커스텀 이벤트/리스너는 아직 도입되지 않았다.
- 향후 이벤트 기반 확장 후보
  - 사용자 로그인 이벤트
  - 배차 생성/변경 이벤트
  - 알림 발송 이벤트
  - 감사 로그 기록 이벤트
- 이벤트 구조 도입 시 도메인 이벤트와 인프라 이벤트를 구분하는 것이 바람직하다.

## 모듈 관계도
- 현재는 작은 규모의 단일 애플리케이션 구조다.
- 기본 관계
  - Route -> Controller -> Request Validation -> Model/View
  - Auth Middleware -> Protected Route -> Dashboard View
- 향후 목표 모듈
  - 인증 모듈
  - 사용자 모듈
  - 파일 모듈
  - 공통 DataTable 모듈
  - 게시판 모듈
  - 배차 모듈
  - 알림 모듈
  - 운영/관리 모듈

## Application 구조
- `Core`
  - 프로젝트 실행에 필요한 핵심 기능
  - 예: `Auth`, `User`, `Role`, `Permission`, 접근 제어, 앱 부트스트랩
- `Shared`
  - 프로젝트 전체에서 재사용되는 Foundation UI와 공통 기능
  - 예: `DataTable`, `Form`, `Modal`, `Dialog`, `Toast`, `Loading`, `File Manager`, `Markdown Editor`
- `Modules`
  - 실제 업무 기능
  - 예: `Board`, `Customer`, `Company`, `Vehicle`, `Driver`, `Order`, `Dispatch`, `Settlement`

## Modules 내부 구조
- 각 업무 모듈은 `Modules/{Domain}` 구조를 기준으로 확장한다.
- `Modules/{Domain}/Models`
  - 도메인 데이터 처리 계층
  - 예: API 호출, 상수, 서비스, 헬퍼, 매퍼, 옵션 로딩, row builder, formatter, list builder
- `Modules/{Domain}/UI`
  - 실제 도메인 화면 UI 계층
  - 예: 페이지 컴포넌트, 도메인 폼 조합, 상세 패널, 도메인 전용 카드
- 핵심 원칙
  - 공통 UI는 `Shared`에 둔다.
  - 모듈의 `Models`에 UI를 넣지 않는다.
  - 모듈의 `UI`는 `Shared` 공통 컴포넌트를 조합해서 만든다.
  - 모듈의 `UI`는 독립적인 레이아웃, 패널 구조, 상태 표현, 화면 밀도, 모듈 전용 스타일을 가질 수 있다.
  - 두 개 이상의 화면에서 반복되는 규칙이나 프론트 재사용이 필요한 기능은 `UI` 내부에 중복 보관하지 않고 공통 분리 구조로 옮긴다.
