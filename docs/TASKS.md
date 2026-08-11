# Current Task

## Completed
- [x] 프로젝트 생성
- [x] Laravel 13 설치
- [x] 로그인
- [x] 회원가입
- [x] 비밀번호 찾기
- [x] 프로필
- [x] Auth Module
- [x] 404 페이지 정리
- [x] Shared Layout 구조 정리
- [x] Base Components 5종 구조 정리
- [x] Axios 설치
- [x] Shared API Module
- [x] AppHeader Notification UI
- [x] Dashboard Notification Test Page
- [x] Dashboard Workspace 개선
- [x] Shared Dropdown Component
- [x] Dashboard 액션 Dropdown 적용
- [x] Header 액션 Dropdown 적용
- [x] 바로가기 메뉴 아이콘 적용
- [x] Dashboard 모듈형 테스트 허브 분리
- [x] 헤더 알림 기능 연결
- [x] User Management 1차
- [x] Spatie Permission 설계 반영
- [x] Board Module 1차
- [x] File Module 기반 설계 및 Media Library 설치
- [x] Dashboard 파일관리 모듈 등록
- [x] File Module 1차 Upload UI / Manager UI
- [x] File Module 다중선택 / 이미지 미리보기 / 청크 업로드
- [x] Shared DataTable 1차 List Framework
- [x] Board 첨부파일 File Module 연결
- [x] Shared Toast UI Editor / Viewer Module
- [x] Form 2차 BaseInput 계층 재구성
- [x] Modal 공통 구조 정리
- [x] Dialog 공통 구조 정리
- [x] Dashboard 카드(Card) 모듈 등록
- [x] Dashboard 리스트(List) 모듈 등록
- [x] Dashboard 폼(Form) 모듈 등록
- [x] Dashboard 토스트(Toast) 모듈 등록
- [x] Dashboard 로딩·빈 상태(Loading/Empty) 모듈 등록
- [x] Dashboard 알림 배너(Alert) 모듈 등록
- [x] Foundation 규약 명세 문서(docs/FOUNDATION.md) 작성
- [x] STEP 2-5 API 규약 문서(docs/API.md) 작성
- [x] 비즈니스/데모 라우트 분리 (dashboard.business.* vs dashboard.modules.*)
- [x] 비즈니스 프론트엔드 분리 (resources/js/business/*, app.js 모노리스 축소)
- [x] 권한 검증 Policy 도입 (Board/Order/User Policy + 라우트 can: 미들웨어)
- [x] 대시보드 공용 컴포넌트 적용 (x-alert 로그인 안내 · Empty State 오더 카드)

## Current
- [ ] Order Module

## 원칙
- 이 문서는 현재 작업을 하나만 지정하는 기준 문서다.
- AI는 이 문서에 적힌 현재 작업만 수행한다.
- `Completed`는 다시 작업하지 않는다.
- `Current`만 작업한다.
- `Next`는 사용자의 지시가 있기 전까지 구현하지 않는다.
- 현재 작업이 완료되면 종료하고, 다음 작업은 이 문서를 갱신한 뒤 시작한다.

## Module
- Business

## Step
- STEP 2 완료 · Shared Module(Foundation + API) 마감

## Current Work
- 공통 Foundation(DataTable 2차 · Loading · Empty State · Feedback)과 API 규약(STEP 2-5)을 완료 선언하고, 다음 작업은 Order Module이다.

## Scope
- 공통 `ToastEditor` / `ToastViewer` 구조 추가 완료
- 대시보드 `Toast UI Editor 테스트` 모듈 연결 완료
- 문서형 본문 입력 재사용 규칙 문서 반영 완료

## Foundation Roadmap

### 목표
- Foundation을 먼저 완성한 뒤 `Order`, `Dispatch`, `Driver` 비즈니스 로직에 집중한다.
- `Order` 개발이 시작된 이후에는 공통 컴포넌트 수정 비용이 커지므로, 지금 단계에서 기반을 우선 마감한다.
- Foundation이 끝나면 각 비즈니스 모듈은 공통 컴포넌트를 조합하는 방식으로만 확장한다.

### Foundation
- [x] 1. DataTable 1차 List Framework
- [x] 1. DataTable 2차 정리 (정렬 · 행 선택)
- [x] 2. Form Components 1차
- [x] 2. Form Components 2차 BaseInput 계층 재구성
- [x] 3. Modal
- [x] 4. Dialog
- [x] 5. Toast
- [x] 6. Loading
- [x] 7. Empty State
- [x] 8. Search 1차
- [x] 9. Filter 1차
- [x] 10. Pagination 1차

### Foundation 완료 기준
- `DataTable`, `Form Components`, `Modal`, `Dialog`, `Toast`, `Loading`, `Empty State`, `Search`, `Filter`, `Pagination`가 공통 규칙으로 정리되어 있어야 한다.
- 비즈니스 모듈 구현 시 공통 컴포넌트를 다시 설계하지 않고 조합만으로 화면 구성이 가능해야 한다.
- 테스트용 페이지와 재사용 규칙 문서가 함께 정리되어 있어야 한다.

### Business Queue
- [ ] 11. Order
- [ ] 12. Dispatch
- [ ] 13. Driver

## 완료 기준
- 다음 작업이 지정되기 전까지 임의 구현 금지
- 현재 작업 범위 밖의 기능은 추가하지 않는다.
- 작업 완료 후 종료한다.
