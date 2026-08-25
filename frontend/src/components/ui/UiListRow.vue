<script setup>
/**
 * UiListRow — 아이콘 + 본문 + 값/화살표/점이 있는 리스트 행.
 * (더보기 메뉴, 알림 목록 등 공통 행 패턴)
 * icon은 @vicons/ionicons5 폰트아이콘 이름을 받는다. (utils/icons.js)
 */
import BaseIcon from '../common/BaseIcon.vue';

defineProps({
    tag: { type: String, default: 'div' },
    icon: { type: String, default: '' },
    iconSize: { type: [Number, String], default: 16 },
    value: { type: String, default: '' },
    arrow: { type: Boolean, default: false },
    dot: { type: Boolean, default: false },
    danger: { type: Boolean, default: false },
    hover: { type: Boolean, default: true },
});
</script>

<template>
    <component
        :is="tag"
        class="ui-row"
        :class="{ 'ui-row--danger': danger, 'ui-row--hover': hover }"
    >
        <span v-if="icon" class="ui-row__icon">
            <BaseIcon :name="icon" :size="iconSize" />
        </span>
        <slot name="icon" />
        <div class="ui-row__body"><slot /></div>
        <span v-if="value" class="ui-row__value">{{ value }}</span>
        <span v-if="arrow" class="ui-row__arrow"><BaseIcon name="arrow-forward" :size="14" /></span>
        <i v-if="dot" class="ui-row__dot"></i>
    </component>
</template>

<style scoped>
.ui-row {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    width: 100%;
    padding: 13px 15px;
    border: 0;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
    background: transparent;
    color: var(--text);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    text-align: left;
    text-decoration: none;
    cursor: default;
}
.ui-row:last-child {
    border-bottom: 0;
}
.ui-row--hover {
    cursor: pointer;
    transition: background 0.12s ease;
}
.ui-row--hover:hover {
    background: color-mix(in srgb, var(--brand) 5%, transparent);
}
.ui-row__icon {
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 9px;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    color: var(--brand);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}
.ui-row__body {
    flex: 1;
    min-width: 0;
}
.ui-row__value {
    font-size: 12px;
    color: var(--text-muted);
}
.ui-row__arrow {
    color: var(--text-muted);
    font-size: 16px;
    font-weight: 300;
}
.ui-row__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--brand);
    flex: none;
    margin-top: 6px;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 8%, transparent), 0 0 9px color-mix(in srgb, var(--brand) 60%, transparent);
}
.ui-row--danger {
    color: var(--danger);
}
.ui-row--danger .ui-row__icon {
    background: color-mix(in srgb, var(--danger) 12%, transparent);
    color: var(--danger);
}
.ui-row--danger .ui-row__arrow {
    color: color-mix(in srgb, var(--danger) 60%, transparent);
}
</style>
