# 색상 체계 (COLORS)

프런트엔드에서 사용하는 모든 색을 **토큰**으로 중앙 관리한다. 색은 `base.css`의 CSS 변수와 `utils/colors.js`의 JS 팔레트 두 곳에서 정의하며, **값을 바꿀 때는 두 곳을 함께 수정**해야 한다.

- CSS 변수 정의: `frontend/src/assets/base.css` (:root / html.dark)
- JS 팔레트 정의: `frontend/src/utils/colors.js`
- naive-ui 테마 오버라이드: `frontend/src/utils/colors.js` → `naiveThemeOverrides(isDark)` (App.vue에서 적용)

## 1. 브랜드 색상

테마별로 액센트가 다르다. **라이트=파랑**, **다크=틸(내 운행 탭의 원래 선택 색)**. 다크 틸은 더 밝은 색을 쓰지 않고 **아래(어두운) 계조만** 사용한다.

| 토큰 | 라이트 | 다크 | 용도 |
| --- | --- | --- | --- |
| `--brand` | `#36adff` | `#63e2b7` | 선택 탭, 링크, 칩, 활성 상태, 진행 바 |
| `--brand-hover` | `#5cbfff` | `#54cf9f` | hover 상태 (다크: 주색보다 어두움) |
| `--brand-pressed` | `#2b8ee0` | `#48b98c` | pressed 상태 (다크: 주색보다 어두움) |
| `--brand-soft` | `rgba(54,173,255,.1)` | `rgba(99,226,183,.1)` | 활성 배경(연한 틴트) |
| `--brand-gradient` | `linear-gradient(135deg,#36adff,#2f54eb)` | `linear-gradient(135deg,#63e2b7,#3ca97e)` | 아바타·히어로 그라디언트 (어두운 방향) |
| `--notify` | `#2563eb` | `#3ca97e` | 알림 배너·점 |

### 틸 계조 스케일 (다크) — `tealScale` (utils/colors.js)

최상단 `#63e2b7`을 넘어 **더 밝게는 쓰지 않는다.** 용도는 각 단계에 고정한다.

| 단계 | 값 | 용도 |
| --- | --- | --- |
| 0 `base` | `#63e2b7` | 주 색 (primary, 액센트) |
| 1 `hover` | `#54cf9f` | hover |
| 2 `pressed` | `#48b98c` | pressed |
| 3 `deep` | `#3ca97e` | 알림 배너·그라디언트 끝 |
| 4 `deeper` | `#2f8f6a` | 심부(필요 시) |

## 2. 상태 색상 (운행 상태)

JS 쪽은 `statusColorVar`(`utils/colors.js`)로 `var(--status-*)`를 참조하므로 **테마를 자동으로 따른다**. 다크 모드에서는 배지 톤을 낮춘 값이 적용된다.

| 토큰 | 라이트 | 다크 (톤 다운) | 상태 |
| --- | --- | --- | --- |
| `--status-draft` | `#909399` | `#7d8189` | 임시저장 |
| `--status-published` | `#36adff` | `#63e2b7` (틸) | 공개 중 |
| `--status-trading` | `#ffa940` | `#c98f3e` | 거래중 |
| `--status-accepted` | `#2f54eb` | `#5b6fc9` | 수락됨 |
| `--status-driving` | `#13c2c2` | `#1c9f9f` | 운행중 |
| `--status-completed` | `#18a058` | `#27946a` | 완료 |
| `--status-settled` | `#722ed1` | `#7a5bc0` | 정산 완료 |
| `--status-cancelled` | `#e5484d` | `#d2555a` | 취소 |

## 3. 시맨틱 색상

| 토큰 | 라이트 | 다크 (톤 다운) | 용도 |
| --- | --- | --- | --- |
| `--danger` | `#b42318` | `#d86262` | 오류·삭제 |
| `--warn` | `#ffa940` | `#c98f3e` | 경고·내일 배지·별점 |
| `--badge-red` | `#e24c4c` | `#cf5656` | 알림 배지·미확인 카운트 |

## 4. 서피스·텍스트

| 토큰 | 라이트 | 다크 | 용도 |
| --- | --- | --- | --- |
| `--bg` | `#f4f4f4` | `#121216` | 페이지 배경 |
| `--surface` | `#ffffff` | `#1c1c22` | 카드·시트 |
| `--border` | `#dddddd` | `#32323a` | 보더·구분선 |
| `--text` | `#1f1f1f` | `#e5e5ea` | 본문 텍스트 |
| `--text-muted` | `#6a6a6a` | `#9aa0ac` | 보조 텍스트 |
| `--accent` | `#1f1f1f` | `#63e2b7` | 어두운 표면용 액센트 |
| `--danger` | `#b42318` | `#d86262` | 위험 동작 |

naive-ui 다크 서피스는 `darkOverrides`(colors.js의 `naiveThemeOverrides`)로 위 값과 동일하게 맞춘다
(`bodyColor`/`cardColor`/`modalColor`/`inputColor`/`borderColor`).

## 5. 사용법

### CSS에서
```css
.active-chip {
    color: var(--brand);
    background: var(--brand-soft);
    border: 1px solid color-mix(in srgb, var(--brand) 35%, transparent);
}
```

반투명 색은 `color-mix(in srgb, var(--brand) 10%, transparent)`처럼 쓴다.

### JS에서 상태 색 참조 (테마 자동)
```js
import { statusColorVar } from '../utils/colors';
const color = statusColorVar[status] ?? 'var(--status-draft)';
```
`statusColorVar`는 CSS 변수 문자열을 반환하므로 인라인 스타일에서도 테마에 따라 자동 적용된다.

### naive-ui 주 색 통일
```js
import { naiveThemeOverrides } from '../utils/colors';
// <n-config-provider :theme-overrides="naiveThemeOverrides(theme.isDark)">
```

## 6. 색 변경 가이드

1. `base.css`의 `:root`(라이트)와 `html.dark`(다크) 토큰 값을 수정.
2. 같은 값을 `utils/colors.js`의 `brand`/`light`/`dark`/`naiveThemeOverrides`에 반영.
3. 컴포넌트에 하드코딩된 색은 두지 않는다 — 반드시 `var(--...)`로 참조.

## 7. 의도적으로 남겨둔 색

- **레벨 티어 그라디언트** (`data/levels.js`, `LevelBadge.vue`): 신입/베테랑/스타/마스터/레전드 구간별 고유 색. 브랜드와 무관한 의미색이므로 토큰화하지 않는다.
