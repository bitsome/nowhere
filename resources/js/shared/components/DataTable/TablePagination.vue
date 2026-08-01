<script setup>
import { computed } from 'vue';
import BaseButton from '../Button/BaseButton.vue';
import BaseIcon from '../Icon/BaseIcon.vue';

const props = defineProps({
    currentPage: {
        type: Number,
        default: 1,
    },
    rangeEnd: {
        type: Number,
        default: 0,
    },
    rangeStart: {
        type: Number,
        default: 0,
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    totalPages: {
        type: Number,
        default: 1,
    },
});

const emit = defineEmits(['page-change']);

const pages = computed(() => {
    return Array.from({ length: props.totalPages }, (_, index) => index + 1);
});
</script>

<template>
    <div class="datatable-pagination">
        <p class="datatable-pagination__summary">
            {{ rangeStart }}-{{ rangeEnd }} / 총 {{ totalItems }}건
        </p>

        <div class="datatable-pagination__actions">
            <BaseButton
                variant="secondary"
                size="sm"
                class="min-w-[36px] px-0"
                title="이전 페이지"
                aria-label="이전 페이지"
                :disabled="currentPage <= 1"
                @click="emit('page-change', currentPage - 1)"
            >
                <BaseIcon name="chevron-left" :size="14" />
            </BaseButton>

            <div class="datatable-pagination__pages">
                <button
                    v-for="pageNumber in pages"
                    :key="pageNumber"
                    type="button"
                    class="datatable-pagination__page"
                    :class="{ 'datatable-pagination__page--active': pageNumber === currentPage }"
                    :title="`페이지 ${pageNumber}`"
                    :aria-label="`페이지 ${pageNumber}`"
                    @click="emit('page-change', pageNumber)"
                >
                    {{ pageNumber }}
                </button>
            </div>

            <BaseButton
                variant="secondary"
                size="sm"
                class="min-w-[36px] px-0"
                title="다음 페이지"
                aria-label="다음 페이지"
                :disabled="currentPage >= totalPages"
                @click="emit('page-change', currentPage + 1)"
            >
                <BaseIcon name="chevron-right" :size="14" />
            </BaseButton>
        </div>
    </div>
</template>
