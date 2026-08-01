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
- 상태: `진행 중`
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
- 상태: `진행 중`
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
  - 예정
- 메모
  - 목적은 많은 컴포넌트를 만드는 것이 아니라 프로젝트 전체에서 재사용할 공통 UI 기준을 만드는 것이다.
  - 버튼, 입력, 카드, 배지, 로딩만 먼저 고정하고 실제 화면 전체에 재사용한다.
  - 디자인 수정은 각 Base Component 한 곳에서 끝나도록 유지한다.
  - 목록 화면은 공통 `DataTable`을 기준으로 재사용하고, 각 모듈은 컬럼과 데이터 정의만 제공하는 구조를 유지한다.

### STEP 2-4
- 상태: `예정`
- 범위
  - Feedback
    - Loading
    - Toast
    - Confirm Dialog
    - Alert
- 메모
  - `Loading`은 새 베이스 컴포넌트를 추가로 만드는 것이 아니라 `BaseLoading`을 실제 피드백 계층에서 재사용하는 단계다.
  - `Toast`, `Confirm Dialog`, `Alert`는 사용자 반응과 상태 전달을 위한 공통 피드백 기능으로 묶는다.
  - Shared Module의 다음 확장 포인트는 API보다 먼저 사용자 피드백 계층을 안정화하는 것이다.

### STEP 2-5
- 상태: `진행 중`
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

## 현재 우선순위
1. `BaseButton`, `BaseInput`, `BaseCard`, `BaseBadge`, `BaseLoading`를 실제 화면에 점진적으로 적용
2. `STEP 2-4`의 `Feedback` 구조 설계
3. `Toast`, `Confirm Dialog`, `Alert` 공통 규약 확정
4. `STEP 2-5`의 `shared/api` 사용 규약을 실제 서비스 계층으로 연결

## 작업 원칙
- 한 번에 하나의 기능만 개발한다.
- 하나의 기능 완료 후 Commit 한다.
- JavaScript만 사용한다.
- TypeScript는 사용하지 않는다.
- Vue 3 Composition API와 `script setup`만 사용한다.
- CSS Framework는 사용하지 않고 기존 CSS 기준으로 유지한다.
- 기존 컴포넌트를 우선 재사용한다.
