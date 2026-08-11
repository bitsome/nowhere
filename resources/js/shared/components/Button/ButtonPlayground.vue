<script setup>
import BaseButton from './BaseButton.vue';

/**
 * 공용 버튼(Button) 플레이그라운드.
 *
 * 현재 사용 중인 BaseButton variant / size 조합을 한눈에 보여준다.
 */
const variants = [
    { key: 'primary', note: '주요 액션 (확인/저장/검색) — 다이얼로그 확인, 툴바 추가 등' },
    { key: 'secondary', note: '보조 액션 (취소/초기화) — 다이얼로그 취소, 페이지네이션 등' },
    { key: 'ghost', note: '경량 액션 (닫기) — 모달 닫기, 파일관리 모달 취소 등' },
    { key: 'danger', note: '위험 액션 (삭제 확인) — 다이얼로그 삭제 버튼' },
];

const sizes = ['sm', 'md', 'lg'];
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="variant in variants"
            :key="variant.key"
            class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ variant.key }}
                    </p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                        {{ variant.note }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <BaseButton
                        v-for="size in sizes"
                        :key="size"
                        :variant="variant.key"
                        :size="size"
                        :title="`${variant.key} ${size}`"
                        :aria-label="`${variant.key} ${size}`"
                    >
                        {{ variant.key }} {{ size }}
                    </BaseButton>
                </div>
            </div>
        </div>
    </div>
</template>
