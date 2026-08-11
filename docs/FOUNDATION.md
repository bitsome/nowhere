# Foundation 규약 명세

공통 컴포넌트(Foundation)의 사용 규칙을 한 문서로 정리한 기준이다.
완료 선언 기준(STEP 2-3 · 2-4)으로 현재 프로젝트에서 재사용되는 컴포넌트만 다룬다.

## 목적

- 화면마다 스타일이 흩어지는 것을 막고, 공통 UI 기준 하나로 정리한다.
- 디자인 수정은 각 컴포넌트 한 곳에서만 일어나도록 유지한다.
- Blade와 Vue가 같은 시각 규칙을 공유한다.

## 공통 원칙

1. **그레이스케일 우선** — 의미 색상(정보/성공/경고/오류)은 개념상 예약만 하고, 지정 전까지 그레이 계열로 표현한다. 색상 지정 시 해당 컴포넌트 CSS 한 곳에서만 수정한다.
2. **의미를 아이콘으로 구분** — 타입 구분은 색보다 아이콘(bell/check/close/! 등)으로 한다.
3. **컴포넌트 우선 재사용** — 수제 마크업보다 공용 컴포넌트(`x-*`, `Base*`, `DataTable`, `Dropdown*`)를 먼저 사용한다.
4. **컨트롤 크기 통일** — 입력/버튼 기본 높이는 44px(2.75rem), 행 높이는 40px 내외, 모서리는 8~10px이다.
5. **접근성** — 버튼/입력/아이콘 단독 요소에는 `title`/`aria-label`을 제공한다.

## 컴포넌트 규약

### 버튼
- **Vue**: `BaseButton` — `variant`(primary / secondary / ghost / danger) × `size`(sm 36px / md 44px / lg 48px), `disabled`, `href`(링크 버튼).
- **Blade**: `btn-primary` / `btn-secondary`(44px), `icon-button`(아이콘 단독), `action-button--primary/secondary`.
- **상태**: disabled는 회색 배경/텍스트로, loading은 스피너 아이콘과 함께 표현하고 입력을 차단한다.
- **아이콘 단독**: 의미가 명확한 공통 액션(검색/새로고침/다운로드/추가)만 사용하며 title/aria-label 필수.

### 입력 폼
- **Vue**: `BaseInput` / `BaseSelect` / `BaseCheckbox` / `BaseTextarea` + `FormGroup`(라벨/필수/설명/오류를 묶는 구조, `form-framework__*`).
- **Blade**: `input-field`(텍스트/셀렉트 공용), `input-field--compact`(날짜/시간/요일 — 내용 크기에 맞는 폭 + 수직 중앙).
- **구조**: 라벨은 입력 위, 필수는 라벨 옆 `필수` 배지, 오류는 입력 아래. 입력에는 placeholder와 title을 함께 제공한다.
- **날짜/시간/요일**: 네이티브 `type="date"`/`type="time"`과 셀렉트를 `input-field` 스타일로 통일한다.

### 카드
- **Vue**: `BaseCard`(DataTable/FileManager 내부에서 사용).
- **Blade**: `surface-card`(기본 카드), `surface-card--raised`(허브/요약), `summary-card`(요약 수치).
- **용도**: 단독 정보 블록이 아니라 "목록/요약을 담는 프레임"으로 사용한다.

### 배지
- **Vue**: `BaseBadge` — `variant`(strong / default).
- **Blade**: `meta-badge`(상태/속성 라벨), `rounded-full` 계열 인라인 배지.
- **용도**: 상태·속성·유형 표시에 사용. 의미 색상 지정 전까지 강약(bg 강/약)으로만 구분한다.

### 로딩
- `BaseLoading` — `inline`(영역 안 스피너+라벨) / 기본(전체 화면 반투명 오버레이).
- **사용처**: DataTable 로딩(`TableLoading`), 버튼 로딩(스피너 + "처리 중..."), 전체 화면 로딩.
- 라벨은 `label` prop으로 지정하고, 기본값 `Loading...`을 유지한다.

### 빈 상태
- `TableEmpty` / `datatable-empty` — 아이콘 + 제목 + 설명 구조.
- **사용처**: 목록/테이블 데이터가 없을 때. 목록 외 영역도 같은 구조를 따른다.
- 기본 문구: "데이터가 없습니다." / "조건에 맞는 데이터가 없습니다."

### 토스트
- `ToastContainer` + `ToastItem` — `type`(info / success / error), 자동 닫힘(기본 3.2초), 닫기 버튼.
- **사용처**: 짧은 상태 전달(완료/오류) — 오더 AI 구조화 등 AJAX 작업 결과.
- **연결**: `createToastBridge(target)`로 페이지에 마운트하고 `pushToast({ type, title, message })`로 발송한다.

### 알림 배너
- `x-alert` — `variant`(info / success / warning / error), `title`/`message`/슬롯, `dismissible`(닫기).
- **사용처**: 화면 흐름 안에 남아야 하는 안내/경고/오류 — 폼 검증 오류(오더 등록/수정) 등.
- 닫기 동작은 `data-alert-close`로 처리한다.

### 다이얼로그
- `BaseDialog`(Vue) / `x-dialog`(Blade) — `size`(sm 420px / md 540px), `variant`(confirm / danger), `show-cancel`, `close-on-backdrop`.
- **사용처**: 파괴적 작업 확인(삭제/취소). 확인 문구와 취소/확인 푸터를 제공한다.
- 열기/닫기는 `data-dialog-open` / `data-dialog-close` / `data-dialog-confirm`로 제어한다.

### 모달
- `BaseModal`(Vue) / `x-modal`(Blade) — `size`(sm/md/lg/xl), `close-on-backdrop`, 헤더/본문/푸터 구조.
- **사용처**: 본문이 긴 상세/편집(회원 상세 등). 헤더·푸터는 고정하고 본문만 스크롤한다.
- ESC/배경 클릭 닫기는 `closeOnBackdrop`으로 제어한다.

### 드롭다운
- `Dropdown` + `DropdownMenu` + `DropdownHeader` + `DropdownDivider` + `DropdownItem`.
- **트리거**: 클릭 기반(호버 미지원). 아이콘/버튼/텍스트 트리거 모두 가능.
- **포지션**: `align`(left / right, 기본 right), `width` prop(기본 240px).
- **구조**: 헤더(제목/설명) + 구분선 + 아이템(아이콘+텍스트, danger 변형).
- **공용 메뉴**: `DropdownActions`(데이터 주도), `DropdownTableActions`(테이블 행 관리).

### 데이터 테이블
- `DataTable` — 컬럼 정의(`key`/`label`/`align`/`width`/`sortable`)와 데이터만 제공하는 컨트롤 프레임워크.
- **기능**: 검색(`searchValue`), 필터(`filterValue`), **정렬**(`sortKey`+`sortDirection`, asc/desc/해제), **행 선택**(`selectable`+`selectedKeys`, 전체/부분), 페이지네이션, 로딩(`TableLoading`), 빈 상태(`TableEmpty`).
- **커스텀**: `cell-{key}` / `header-{key}` / `toolbar-*` 슬롯으로 셀·툴바를 확장한다.
- **기준**: 목록 화면은 공용 DataTable을 사용하고, 각 모듈은 컬럼·데이터·셀 슬롯만 정의한다.

## 피드백 표시 선택 규칙

| 상황 | 컴포넌트 |
|---|---|
| 짧은 상태 전달(완료/오류, 자동 닫힘) | 토스트 |
| 화면 안에 남아야 하는 안내/경고/오류 | 알림 배너(`x-alert`) |
| 파괴적 작업 확인 | 다이얼로그 |
| 본문이 긴 상세/편집 | 모달 |
| 진행 중 표시 | 로딩(`BaseLoading`) |
| 데이터 없음 | 빈 상태(`datatable-empty`) |

## API 통신 규약

- 서버 통신은 `resources/js/shared/api/` 헬퍼를 사용한다. 화면 로직에서 직접 `fetch`/`axios`를 호출하지 않는다.
- 상세 규칙(호출/응답/에러/인터셉터)은 `docs/API.md`를 기준으로 유지한다.

## 실화면 적용 현황

| 컴포넌트 | 적용처 |
|---|---|
| DataTable · 드롭다운 · 다이얼로그 · 모달 | 회원, 오더, 게시판, 파일 |
| 토스트 · 로딩 | 오더 AI 구조화(성공/실패/진행 중) |
| 알림 배너 | 오더 등록/수정 폼 검증 오류 |
| 빈 상태 | 게시판 빈 목록 |
