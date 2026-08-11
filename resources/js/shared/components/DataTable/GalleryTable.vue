<script setup>
import DropdownTableActions from '../Dropdown/DropdownTableActions.vue';

/**
 * 갤러리형 테이블.
 *
 * 이미지 썸네일 + 메타 정보 + 공용 액션 버튼(점 3개)으로 구성된 카드 그리드 목록.
 * items 구조:
 * - { id, name, fileName, size, mimeType, imageUrl, actions: [...] }
 */
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    emptyTitle: {
        type: String,
        default: '업로드된 항목이 없습니다.',
    },
    emptyDescription: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="gallery-table">
        <div v-if="items.length === 0" class="gallery-table__empty">
            <p class="gallery-table__empty-title">{{ emptyTitle }}</p>
            <p v-if="emptyDescription" class="gallery-table__empty-description">{{ emptyDescription }}</p>
        </div>

        <div v-else class="gallery-table__grid">
            <article v-for="item in items" :key="item.id" class="gallery-table__card">
                <div class="gallery-table__thumb">
                    <img
                        v-if="item.imageUrl"
                        :src="item.imageUrl"
                        :alt="item.name"
                        :title="item.name"
                        class="gallery-table__image"
                    >
                    <div v-else class="gallery-table__placeholder">
                        <p>미리보기 없음</p>
                        <p class="gallery-table__placeholder-name">{{ item.fileName }}</p>
                    </div>
                </div>

                <div class="gallery-table__meta">
                    <p class="gallery-table__title" :title="item.name">{{ item.name }}</p>
                    <p class="gallery-table__subtitle" :title="item.fileName">{{ item.fileName }}</p>
                    <p class="gallery-table__info">{{ item.size }} / {{ item.mimeType }}</p>
                </div>

                <div class="gallery-table__actions">
                    <DropdownTableActions
                        :items="item.actions"
                        :trigger-label="`${item.name} 액션`"
                    />
                </div>
            </article>
        </div>
    </div>
</template>
