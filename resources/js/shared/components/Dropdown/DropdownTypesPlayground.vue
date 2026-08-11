<script setup>
import BaseIcon from '../Icon/BaseIcon.vue';
import Dropdown from './Dropdown.vue';
import DropdownDivider from './DropdownDivider.vue';
import DropdownHeader from './DropdownHeader.vue';
import DropdownItem from './DropdownItem.vue';
import DropdownMenu from './DropdownMenu.vue';

/**
 * 공용 Dropdown의 유형별 데모.
 *
 * variant:
 * - trigger  : 클릭 트리거 변형(아이콘/버튼/텍스트)
 * - position : 메뉴 정렬(align left/right)
 * - structure: 헤더/구분선/danger 항목 구조
 */
defineProps({
    variant: {
        type: String,
        default: 'trigger',
    },
});
</script>

<template>
    <div v-if="variant === 'trigger'" class="flex flex-wrap items-center gap-3">
        <Dropdown align="left" width="200px">
            <template #trigger>
                <button
                    type="button"
                    class="btn-secondary shared-dropdown__icon-trigger"
                    title="아이콘 트리거 메뉴"
                    aria-label="아이콘 트리거 메뉴"
                >
                    <BaseIcon name="menu" :size="18" />
                </button>
            </template>

            <DropdownMenu>
                <DropdownItem title="상세보기">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="view" :size="18" />
                        <span>상세보기</span>
                    </span>
                </DropdownItem>
                <DropdownItem title="수정">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="edit" :size="18" />
                        <span>수정</span>
                    </span>
                </DropdownItem>
            </DropdownMenu>
        </Dropdown>

        <Dropdown align="left" width="200px">
            <template #trigger>
                <button type="button" class="btn-secondary" title="버튼 트리거 메뉴">
                    <span class="flex items-center gap-2">
                        <BaseIcon name="settings" :size="16" />
                        <span>설정 메뉴</span>
                        <BaseIcon name="chevron-down" :size="14" />
                    </span>
                </button>
            </template>

            <DropdownMenu>
                <DropdownItem title="설정">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="settings" :size="18" />
                        <span>설정</span>
                    </span>
                </DropdownItem>
                <DropdownItem title="새로고침">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="refresh" :size="18" />
                        <span>새로고침</span>
                    </span>
                </DropdownItem>
            </DropdownMenu>
        </Dropdown>

        <Dropdown align="left" width="200px">
            <template #trigger>
                <button type="button" class="btn-secondary" title="텍스트 트리거 메뉴">
                    <span class="flex items-center gap-1.5">
                        <span>더보기</span>
                        <BaseIcon name="chevron-down" :size="14" />
                    </span>
                </button>
            </template>

            <DropdownMenu>
                <DropdownItem title="검색">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="search" :size="18" />
                        <span>검색</span>
                    </span>
                </DropdownItem>
                <DropdownItem danger title="로그아웃">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="logout" :size="18" />
                        <span>로그아웃</span>
                    </span>
                </DropdownItem>
            </DropdownMenu>
        </Dropdown>
    </div>

    <div v-else-if="variant === 'position'" class="grid gap-3 xl:grid-cols-2">
        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
            <p class="text-xs text-gray-500 dark:text-gray-400">align="left"</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">왼쪽 정렬 메뉴</p>
            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">메뉴 왼쪽 끝이 트리거 왼쪽 끝에 맞춰집니다.</p>
            <div class="mt-4 flex justify-start">
                <Dropdown align="left" width="220px">
                    <template #trigger>
                        <button type="button" class="btn-secondary" title="왼쪽 정렬 메뉴">
                            <span class="flex items-center gap-2">
                                <BaseIcon name="menu" :size="16" />
                                <span>왼쪽 정렬</span>
                            </span>
                        </button>
                    </template>

                    <DropdownMenu>
                        <DropdownItem title="오더 관리">
                            <span class="shared-dropdown__item-content">
                                <BaseIcon name="dashboard" :size="18" />
                                <span>오더 관리</span>
                            </span>
                        </DropdownItem>
                        <DropdownItem title="배차 관리">
                            <span class="shared-dropdown__item-content">
                                <BaseIcon name="bell" :size="18" />
                                <span>배차 관리</span>
                            </span>
                        </DropdownItem>
                    </DropdownMenu>
                </Dropdown>
            </div>
        </div>

        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
            <p class="text-xs text-gray-500 dark:text-gray-400">align="right"</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">오른쪽 정렬 메뉴</p>
            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">메뉴 오른쪽 끝이 트리거 오른쪽 끝에 맞춰집니다.</p>
            <div class="mt-4 flex justify-end">
                <Dropdown align="right" width="220px">
                    <template #trigger>
                        <button type="button" class="btn-secondary" title="오른쪽 정렬 메뉴">
                            <span class="flex items-center gap-2">
                                <BaseIcon name="menu" :size="16" />
                                <span>오른쪽 정렬</span>
                            </span>
                        </button>
                    </template>

                    <DropdownMenu>
                        <DropdownItem title="오더 관리">
                            <span class="shared-dropdown__item-content">
                                <BaseIcon name="dashboard" :size="18" />
                                <span>오더 관리</span>
                            </span>
                        </DropdownItem>
                        <DropdownItem title="배차 관리">
                            <span class="shared-dropdown__item-content">
                                <BaseIcon name="bell" :size="18" />
                                <span>배차 관리</span>
                            </span>
                        </DropdownItem>
                    </DropdownMenu>
                </Dropdown>
            </div>
        </div>
    </div>

    <div v-else class="flex">
        <Dropdown align="left" width="260px">
            <template #trigger>
                <button type="button" class="btn-secondary" title="헤더·구분선·danger 구조 메뉴">
                    <span class="flex items-center gap-2">
                        <BaseIcon name="menu" :size="16" />
                        <span>메뉴 구조</span>
                        <BaseIcon name="chevron-down" :size="14" />
                    </span>
                </button>
            </template>

            <DropdownMenu>
                <DropdownHeader title="오더 #20260803-001" description="서울 → 인천 · 09:00" />
                <DropdownDivider />
                <DropdownItem title="상세보기">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="view" :size="18" />
                        <span>상세보기</span>
                    </span>
                </DropdownItem>
                <DropdownItem title="수정">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="edit" :size="18" />
                        <span>수정</span>
                    </span>
                </DropdownItem>
                <DropdownDivider />
                <DropdownItem danger title="삭제">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="trash" :size="18" />
                        <span>삭제</span>
                    </span>
                </DropdownItem>
            </DropdownMenu>
        </Dropdown>
    </div>
</template>
