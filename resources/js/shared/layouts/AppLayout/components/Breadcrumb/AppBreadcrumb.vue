<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2 text-sm text-[#6a6a6a] dark:text-[#9ea1a8]">
            <li
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
                class="flex items-center gap-2"
            >
                <a
                    v-if="item.href && index !== items.length - 1"
                    :href="item.href"
                    class="transition hover:text-[#1f1f1f] dark:hover:text-[#d6d6dd]"
                >
                    {{ item.label }}
                </a>
                <span v-else>{{ item.label }}</span>
                <span v-if="index !== items.length - 1" class="text-[#8b8b8b] dark:text-[#7d8087]">/</span>
            </li>
        </ol>
    </nav>
</template>
