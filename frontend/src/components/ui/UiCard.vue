<script setup>
/**
 * UiCard — 공통 카드 컨테이너.
 * 표면 색상·테두리·둥근 모서리·패딩을 통일해 중복을 방지한다.
 */
defineProps({
    tag: { type: String, default: 'div' },
    padded: { type: Boolean, default: true },
    hover: { type: Boolean, default: false },
    tone: { type: String, default: 'surface' }, // surface | tint | accent
});
</script>

<template>
    <component
        :is="tag"
        class="ui-card"
        :class="{
            'ui-card--unpadded': !padded,
            'ui-card--hover': hover,
            [`ui-card--${tone}`]: tone !== 'surface',
        }"
    >
        <slot />
    </component>
</template>

<style scoped>
.ui-card {
    display: block;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 16px;
    color: inherit;
    text-decoration: none;
    text-align: left;
}
.ui-card--unpadded {
    padding: 0;
}
.ui-card--hover {
    cursor: pointer;
    transition: border-color 0.15s ease;
}
.ui-card--hover:hover {
    border-color: color-mix(in srgb, var(--brand) 30%, transparent);
}
.ui-card--tint {
    background: #f3f5f6;
    border-color: #e3e6e9;
}
.ui-card--accent {
    background: linear-gradient(135deg, #eef9f4, #f1f6fa);
    border-color: color-mix(in srgb, var(--brand) 30%, transparent);
}
html.dark .ui-card--tint {
    background: #161a1e;
    border-color: #2b3035;
}
html.dark .ui-card--accent {
    background: linear-gradient(135deg, #18221e, #171b1e);
    border-color: color-mix(in srgb, var(--brand) 28%, transparent);
}
</style>
