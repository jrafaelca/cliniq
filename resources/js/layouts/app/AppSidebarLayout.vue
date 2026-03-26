<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader
                :breadcrumbs="breadcrumbs"
                :page-title="pageTitle"
            />
            <slot />
        </AppContent>
    </AppShell>
</template>
