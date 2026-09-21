// 중앙 색상 팔레트 — 화면이 직접 쓰는 색은 base.css 의 CSS 변수다.
// 이 파일은 JS 로 값을 넘겨야 하는 두 곳만 맡는다: naive-ui 테마와 상태 색상.
// 값이 바뀌면 base.css 와 함께 수정한다.

// 다크 테마 주 색(틸) — base.css html.dark 의 --brand 와 같은 값.
// 최상단 #63e2b7 을 기준으로 아래(어두운) 단계만 쓴다.
const DARK_BRAND = {
    base: '#63e2b7',
    hover: '#54cf9f',
    pressed: '#48b98c',
};

// 다크 테마 서피스 — base.css html.dark 의 배경·테두리와 같은 값
const DARK_SURFACE = {
    bg: '#121216',
    surface: '#1c1c22',
    border: '#32323a',
    input: '#23232b',
};

// 상태 색상 — CSS 변수(var(--status-*))로 참조해 테마별로 자동 적용
export const statusColorVar = {
    draft: 'var(--status-draft)',
    published: 'var(--status-published)',
    trading: 'var(--status-trading)',
    accepted: 'var(--status-accepted)',
    driving: 'var(--status-driving)',
    completed: 'var(--status-completed)',
    settled: 'var(--status-settled)',
    cancelled: 'var(--status-cancelled)',
    acceptance_pending: 'var(--status-acceptance-pending)',
};

// naive-ui 테마 오버라이드 — 다크는 틸 주 색 + 서피스를 CSS 변수와 동일하게 맞춘다.
// 라이트는 base.css 가 전부 관리하므로 null 을 주고 naive-ui 기본 테마를 쓴다.
export const naiveThemeOverrides = (isDark) => (isDark
    ? {
        common: {
            primaryColor: DARK_BRAND.base,
            primaryColorHover: DARK_BRAND.hover,
            primaryColorPressed: DARK_BRAND.pressed,
            primaryColorSuppl: DARK_BRAND.base,
            bodyColor: DARK_SURFACE.bg,
            cardColor: DARK_SURFACE.surface,
            modalColor: DARK_SURFACE.surface,
            popoverColor: DARK_SURFACE.surface,
            tableColor: DARK_SURFACE.surface,
            inputColor: DARK_SURFACE.input,
            borderColor: DARK_SURFACE.border,
            dividerColor: DARK_SURFACE.border,
        },
    }
    : null);
