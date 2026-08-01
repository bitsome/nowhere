<script setup>
import FormGroup from '../Form/FormGroup.vue';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '검색어를 입력하세요',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <FormGroup label="검색" for-id="datatable-search-field">
        <BaseInput
            id="datatable-search-field"
            :model-value="modelValue"
            :placeholder="placeholder"
            title="데이터 검색"
            aria-label="데이터 검색"
            @update:model-value="emit('update:modelValue', $event)"
        />
    </FormGroup>
</template>
