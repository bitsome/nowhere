# DASHBOARD

## 목적
- 대시보드를 기능 테스트의 단일 허브로 사용하되, 실제 테스트 UI는 모듈별 개별 페이지로 분리하는 규칙을 정의한다.
- 앞으로 추가되는 기능이 한 화면에 누적되어 대시보드가 비대해지는 문제를 방지한다.
- 운영자와 AI가 동일한 방식으로 테스트 페이지를 확장할 수 있게 한다.

## 핵심 원칙
- 대시보드는 `링크 허브` 역할만 수행한다.
- 기능 테스트 UI를 대시보드 메인 페이지에 직접 누적하지 않는다.
- 하나의 기능은 하나의 모듈 페이지에서 테스트한다.
- 새 기능을 추가할 때는 반드시 허브 링크와 개별 테스트 페이지를 함께 만든다.
- 현재 작업과 관련 없는 모듈 페이지는 임의로 수정하지 않는다.

## 구조 규칙
- 허브 경로: `/dashboard`
- 모듈 경로: `/dashboard/modules/{module}`
- 예시
  - `/dashboard/modules/notification`
  - `/dashboard/modules/files`
  - `/dashboard/modules/dropdown`
  - `/dashboard/modules/datatable`
  - `/dashboard/modules/editor`
  - `/dashboard/modules/users`
  - `/dashboard/modules/boards`
  - `/dashboard/modules/order`
  - `/dashboard/modules/dispatch`

## 페이지 역할

### 대시보드 허브
- 현재 테스트 가능한 모듈 목록을 보여준다.
- 각 모듈의 상태와 설명을 보여준다.
- 각 모듈 페이지로 이동하는 링크만 제공한다.
- 실제 입력 폼, 테스트 로그, 시뮬레이션 UI는 두지 않는다.

### 모듈 페이지
- 특정 기능 하나만 테스트한다.
- 테스트에 필요한 입력, 상태 카드, 로그, 샘플 버튼을 포함할 수 있다.
- 다른 기능의 테스트 UI를 혼합하지 않는다.
- 모듈 제목, 설명, 현재 상태를 명확히 보여준다.

## 추가 규칙
- 새 기능 테스트가 필요하면 먼저 모듈명을 정한다.
- 라우트 이름은 `dashboard.modules.{module}` 패턴을 따른다.
- 대시보드 허브에는 새 모듈 카드와 링크를 추가한다.
- 모듈별 테스트 UI는 `resources/views/dashboard/modules/` 하위에 만든다.
- 공통 구조가 필요하면 재사용 가능한 레이아웃 또는 partial로 분리한다.

## 문서 갱신 규칙
- 새 모듈을 추가하면 `docs/TASKS.md`를 먼저 갱신한다.
- 구조 규칙이 바뀌면 이 문서(`docs/DASHBOARD.md`)를 갱신한다.
- 대시보드 운영 규칙 변경 시 `docs/RULES.md`에도 핵심 기준을 반영한다.
- 큰 구조 변경은 `docs/CHANGELOG.md`에 기록한다.

## AI 규칙
- AI는 새 기능 테스트 UI를 대시보드 허브 본문에 직접 추가하지 않는다.
- AI는 기능별 테스트 페이지를 먼저 만들고, 이후 허브 링크를 연결한다.
- AI는 하나의 모듈 페이지에 여러 기능을 섞지 않는다.
- AI는 모듈 추가 시 허브, 라우트, 문서를 함께 갱신한다.
- **AI는 새 공통 컴포넌트를 만들면 반드시 대시보드에 미리보기 페이지를 등록한다.** 모든 variant·상태를 한눈에 확인할 수 있어야 한다.
