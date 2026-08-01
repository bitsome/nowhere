<script setup>
import FormGroup from '../Form/FormGroup.vue';
import BaseSelect from '../Form/BaseSelect.vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <FormGroup label="필터" for-id="datatable-filter-field">
        <BaseSelect
            id="datatable-filter-field"
            :model-value="modelValue"
            :options="options"
            title="데이터 필터"
            aria-label="데이터 필터"
            @update:model-value="emit('update:modelValue', $event)"
        />
    </FormGroup>
</template>
