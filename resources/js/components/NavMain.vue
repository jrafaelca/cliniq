<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { CirclePlus } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { start } from '@/routes/practice';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const isStartingPractice = ref(false);

function quickStartPractice(): void {
    if (isStartingPractice.value) {
        return;
    }

    isStartingPractice.value = true;

    router.post(
        start().url,
        {},
        {
            onFinish: () => {
                isStartingPractice.value = false;
            },
        },
    );
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupContent class="flex flex-col gap-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground min-w-8 duration-200 ease-linear"
                        type="button"
                        :disabled="isStartingPractice"
                        @click="quickStartPractice"
                    >
                        <CirclePlus />
                        <span>{{
                            isStartingPractice
                                ? trans('nav.quick_start_practice_loading')
                                : trans('nav.quick_start_practice')
                        }}</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarGroupLabel>{{ trans('nav.platform') }}</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
