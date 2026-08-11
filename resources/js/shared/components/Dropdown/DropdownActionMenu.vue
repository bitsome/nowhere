<script setup>
import { computed } from 'vue';
import DropdownActions from './DropdownActions.vue';

/**
 * 헤더 바로가기 메뉴.
 *
 * 공용 DropdownActions 모듈에 헤더 전용 메뉴 항목을 구성해서 사용한다.
 */
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
    myOrdersUrl: {
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
        default: 'btn-secondary shared-dropdown__icon-trigger',
    },
    triggerLabel: {
        type: String,
        default: '바로가기 메뉴',
    },
});

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

const items = computed(() => [
    { header: true, title: props.title, description: props.description },
    { divider: true },
    { icon: 'dashboard', label: '대시보드', href: props.dashboardUrl },
    { icon: 'bell', label: '알림 테스트', href: props.notificationUrl },
    { icon: 'list', label: '내가 받은 오더', href: props.myOrdersUrl },
    { icon: 'user', label: '프로필', href: props.profileUrl },
    { divider: true },
    { icon: 'logout', label: '로그아웃', danger: true, action: logout },
]);
</script>

<template>
    <DropdownActions
        :items="items"
        :trigger-class="triggerClass"
        :trigger-label="triggerLabel"
        trigger-icon="menu"
        swap-icon-on-open
        width="260px"
    />
</template>
