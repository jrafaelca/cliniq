<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.appearance_breadcrumb'),
        href: edit(),
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="trans('settings.appearance_head_title')" />

        <h1 class="sr-only">{{ trans('settings.appearance_sr_title') }}</h1>

        <div class="mx-auto w-full max-w-5xl">
            <SettingsLayout>
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        :title="trans('settings.appearance_title')"
                        :description="trans('settings.appearance_description')"
                    />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </div>
    </AppLayout>
</template>
