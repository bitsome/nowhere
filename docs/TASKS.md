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

## Current
- [ ] Modal 공통 구조 정리

## Next
- [ ] Dialog 공통 구조 정리
- [ ] Loading 공통 구조 정리
- [ ] Empty State 공통 구조 정리
- [ ] DataTable 2차 정리
- [ ] Foundation 완료 선언
- [ ] Order Module

## 원칙
- 이 문서는 현재 작업을 하나만 지정하는 기준 문서다.
- AI는 이 문서에 적힌 현재 작업만 수행한다.
- `Completed`는 다시 작업하지 않는다.
- `Current`만 작업한다.
- `Next`는 사용자의 지시가 있기 전까지 구현하지 않는다.
- 현재 작업이 완료되면 종료하고, 다음 작업은 이 문서를 갱신한 뒤 시작한다.

## Module
- Shared

## Step
- STEP 2 완료

## Current Work
- 공통 Modal 계층을 Foundation 기준으로 정리하고 재사용 가능한 오버레이 구조를 확정한다.

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
- [ ] 1. DataTable 2차 정리
- [x] 2. Form Components 1차
- [x] 2. Form Components 2차 BaseInput 계층 재구성
- [ ] 3. Modal
- [ ] 4. Dialog
- [x] 5. Toast
- [ ] 6. Loading
- [ ] 7. Empty State
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

### 작업 순서 원칙
- 현재는 `Foundation`만 작업한다.
- `Order`, `Dispatch`, `Driver`는 Foundation 완료 전까지 착수하지 않는다.
- 공통 컴포넌트 수정이 필요한 일은 Business 단계로 넘기지 않고 Foundation 단계에서 마감한다.

## Do Not
- Button 구현 금지
- Order 개발 금지
- Dispatch 개발 금지
- Driver 개발 금지
- WebSocket 구현 금지
- Redis 구현 금지
- Pusher 구현 금지
- 실시간 갱신 구현 금지
- 읽음 API 구현 금지
- 삭제 API 구현 금지
- Animation 구현 금지
- Sub Menu 구현 금지
- Keyboard Navigation 구현 금지
- Search 구현 금지
- Icon Library 구현 금지
- API 구현 금지
- Router 구현 금지
- Permission 구현 금지
- Notification 구현 금지
- User Menu 구현 금지
- 무한스크롤 구현 금지
- 페이지네이션 구현 금지
- 실제 알림 서버 구현 금지
- 회원등록 구현 금지
- 회원삭제 구현 금지
- 회원 일반정보 수정 구현 금지
- 프로필 수정 기능 구현 금지
- File Module UI 선구현 금지
- Profile 파일 연결 선구현 금지
- Order 파일 연결 선구현 금지
- Driver 파일 연결 선구현 금지
- Drag & Drop 구현 금지
- Multiple Upload 구현 금지
- Progress 구현 금지
- Folder 구현 금지
- Crop/Compress/PDF/Video Preview 구현 금지
- 조직도 구현 금지
- 부서관리 구현 금지
- 직급관리 구현 금지
- 새로운 기능 자체 선구현 금지
- Spatie 설치 전 권한 로직 전면 교체 금지
- 임시 users.role/users.permissions 즉시 제거 금지
- 대시보드와 무관한 기존 인증 흐름 수정 금지
- 현재 작업과 무관한 페이지 수정 금지
- 현재 작업과 무관한 Route 수정 금지
- 현재 작업과 무관한 Store 수정 금지
- Board 1차 범위(목록/상세/등록/수정/삭제/검색) 외 기능 선구현 금지
- 댓글 기능 선구현 금지
- FAQ 기능 선구현 금지
- 좋아요/신고/북마크 기능 선구현 금지
- 공지 상단 고정 기능 선구현 금지
- 실시간 게시판 알림 기능 선구현 금지

## 완료 기준
- 다음 작업이 지정되기 전까지 임의 구현 금지
- 현재 작업 범위 밖의 기능은 추가하지 않는다.
- 작업 완료 후 종료한다.
