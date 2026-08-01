<script setup>
import BaseLoading from '../Loading/BaseLoading.vue';
</script>

<template>
    <div class="datatable-loading">
        <BaseLoading :active="true" :inline="true" label="목록을 불러오는 중입니다." />
    </div>
</template>
