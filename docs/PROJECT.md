# PROJECT

## 개요
- 프로젝트명: `nowhere`
- 서비스 성격: 배차 관리 시스템 `NoWhere`
- 스택: Laravel 13, PHP 8.3+, Vue 3, JavaScript (ES6), Vite, Axios, Plain CSS
- 현재 기본 DB 연결: `sqlite`
- 목적: Foundation과 Business Foundation을 먼저 완성한 뒤 `Order`, `Dispatch`, `Settlement` 비즈니스 모듈로 확장 가능한 운영 시스템 구축

## 프로젝트 목적
- NoWhere는 단순한 CRUD 앱이 아니라 배차 업무를 위한 운영 시스템을 목표로 한다.
- 핵심은 예쁜 UI보다 빠른 판단, 빠른 입력, 일관된 업무 처리다.
- 도메인 모듈을 바로 만드는 방식이 아니라 공통 Foundation을 먼저 정리한 뒤 비즈니스 모듈을 조합하는 구조를 기본 전략으로 삼는다.

## 서비스 소개
- NoWhere는 인증, 사용자, 게시판, 파일관리, 문서형 에디터, 공통 DataTable/Form/UI 컴포넌트를 기반으로 확장되는 배차 운영 플랫폼이다.
- 현재는 공통 Foundation과 일부 대시보드 테스트 모듈이 먼저 구축되어 있다.
- 장기적으로는 `Customer`, `Company`, `Vehicle`, `Driver`, `Common Code`를 선행 구축한 뒤 `Order`, `Dispatch`, `Settlement`로 확장한다.

## 대상 사용자
- 운영자
- 관리자
- 고객지원 담당자
- 기사 관리 담당자
- 향후 기사/업체 연동 사용자

## 개발 목표
- 재사용 가능한 Foundation 완성
- 공통 UI와 공통 데이터 구조 우선 정리
- 비즈니스 모듈 개발 시 공통 컴포넌트 수정 최소화
- 문서 중심 워크플로우 유지
- 작은 단위의 기능별 커밋과 테스트 기반 개발 유지

## 핵심 기능
- 인증: 로그인, 회원가입, 비밀번호 재설정, 프로필
- 사용자 관리: 목록, 상세, 권한/상태 기반 구조
- 게시판: 목록, 등록, 수정, 상세, 첨부파일
- 파일관리: 업로드, 라이브러리, 이미지 선택, 삭제
- 문서형 입력: Toast UI Editor / Viewer 기반 Markdown 처리
- 공통 UI: DataTable, Dropdown, Form, Toast, Icon, Loading
- 대시보드 테스트 허브: Notification, DataTable, Editor, File Manager 등 공통 모듈 테스트

## 서비스 플로우
- Foundation
  - `Auth`
  - `User`
  - `Role`
  - `Permission`
  - `Board`
  - `File Manager`
  - `Markdown Editor`
  - `DataTable`
  - `Form`
  - `Modal`
  - `Dialog`
  - `Toast`
- Business Foundation
  - `Customer`
  - `Company`
  - `Vehicle`
  - `Driver`
  - `Common Code`
- Business
  - `Order`
  - `Dispatch`
  - `Settlement`

## 프로젝트 구조
- 현재 프로젝트는 Laravel 백엔드 + Blade + Vue 공통 모듈 구조를 함께 사용한다.
- 서버 렌더링 화면은 Blade에서 구성하고, 상호작용이 필요한 공통 모듈은 `resources/js/shared/` 아래에서 관리한다.
- 프로젝트의 핵심은 도메인 화면마다 새 UI를 만들지 않고 `shared/components/`와 `shared/layouts/`를 재사용하는 것이다.
- 앞으로 상위 구조는 `Application > Core / Shared / Modules` 기준으로 정리한다.
- `Core`는 프로젝트 실행에 필요한 핵심 기능, `Shared`는 전체 재사용 기능, `Modules`는 실제 업무 기능을 담당한다.
- 각 업무 모듈은 내부에서 `Models`와 `UI` 계층을 분리한다.

## 현재 상태
- 로그인/로그아웃 기능 구현
- 랜딩 페이지 및 대시보드 UI 구성
- 기본 사용자 모델 및 세션 기반 인증 사용
- `docs/` 기반 설계 문서 구조 구성
- 공통 `DataTable`, `Form`, `Toast UI Editor / Viewer`, `Toast`, `Dropdown` 구조 정리
- `Board`, `File Manager`, `Notification` 테스트 및 공통 모듈 연동 반영

## 기술 스택

### Backend
- Laravel 13
- PHP 8.3+
- SQLite (현재 기본)
- MySQL 8 (확장 가능)
- Redis (2차)
- Spatie Media Library
- Spatie Permission (설계 반영 단계)

### Frontend
- Vue 3
- JavaScript (ES6)
- Vite
- Axios
- CSS (직접 작성)
- Blade + Vue 혼합 구조

### Server
- Ubuntu
- Nginx

### Version Control
- Git
- GitHub

## 개발 규칙
- ✓ JavaScript만 사용
- ✓ 기존 CSS만 사용
- ✓ 컴포넌트 재사용
- ✓ 기능별 모듈화
- ✓ 하나의 기능 완료 후 Commit
- ✓ 하나의 기능만 개발
- ✓ Laravel 13 기준
- ✓ Vue3 Composition API (`script setup`)
- ✓ CSS Framework 사용 금지
- ✓ TypeScript 사용 금지
- ✓ 공통 모듈 우선
- ✓ 파일 업로드는 공통 `File Module` 우선
- ✓ 문서형 입력은 공통 `ToastEditor` 우선
- ✓ 폼 입력은 공통 `Form` / `Base*` 계층 우선

## 폴더 구조

### 루트
- `app/`: 컨트롤러, 모델, 요청 객체, 서비스
- `bootstrap/`: 앱 부트스트랩과 캐시
- `config/`: Laravel 설정 파일
- `database/`: 마이그레이션, 팩토리, 시더
- `docs/`: 설계/규칙/로드맵 문서
- `public/`: 퍼블릭 엔트리와 빌드 산출물
- `resources/`: CSS, Vue/JS, Blade 화면
- `routes/`: 웹/콘솔 라우트
- `storage/`: 로그, 캐시, 세션, 파일 저장
- `tests/`: Pest Feature/Unit 테스트

### app
- `app/Http/Controllers/`: 인증, 대시보드, 사용자, 게시판, 파일관리 컨트롤러
- `app/Http/Requests/`: 인증 및 프로필 요청 검증
- `app/Models/`: `User`, `Board`
- `app/Notifications/`: 비밀번호 재설정 알림
- `app/Services/`: `FileService`

### resources/js
- `resources/js/app.js`: 프런트엔드 엔트리
- `resources/js/shared/api/`: Axios 공통 API 계층
- `resources/js/shared/components/`: 재사용 UI 컴포넌트
- `resources/js/shared/layouts/`: App/Auth/Blank 레이아웃
- `resources/js/shared/notifications/`: Notification store

### resources/js/shared/components
- `Badge/`: 배지
- `Button/`: 공통 버튼
- `Card/`: 공통 카드
- `DataTable/`: 목록 프레임워크
- `Dropdown/`: 드롭다운 계층
- `Form/`: `Form`, `FormGroup`, `BaseInput`, `BaseTextarea`, `BaseSelect`, `BaseCheckbox`, `BaseMarkdownEditor`
- `Icon/`: 공통 아이콘 래퍼
- `Loading/`: `BaseLoading`
- `Toast/`: 공통 Toast
- `ToastEditor/`: Editor, Viewer, FileManagerModal

### 프런트 계층 원칙
- `Core`: 프로젝트 실행에 필요한 핵심 기능
- `Shared`: 전체 재사용 Foundation
- `Modules`: 실제 업무 기능
- 각 업무 모듈은 내부에서 `Models`와 `UI`를 분리한다.
- 모듈의 `Models`에는 UI를 넣지 않는다.
- 모듈의 `UI`는 `Shared` 공통 컴포넌트를 조합해서 만든다.
- NoWhere 비즈니스 UI는 독립적인 디자인과 스타일 레이어를 가질 수 있다.

### resources/views
- `auth/`: 인증 화면
- `components/layouts/`: Blade 레이아웃
- `dashboard/modules/`: 대시보드 모듈형 테스트/업무 화면
- `errors/`: 에러 페이지

### docs
- `RULES.md`: AI/개발 공통 규칙
- `UI.md`: UI 규칙
- `TASKS.md`: 현재 작업과 Foundation 로드맵
- `BUSINESS.md`: 비즈니스 모델
- `DATABASE.md`: 데이터베이스 구조
- `ARCHITECTURE.md`, `ROADMAP.md`, `DASHBOARD.md` 등 보조 설계 문서

## 주요 디렉터리
- `app/`: 컨트롤러, 모델, 요청 객체
- `resources/views/`: Blade 화면
- `resources/js/shared/`: 공통 프런트 구조
- `routes/`: 웹 라우트
- `database/`: 마이그레이션, 팩토리, 시더
- `tests/`: Pest 테스트
- `docs/`: 설계 기준 문서

## 향후 계획
- `Modal`, `Dialog`, `Loading`, `Empty State` Foundation 정리
- `Customer`, `Company`, `Vehicle`, `Driver`, `Common Code` 선행 구축
- `Order`를 참조 데이터 선택 중심으로 설계
- 이후 `Dispatch`, `Settlement` 단계로 확장
