<script setup>
/**
 * UiChip — 작은 라벨/태그 칩.
 * 상황별 색상을 variant로 통일한다 (green/yellow/red/blue/purple/teal/default).
 */
defineProps({
    variant: { type: String, default: 'default' },
});
</script>

<template>
    <span class="ui-chip" :class="`ui-chip--${variant}`"><slot /></span>
</template>

<style scoped>
.ui-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 7px;
    border-radius: 7px;
    background: #edf0f2;
    border: 1px solid #dfe2e5;
    font-size: 9px;
    font-weight: 700;
    color: var(--text-muted);
    white-space: nowrap;
}
html.dark .ui-chip {
    background: #252b2f;
    border: 1px solid #3a4146;
}
.ui-chip--green {
    color: #0d8a63;
    background: rgba(14, 157, 108, 0.1);
    border-color: rgba(14, 157, 108, 0.28);
}
.ui-chip--yellow {
    color: #9a6a00;
    background: rgba(217, 141, 0, 0.1);
    border-color: rgba(217, 141, 0, 0.32);
}
.ui-chip--red {
    color: #c0393e;
    background: rgba(224, 91, 91, 0.1);
    border-color: rgba(224, 91, 91, 0.32);
}
.ui-chip--blue {
    color: #1d5fd0;
    background: rgba(77, 143, 232, 0.1);
    border-color: rgba(77, 143, 232, 0.3);
}
.ui-chip--purple {
    color: #6b46d6;
    background: rgba(139, 120, 232, 0.1);
    border-color: rgba(139, 120, 232, 0.3);
}
.ui-chip--teal {
    color: #0e9d8d;
    background: rgba(45, 212, 191, 0.1);
    border-color: rgba(45, 212, 191, 0.3);
}
html.dark .ui-chip--green {
    color: #70e8be;
    background: rgba(99, 226, 183, 0.08);
    border-color: rgba(99, 226, 183, 0.28);
}
html.dark .ui-chip--yellow {
    color: #ffd071;
    background: rgba(242, 169, 59, 0.08);
    border-color: rgba(242, 184, 75, 0.34);
}
html.dark .ui-chip--red {
    color: #ff8a8a;
    background: rgba(224, 91, 91, 0.08);
    border-color: rgba(224, 91, 91, 0.34);
}
html.dark .ui-chip--blue {
    color: #79a8ff;
    background: rgba(93, 158, 255, 0.08);
    border-color: rgba(93, 158, 255, 0.28);
}
html.dark .ui-chip--purple {
    color: #b9a7ff;
    background: rgba(163, 139, 255, 0.08);
    border-color: rgba(163, 139, 255, 0.28);
}
html.dark .ui-chip--teal {
    color: #5de4d2;
    background: rgba(45, 212, 191, 0.08);
    border-color: rgba(45, 212, 191, 0.28);
}
</style>
