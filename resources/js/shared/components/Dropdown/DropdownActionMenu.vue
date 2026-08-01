<script setup>
import BaseIcon from '../Icon/BaseIcon.vue';
import Dropdown from './Dropdown.vue';
import DropdownDivider from './DropdownDivider.vue';
import DropdownHeader from './DropdownHeader.vue';
import DropdownItem from './DropdownItem.vue';
import DropdownMenu from './DropdownMenu.vue';

const props = defineProps({
    csrfToken: {
        type: String,
        default: '',
    },
    dashboardUrl: {
        type: String,
        default: '#',
    },
    notificationUrl: {
        type: String,
        default: '#',
    },
    description: {
        type: String,
        default: '',
    },
    logoutUrl: {
        type: String,
        default: '#',
    },
    profileUrl: {
        type: String,
        default: '#',
    },
    title: {
        type: String,
        default: 'Dashboard',
    },
    triggerClass: {
        type: String,
        default: 'btn-secondary',
    },
    triggerLabel: {
        type: String,
        default: '바로가기 메뉴',
    },
});

const goTo = (url) => {
    window.location.href = url;
};

const logout = () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = props.logoutUrl;

    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = props.csrfToken;

    form.appendChild(tokenInput);
    document.body.appendChild(form);
    form.submit();
};
</script>

<template>
    <Dropdown align="left" width="260px">
        <template #trigger="{ isOpen }">
            <button
                type="button"
                :class="[triggerClass, 'shared-dropdown__icon-trigger']"
                :title="isOpen ? `${triggerLabel} 닫기` : `${triggerLabel} 열기`"
                :aria-label="isOpen ? `${triggerLabel} 닫기` : `${triggerLabel} 열기`"
            >
                <BaseIcon
                    :name="isOpen ? 'close' : 'menu'"
                    :size="20"
                />
            </button>
        </template>

        <template #default="{ isOpen }">
            <DropdownMenu :is-open="isOpen">
                <DropdownHeader :title="title" :description="description" />
                <DropdownDivider />
                <DropdownItem title="대시보드" aria-label="대시보드" @click="goTo(dashboardUrl)">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="dashboard" :size="18" />
                        <span>대시보드</span>
                    </span>
                </DropdownItem>
                <DropdownItem title="알림 테스트" aria-label="알림 테스트" @click="goTo(notificationUrl)">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="bell" :size="18" />
                        <span>알림 테스트</span>
                    </span>
                </DropdownItem>
                <DropdownItem title="프로필" aria-label="프로필" @click="goTo(profileUrl)">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="user" :size="18" />
                        <span>프로필</span>
                    </span>
                </DropdownItem>
                <DropdownDivider />
                <DropdownItem danger title="로그아웃" aria-label="로그아웃" @click="logout">
                    <span class="shared-dropdown__item-content">
                        <BaseIcon name="logout" :size="18" />
                        <span>로그아웃</span>
                    </span>
                </DropdownItem>
            </DropdownMenu>
        </template>
    </Dropdown>
</template>
