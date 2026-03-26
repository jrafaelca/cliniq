<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    pageTitle?: string | null;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    pageTitle: null,
});
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :page-title="pageTitle"
    >
        <slot />
    </AppLayout>
</template>
