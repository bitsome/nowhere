// 중앙 색상 팔레트 — 모든 화면이 이 값을 기준으로 한다.
// CSS 변수(base.css)와 값이 일치해야 하므로 변경 시 양쪽을 함께 수정한다.

// 틸 계조 스케일 — 최상단 #63e2b7을 기준으로 아래(어두운) 단계만 사용. 더 밝게는 쓰지 않는다.
export const tealScale = {
    base: '#63e2b7',   // 0단계: 주 색
    hover: '#54cf9f',  // 1단계: hover
    pressed: '#48b98c', // 2단계: pressed
    deep: '#3ca97e',   // 3단계: 알림 배너·그라디언트 끝
    deeper: '#2f8f6a', // 4단계: 심부 (필요 시)
};

// 브랜드 — 라이트=파랑, 다크=틸 (내 운행 탭 원래 선택 색)
export const brand = {
    blue: '#36adff',
    blueHover: '#5cbfff',
    bluePressed: '#2b8ee0',
    teal: tealScale.base,
    tealHover: tealScale.hover,
    tealPressed: tealScale.pressed,
    indigo: '#2f54eb',
};

// 알림 배너/점 (라이트=블루, 다크=틸 계조 3단계)
export const notify = {
    blue: '#2563eb',
    teal: tealScale.deep,
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
};

// 시맨틱 색상
export const semantic = {
    warn: '#ffa940',
    danger: '#e5484d',
    badgeRed: '#e24c4c',
};

// 라이트 테마 시맨틱 (base.css :root와 동일)
export const light = {
    bg: '#f4f4f4',
    surface: '#ffffff',
    border: '#dddddd',
    text: '#1f1f1f',
    textMuted: '#6a6a6a',
    accent: '#1f1f1f',
    danger: '#b42318',
    hover: '#f1f1f1',
};

// 다크 테마 시맨틱 (base.css html.dark와 동일)
export const dark = {
    bg: '#121216',
    surface: '#1c1c22',
    border: '#32323a',
    text: '#e5e5ea',
    textMuted: '#9aa0ac',
    accent: brand.teal,
    danger: '#d86262',
    input: '#23232b',
    hover: '#2a2a33',
    badge: '#3a3a44',
};

// 브랜드 그라디언트 (라이트=파랑→인디고, 다크=틸 계조 내림차순)
export const brandGradient = `linear-gradient(135deg, ${brand.blue}, ${brand.indigo})`;
export const tealGradient = `linear-gradient(135deg, ${tealScale.base}, ${tealScale.deep})`;

// naive-ui 테마 오버라이드 — 다크는 틸 주 색 + 서피스를 CSS 변수와 동일하게 맞춘다
export const naiveThemeOverrides = (isDark) => (isDark
    ? {
        common: {
            primaryColor: brand.teal,
            primaryColorHover: brand.tealHover,
            primaryColorPressed: brand.tealPressed,
            primaryColorSuppl: brand.teal,
            bodyColor: dark.bg,
            cardColor: dark.surface,
            modalColor: dark.surface,
            popoverColor: dark.surface,
            tableColor: dark.surface,
            inputColor: dark.input,
            borderColor: dark.border,
            dividerColor: dark.border,
        },
    }
    : null);
