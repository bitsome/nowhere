# Changelog

## Version
- `0.1.0`
- 날짜: `2026-08-01`
- 상태: 초기 문서화 및 인증/프론트엔드 기반 구성

## Added
- 로그인/로그아웃 기능 추가
- 랜딩 페이지, 로그인 페이지, 대시보드 UI 추가
- Blade 공용 레이아웃 컴포넌트 추가
- `docs/` 문서 구조 추가
- `ARCHITECTURE.md`, `DISPATCH.md`, `BUSINESS.md` 문서 추가
- User Management 1차 모듈 추가
- Board Module 1차 모듈 추가
- `spatie/laravel-medialibrary` 패키지 추가
- `media` 테이블 마이그레이션과 공통 `FileService` 골격 추가
- 공통 `Toast UI Editor / Viewer` 모듈 추가
- `Tailwind CSS` 및 관련 Vite 플러그인 제거

## Changed
- 기본 Laravel 웰컴 페이지를 서비스형 랜딩 페이지로 변경
- 로그인 및 대시보드 화면을 공통 디자인 시스템 기준으로 정리
- 문서 구조를 운영/비즈니스 규칙 중심으로 확장
- 대시보드를 통합 테스트 화면에서 모듈 링크 허브 구조로 변경
- 기능 테스트를 `/dashboard/modules/{module}` 개별 페이지 구조로 분리
- `DASHBOARD.md` 문서를 추가하여 대시보드 운영 규칙을 별도로 정의
- 사용자 데이터 구조에 Role, Permission, Status, 마지막 로그인, 로그인 횟수 필드를 확장
- Spatie 기준 권한 구조, 메뉴 권한 분리, Super Admin 단일 계정 규칙 문서화
- 권한 위임 제한 규칙(동등/상위 권한 부여 금지, Admin 하위 권한만 위임) 문서화
- 게시판 모듈을 `type` 기반 공통 구조로 확장하고 `notice`, `free`, `qna` 구분 흐름을 도입
- 파일 업로드 기능은 도메인별 개별 구현이 아니라 공통 `File Module` 재사용 원칙으로 문서화
- 파일 업로드 엔진은 `spatie/laravel-medialibrary`, NoWhere 업무형 파일 UI는 공통 `File Module` 기준으로 정리
- 대시보드 허브에 `파일관리` 모듈 링크와 `/dashboard/modules/files` 진입 페이지를 추가
- 파일관리 모듈에 Upload UI와 Manager UI를 추가하고 업로드/다운로드/삭제 흐름을 연결
- 파일관리 업로드를 다중선택과 이미지 미리보기, 청크 업로드 방식으로 확장
- 공통 `shared/components/DataTable/` 구조를 추가하고 List Framework 기준의 `Shared DataTable` 1차를 구현
- 대시보드 허브에 `Shared DataTable 테스트` 모듈 링크와 `/dashboard/modules/datatable` 페이지를 추가
- 게시판 등록/수정 화면에 공통 `File Module` 기준 첨부 업로드를 연결하고 상세 페이지에 첨부 목록/다운로드를 추가
- 대시보드 허브에 `Toast UI Editor 테스트` 모듈 링크와 `/dashboard/modules/editor` 페이지를 추가
- 문서형 입력/조회는 공통 `ToastEditor` / `ToastViewer` 재사용 원칙으로 문서화
- 공통 CSS 엔트리와 DataTable CSS를 plain CSS 기준으로 재구성하고 외부 CSS 라이브러리 미사용 규칙 반영

## Fixed
- `layouts.app` Blade 컴포넌트 경로 오류 수정
- `x-layouts.app` 호출 시 발생하던 컴포넌트 해석 오류 수정

## Removed
- 기본 Laravel 시작 화면 의존성 제거

## Deprecated
- 현재 기준 없음

---

## 0.2.0 — NoWhere 앱 리뉴얼 (2026-08-11)

SPA 프론트엔드(마켓·내 오더·채팅·커뮤니티)와 API 기반 오더/리뷰 시스템 구축 이후의 주요 변경.

### Added
- **평점·리뷰 시스템**: `reviews` 테이블, `POST /orders/{id}/review`, 유저 프로필의 `reviewSummary`·`reviews`·`stats`(완료 오더·매출)
- **원 등록자 기록**: `orders.original_owner_id` — claim 시 등록자 보존 → 상호 리뷰 대상 식별
- **오더 등록 화면 재설계**: 내 오더 목록(받은/등록 탭 + 운행 단계 필터 + 마켓 동일 필터 모달) + 상단 등록 버튼, 메인 헤더 적용
- **하단 네비 재구성**: "내 오더" 탭 → 오더 등록 목록 페이지, 기존 내 오더 페이지 제거
- **채팅 오더 카드**: 대화방 상단에 연결된 오더 정보(노선·날짜·금액·상태) 표시
- **마켓 등록자 신뢰 정보**: 마켓 카드에 등록자 평점·리뷰 수·완료 수 (bulk 조회, N+1 없음)
- **알림 미확인 배지**: 헤더 알림 아이콘에 unreadCount 배지
- **내 프로필 실적 카드**: 완료 오더·누적 매출·받은 평점
- **셋트 오더 상세 보강**: 그룹 총 금액 + 일정별 금액 표시
- **UI 최적화**: 마켓/오더 목록 로딩 스켈레톤, 커뮤니티 이미지 업로드 자동 리사이즈(1080px), 전역 렌더링/터치 CSS

### Changed
- 채팅 대화 목록: 대화당 **최신 메시지 1건만** 로드 (메모리/쿼리 절감)
- 오더 목록 API: 불필요한 `with('user')` 제거, owner 계산을 마켓 응답에서만 수행
- 오더 테이블에 `service_date`, `claimed_at` 성능 인덱스 추가

### Fixed
- 오더 등록 화면의 weekday 배열 누락 오류 수정 (수요일/목요일)

### Removed
- 기존 `my-orders` 라우트·`ReceivedOrdersView` 페이지 제거 (오더 등록 페이지로 통합)

## Breaking Change
- 현재 기준 없음
