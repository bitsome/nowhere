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

## Breaking Change
- 현재 기준 없음
