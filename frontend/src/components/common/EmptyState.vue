<script setup>
import BaseIcon from './BaseIcon.vue';

defineProps({
    title: { type: String, default: '데이터가 없습니다' },
    hint: { type: String, default: '' },
    icon: { type: String, default: 'inbox' }, // utils/icons.js의 아이콘 이름
});
</script>

<template>
    <div class="empty-state">
        <div class="empty-state__icon" :class="`empty-state__icon--${icon}`">
            <BaseIcon :name="icon" :size="30" />
        </div>
        <p class="empty-state__title">{{ title }}</p>
        <p v-if="hint" class="empty-state__hint">{{ hint }}</p>
        <div v-if="$slots.action" class="empty-state__action">
            <slot name="action" />
        </div>
    </div>
</template>

<style scoped>
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 36px 20px 28px;
    text-align: center;
}

.empty-state__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 68px;
    height: 68px;
    margin-bottom: 8px;
    border-radius: 50%;
    color: var(--brand);
    background: var(--brand-soft);
}

.empty-state__icon--search {
    color: #2f7de1;
    background: rgba(47, 125, 225, 0.12);
}

.empty-state__icon--chat {
    color: #7a5af8;
    background: rgba(122, 90, 248, 0.12);
}

.empty-state__icon--bell {
    color: #e8733d;
    background: rgba(232, 115, 61, 0.12);
}

.empty-state__icon--truck {
    color: #2ba471;
    background: rgba(43, 164, 113, 0.12);
}

.empty-state__icon--edit {
    color: #2f7de1;
    background: rgba(47, 125, 225, 0.12);
}

.empty-state__icon--wallet {
    color: #2ba471;
    background: rgba(43, 164, 113, 0.12);
}

html.dark .empty-state__icon {
    background: rgba(255, 255, 255, 0.06);
}

.empty-state__title {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
}

.empty-state__hint {
    margin: 0;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.5;
}

.empty-state__action {
    margin-top: 12px;
}
</style>
