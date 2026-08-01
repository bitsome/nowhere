# 시스템 아키텍처

## 전체 구조
- 애플리케이션은 Laravel 13 기반의 모놀리식 웹 구조를 사용한다.
- 서버 렌더링 중심의 Blade UI와 Laravel 라우팅, 컨트롤러, 세션 인증으로 구성한다.
- 현재는 인증과 기본 대시보드를 시작점으로 두고, 이후 도메인 기능을 모듈 단위로 확장하는 구조를 목표로 한다.

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

## Frontend
- 렌더링 방식: Blade 기반 서버 렌더링
- 스타일링: 프로젝트 내부 plain CSS
- 번들러: Vite
- 주요 화면
  - 랜딩 페이지
  - 로그인 페이지
  - 인증 후 대시보드
- 공용 레이아웃과 재사용 스타일 클래스를 통해 UI 일관성을 유지한다.

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
- 1단계: `spatie/laravel-permission` 설치
- 2단계: Permission Seeder 작성
- 3단계: Role Seeder 작성
- 4단계: User ↔ Role 연결
- 5단계: Role 관리 페이지
- 6단계: Permission 관리 페이지
- 7단계: User 권한 변경
- 8단계: Vue `can()` 적용
- 전환 완료 전까지는 현재 임시 컬럼 구조를 유지하고, 완료 후 점진적으로 교체한다.

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

## 공통 DataTable 구조
- 목록 화면은 단순한 테이블이 아니라 공통 `List Framework` 성격의 `DataTable`을 기준으로 설계한다.
- 공통 경로는 `resources/js/shared/components/DataTable/`를 기준으로 유지한다.
- 각 모듈은 `columns`, `rows`, 검색어, 필터 값, 페이지 정보만 제공하고, 목록 출력 UI는 공통 `DataTable`이 담당한다.
- `UserList`, `BoardList`, `OrderList`, `DriverList`, `DispatchList`, `SettlementList`는 공통 `DataTable`을 재사용하는 구조를 기본 원칙으로 한다.
- 도메인 전용 `UserTable`, `BoardTable`, `OrderTable` 같은 개별 출력 컴포넌트는 특별한 사유가 없으면 만들지 않는다.
- 1차 범위는 아래 기능만 포함한다.
  - 컬럼 출력
  - 데이터 출력
  - 빈 데이터 표시
  - 로딩
  - 페이지네이션
  - 검색
  - Toolbar
  - Slot 지원
- 2차 이후 후보 기능은 정렬, 체크박스, 전체선택, 다중선택, 컬럼 숨김, 컬럼 순서 변경이다.
- 3차 이후 후보 기능은 Drag Sort, Excel Export/Import, Row Expand, Tree Table이다.

## 공통 Toast UI Editor / Viewer 구조
- 문서형 본문 입력과 조회는 공통 `Toast UI Editor / Viewer` 구조를 기준으로 설계한다.
- 공통 경로는 `resources/js/shared/components/ToastEditor/`를 기준으로 유지한다.
- 입력 컴포넌트는 `ToastEditor.vue`, 조회 컴포넌트는 `ToastViewer.vue`를 사용한다.
- 저장 기준 값은 HTML이 아니라 Markdown 문자열을 우선 사용한다.
- `Board`, `Notice`, `FAQ`, `QnA`, `Operation Manual` 같은 문서형 모듈은 공통 Editor / Viewer를 재사용하는 구조를 기본 원칙으로 한다.
- 도메인 전용 `BoardEditor`, `NoticeEditor`, `FaqEditor` 같은 개별 에디터 컴포넌트는 특별한 사유가 없으면 만들지 않는다.
- Editor 이미지 업로드는 도메인 모듈 내부에서 별도 저장 로직을 만들지 않고 공통 `File Module`과 `spatie/laravel-medialibrary` 훅을 연결한다.
- 1차 범위는 아래 기능만 포함한다.
  - Markdown / WYSIWYG 전환
  - 공통 Viewer 렌더링
  - Markdown 문자열 동기화
  - Blade 대시보드 테스트 페이지 연결

## 파일 모듈 구조
- 업로드/저장은 `spatie/laravel-medialibrary`가 담당하고, NoWhere 전용 파일 관리 UI와 업무 규칙은 공통 `File Module`이 담당한다.
- 백엔드에서는 `app/Services/FileService.php`를 공통 진입점으로 두고 Media Library 호출을 캡슐화한다.
- 파일 기능은 공통 `File Module`로 분리하고, 다른 모듈은 이를 가져다 사용하는 구조를 기본으로 한다.
- 1차 범위 후보
  - 파일 업로드
  - 파일 삭제
  - 파일 다운로드
  - 이미지 미리보기
  - 파일 목록
- 역할 분리 원칙
  - `Upload`: 게시판, 오더, 프로필, 기사 등 일반 화면에서 첨부/업로드 용도로 사용
  - `Manager`: 업로드된 파일 조회, 삭제, 관리가 필요한 관리자 화면에서 사용
- 적용 대상 예시
  - `Board` -> 공통 파일 업로드/목록
  - `Profile` -> 공통 이미지 업로드
  - `Order` -> 공통 파일 업로드/미리보기
  - `Driver` -> 공통 프로필 이미지 업로드
- 파일 기능이 필요한 새 모듈이 생기면 각 모듈 내부에서 별도 업로드 로직을 만들지 않고 공통 파일 모듈을 우선 재사용한다.

## 게시판 구조
- 게시판은 하나의 공통 `Board` 모듈로 운영하고 `type` 값으로 `notice`, `free`, `qna`를 구분한다.
- 1차 범위는 `목록`, `상세`, `등록`, `수정`, `삭제`, `검색`만 포함한다.
- 첨부파일은 게시판 내부에 별도 저장 구조를 만들지 않고 공통 `File Module`과 `spatie/laravel-medialibrary`를 재사용한다.
- 게시글 등록/수정은 공통 Upload 성격의 파일 업로드를 사용하고, 상세 화면에서는 첨부 목록과 다운로드를 제공한다.
- 현재 제외 항목은 `댓글`, `좋아요`, `신고`, `북마크`, `FAQ`, `상단 고정`, `실시간 알림`이다.
- UI는 현재 프로젝트 기준에 맞춰 Blade 기반 모듈 페이지로 구현한다.
- 경로는 `/dashboard/modules/boards`를 기준으로 사용한다.

### boards 테이블
- `id`
- `type`
- `title`
- `content`
- `user_id`
- `status`
- `is_notice`
- `is_private`
- `view_count`
- `created_at`
- `updated_at`

### Board 권한
- `board.view`
- `board.create`
- `board.update`
- `board.delete`
- `board.comment`은 댓글 단계에서 사용할 예약 권한으로 유지한다.

## 요청 흐름
1. 사용자가 브라우저에서 웹 페이지 요청
2. Laravel Router가 URL을 매칭
3. 필요한 미들웨어(`web`, `auth`, `guest`) 실행
4. Controller 또는 Closure가 요청 처리
5. FormRequest로 입력값 검증
6. 인증 또는 비즈니스 로직 수행
7. Blade View 또는 Redirect Response 반환
8. 브라우저가 결과 화면 렌더링

## 배포 구조
- 현재는 전형적인 Laravel 웹 배포 구조를 가정한다.
- 기본 구성 요소
  - 웹 서버(Nginx/Apache)
  - PHP 런타임
  - 애플리케이션 코드
  - SQLite 또는 향후 RDBMS
  - 필요 시 Queue Worker / Redis / Scheduler
- 확장 시 고려 사항
  - 환경 변수 분리
  - 자산 빌드 자동화
  - 마이그레이션 배포 절차
  - 백그라운드 워커 운영
  - 로그 및 장애 모니터링
