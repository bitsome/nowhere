<script setup>
/**
 * BaseIcon — 이름 기반 아이콘 렌더러.
 * 모든 아이콘은 @vicons/ionicons5 폰트아이콘(SVG)으로 통일해 사용한다.
 *
 * <BaseIcon name="history" :size="18" />
 */
import { computed } from 'vue';
import { iconOf } from '../../utils/icons';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 18 },
});

const comp = computed(() => iconOf(props.name));
</script>

<template>
    <n-icon :size="size"><component :is="comp" /></n-icon>
</template>
