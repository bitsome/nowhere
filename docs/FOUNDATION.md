# Foundation 규약 명세

> 상태: ✅ 현행 — 독립 프론트엔드(SPA, `frontend/`)의 실제 컴포넌트 기준.

공통 컴포넌트(Foundation)의 사용 규칙을 한 문서로 정리한 기준이다. 운영 UI는 전부 **Vue 3 SPA**이며, Blade 레거시 컴포넌트는 사용하지 않는다.

## 목적
- 화면마다 스타일이 흩어지는 것을 막고, 공통 UI 기준 하나로 정리한다.
- 디자인 수정은 각 컴포넌트 한 곳에서만 일어나도록 유지한다.
- 색상·타이포는 [COLORS.md](./COLORS.md), 페이지 UX 원칙은 [UI.md](./UI.md)를 참조한다.

## 공통 원칙
1. **그레이스케일 우선** — 의미 색상(정보/성공/경고/오류)은 개념상 예약만 하고, 기본은 그레이 계열로 표현한다.
2. **의미를 아이콘으로 구분** — 타입 구분은 색보다 아이콘(bell/check/close/! 등)으로 한다.
3. **컴포넌트 우선 재사용** — 수제 마크업보다 공용 컴포넌트(`Ui*`, `common/*`)를 먼저 사용한다.
4. **컨트롤 크기 통일** — 입력/버튼 기본 높이는 44px, 행 높이는 40px 내외, 모서리는 8~10px이다.
5. **접근성** — 버튼/입력/아이콘 단독 요소에는 `title`/`aria-label`을 제공한다.

## 컴포넌트 인벤토리 (`frontend/src/components`)

### 공통 (common)
| 컴포넌트 | 용도 |
|---|---|
| `BaseIcon` | 모든 아이콘의 공통 래퍼 — 아이콘은 이 컴포넌트로만 사용한다 |
| `ConfirmDialog` | 파괴적 작업 확인 다이얼로그 — `window.confirm` 대신 사용 |
| `EmptyState` | 데이터 없음 — 아이콘 + 제목 + 설명 구조 |
| `LevelBadge` / `VerifiedBadge` | 레벨·차량/면허 인증 배지 |
| `ImageGallery` | 이미지 갤러리 뷰어 |
| `ScrollTopButton` | 스크롤 최상단 이동 |

### UI 기본 (ui)
| 컴포넌트 | 용도 |
|---|---|
| `UiCard` | 카드 컨테이너 (배경 `var(--surface)`, 테두리·radius 규칙은 base.css) |
| `UiChip` | 칩/라벨 |
| `UiListRow` | 목록 행 |
| `UiSection` | 섹션 블록 (제목 + 내용) |

### 운행 (orders) · 채팅 (chat) · 레이아웃 (layout) · 커뮤니티 (community)
- `orders/`: `OrderCard`(마켓/목록 카드 — 조건%·근거 체크리스트), `OrderCardSkeleton`, `OrderDetailChat`, `SetGroupCard`(셋트 일정 카드)
- `chat/`: `ChatList`, `ChatThread`, `MessageBubble`, `ChatImageSheet`, `ChatRequestEvent`, `ChatRequestSheet`
- `layout/`: `HeaderBar`, `ChatListener`(SSE), `NotificationListener`(SSE)
- `community/`: `CommunityPostEditor`

## 상태 배지 규칙
- 배지: padding `1px 6px`, font-size `10px`, font-weight `400` (bold 제거)
- 조건% 배지: 70 이상 브랜드 / 40 이상 앰버 / 그 외 무채색

## UI 라이브러리
- **naive-ui** — `unplugin-vue-components` + `NaiveUiResolver`로 사용한 컴포넌트만 번들에 포함한다.
- 테마 오버라이드: `frontend/src/utils/colors.js` → `naiveThemeOverrides(isDark)` (App.vue에서 적용).

## API 통신 규약
- 서버 통신은 `frontend/src/api/client.js`(axios)를 사용한다. 화면 로직에서 직접 `fetch`를 호출하지 않는다.
- 상세 규칙(호출/응답/에러/인터셉터)은 [API.md](./API.md)를 기준으로 유지한다.

## 피드백 표시 선택 규칙
| 상황 | 컴포넌트 |
|---|---|
| 파괴적 작업 확인 (삭제/취소) | `ConfirmDialog` |
| 데이터 없음 | `EmptyState` |
| 짧은 상태 전달 | naive-ui `useMessage` (토스트) |
| 진행 중 표시 | naive-ui `useLoadingBar` / 스켈레톤(`OrderCardSkeleton`) |
