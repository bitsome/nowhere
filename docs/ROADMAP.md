# ROADMAP

## 목적
- NoWhere 프로젝트의 개발 단계와 우선순위를 정리한다.
- 현재 진행 중인 범위와 다음 작업 범위를 명확히 구분한다.
- AI와 개발자가 동일한 순서로 작업할 수 있도록 기준을 제공한다.

## 진행 상태 기준
- `완료`: 현재 기준으로 구조 또는 기능 구현이 끝난 상태
- `진행 중`: 현재 작업 중이거나 다음 즉시 작업 범위
- `예정`: 아직 시작하지 않은 단계

## Phase 1

### Auth Module
- 상태: `완료`
- 범위
  - Login
  - Register
  - Forgot Password
  - Profile
  - Logout
- 메모
  - 세션 기반 인증 구현 완료
  - 이메일 인증코드 기반 비밀번호 재설정 구현 완료
  - 프로필 사진, 휴대폰, 비밀번호 변경 흐름 구현 완료

## Phase 2

### Shared Module
- 상태: `완료`
- 원칙
  - 공통 기능만 개발한다.
  - 한 번에 전부 만들지 않고 단계별로 쪼개서 진행한다.
  - 재사용 가능한 구조를 먼저 만든 후 실제 화면에 적용한다.

### STEP 2-1
- 상태: `완료`
- 범위
  - Layout
    - AppLayout
    - AuthLayout
    - BlankLayout
- 메모
  - `shared/layouts/` 구조 정리 완료
  - 각 레이아웃별 `index.js` 진입점 정리 완료

### STEP 2-2
- 상태: `완료`
- 범위
  - Layout Components
    - Header
    - Sidebar
    - Content
    - Breadcrumb
    - PageTitle
- 메모
  - `AppLayout/components/` 기준 공통 레이아웃 컴포넌트 구조 정리 완료
  - 이후 모든 운영 페이지에서 재사용할 수 있는 구조로 유지

### STEP 2-3
- 상태: `완료`
- 범위
  - Base Components
    - BaseButton
    - BaseInput
    - BaseCard
    - BaseBadge
    - BaseLoading
    - Shared DataTable
- 현재 상태
  - 완료
    - BaseButton
    - BaseInput
    - BaseCard
    - BaseBadge
    - BaseLoading
    - Shared DataTable 1차
    - Shared DataTable 2차 (정렬 · 행 선택)
- 메모
  - 목적은 많은 컴포넌트를 만드는 것이 아니라 프로젝트 전체에서 재사용할 공통 UI 기준을 만드는 것이다.
  - 버튼, 입력, 카드, 배지, 로딩만 먼저 고정하고 실제 화면 전체에 재사용한다.
  - 디자인 수정은 각 Base Component 한 곳에서 끝나도록 유지한다.
  - 목록 화면은 공통 `DataTable`을 기준으로 재사용하고, 각 모듈은 컬럼과 데이터 정의만 제공하는 구조를 유지한다.

### STEP 2-4
- 상태: `완료`
- 범위
  - Feedback
    - Loading
    - Toast
    - Confirm Dialog
    - Alert
- 메모
  - `Loading`은 `BaseLoading`을 실제 피드백 계층(DataTable 로딩, 버튼 로딩, 전체 화면 로딩)에서 재사용한다.
  - `Toast`, `Confirm Dialog`, `Alert`는 사용자 반응과 상태 전달을 위한 공통 피드백 기능으로 묶는다.
  - 알림 배너(`x-alert`)와 토스트(`createToastBridge`)를 실제 업무 화면(오더 등록, 검증 오류)에 적용 완료.
   - 컴포넌트별 사용 규칙은 `docs/FOUNDATION.md`를 기준으로 유지한다.

### STEP 2-5
- 상태: `완료`
- 범위
  - API
    - Axios
    - Request
    - Response
    - Interceptor
    - Error
- 메모
  - `resources/js/shared/api/` 기준 공통 구조를 생성했다.
  - `Axios` 인스턴스를 기준으로 요청/응답 규약을 통일한다.
  - 인터셉터에서 공통 에러 처리와 인증 흐름 확장 지점을 제공한다.
  - `request`, `requestData`, `ensureCsrfCookie` 헬퍼를 통해 재사용 가능한 호출 규약을 제공한다.
  - API 계층은 실제 화면 로직이 아니라 공통 통신 규약을 담당한다.
  - 실제 서비스 계층 연결 완료: 오더 AI 구조화(`postData`), 파일 관리(`getData`), 이미지 업로드(`postData`).
  - 사용 규약은 `docs/API.md`를 기준으로 유지한다.

## 현재 우선순위
1. 서버 운영 안정화 (SSH 접속 복구·키 인증, SQLite 백업 cron, SSL) — 진행 중
2. NoWhere 마켓 (SPA + Laravel API) — 기능 개발 완료
3. 상용화 준비 (고정 주소·도메인, SQLite→MySQL 검토) — 예정

## Phase 3

### NoWhere 마켓 (SPA + Laravel API)
- 상태: `완료` (기능 개발) · 서버 운영 안정화 단계
- 범위
  - 운행(Order): 등록/마켓/가져오기(claim)/상태 전이/정산/리뷰
  - 매칭(Match): 매칭 설정/추천/콜링
  - 기사(Driver) 앱: 홈(오늘 운행·수익·빠른 매칭)/운행 이력/정산/설정
  - 커뮤니티(Community): 카테고리 7종/검색/인기 글
  - 채팅(Chat): 요청 이벤트 카드(승인/시간/경로/요금/취소), 날짜·유형 분류
  - 알림: 웹 푸시(VAPID) + 알림 센터(상황별 카테고리/색상)
  - 자동화: 자동 운행 등록(매일 09:00 KST), 일괄 정산, 배포 스크립트(deploy.sh)
- 메모
  - 마켓은 본인 등록 운행도 노출하되 본인 claim은 차단한다 (관리자 검증 목적).
  - 민감 정보는 로컬 전용 `SECRETS.md`로 분리해 커밋·업로드하지 않는다.
  - DB는 상용화 전까지 서버 SQLite 유지.

### 서버 운영 안정화 (현재)
- 상태: `진행 중`
- 범위
  - SSH 접속 복구: 비밀번호 확정(shadow 해시 정상화), SSH 키 인증 등록
  - SQLite DB 자동 백업 cron (매일, 보존 정책 포함)
  - SSL 인증서 적용 (도메인 확보 후 Let's Encrypt)
- 완료 기준
  - SSH 키로 접속이 안정화되고, git 히스토리에 노출된 비밀번호 의존이 제거된다.
  - DB 백업이 매일 자동 수행되고 복원 검증이 가능하다.

## 작업 원칙
- 한 번에 하나의 기능만 개발한다.
- 하나의 기능 완료 후 Commit 한다.
- JavaScript만 사용한다.
- TypeScript는 사용하지 않는다.
- Vue 3 Composition API와 `script setup`만 사용한다.
- **독립 프론트엔드(frontend/)는 Naive UI를 사용한다** (외부 개발자 UI 라이브러리 채택).
- 기존 CSS 규칙은 유지하되, Naive UI 적용 화면은 라이브러리 테마를 우선한다.
- 기존 컴포넌트를 우선 재사용한다.
