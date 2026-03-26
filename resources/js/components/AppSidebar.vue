<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ClipboardList, LayoutGrid, LifeBuoy, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as results } from '@/routes/results';
import type { NavItem } from '@/types';

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('nav.dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: trans('nav.results'),
        href: results(),
        icon: ClipboardList,
    },
]);

const secondaryNavItems = computed<NavItem[]>(() => [
    {
        title: trans('nav.support'),
        href: 'mailto:support@cliniq.app',
        icon: LifeBuoy,
    },
    {
        title: trans('nav.feedback'),
        href: 'mailto:feedback@cliniq.app?subject=Cliniq%20Feedback',
        icon: Send,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="secondaryNavItems" :label="trans('nav.help')" class="px-2 pb-2" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
