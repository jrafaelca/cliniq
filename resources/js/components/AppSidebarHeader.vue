<script setup lang="ts">
import { computed } from 'vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        pageTitle?: string | null;
    }>(),
    {
        breadcrumbs: () => [],
        pageTitle: null,
    },
);

const resolvedTitle = computed(() => {
    if (props.pageTitle) {
        return props.pageTitle;
    }

    const currentBreadcrumb = props.breadcrumbs.at(-1);

    return currentBreadcrumb?.title ?? '';
});
</script>

<template>
    <header
        class="flex min-h-16 shrink-0 items-center border-b border-sidebar-border/70 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12"
    >
        <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
            <SidebarTrigger class="-ml-1" />
            <Separator
                orientation="vertical"
                class="mx-2 data-[orientation=vertical]:h-4"
            />
            <div class="min-w-0">
                <h1 class="truncate text-base font-medium">
                    {{ resolvedTitle }}
                </h1>
            </div>
        </div>
    </header>
</template>
