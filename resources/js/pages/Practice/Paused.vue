<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { start, show } from '@/routes/practice';
import type { BreadcrumbItem } from '@/types';

type Props = {
    attemptId: number;
    inactivity_limit_minutes: number;
};

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('practice.breadcrumb'),
        href: show(props.attemptId),
    },
]);

const continuePracticeHref = computed(() => {
    const showRoute = show(props.attemptId);
    const separator = showRoute.url.includes('?') ? '&' : '?';

    return `${showRoute.url}${separator}resume=1`;
});
</script>

<template>
    <Head :title="trans('practice.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {{ trans('practice.inactivity_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{
                            trans('practice.inactivity_message', {
                                minutes: String(inactivity_limit_minutes),
                            })
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('practice.inactivity_help') }}
                    </p>
                </CardContent>
                <CardFooter class="flex flex-wrap gap-3">
                    <Button as-child>
                        <Link :href="continuePracticeHref">
                            {{ trans('practice.inactivity_continue') }}
                        </Link>
                    </Button>

                    <Form
                        v-bind="start.form()"
                        v-slot="{ processing }"
                        class="w-full sm:w-auto"
                    >
                        <input type="hidden" name="restart" value="1" />
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="processing"
                        >
                            {{ trans('practice.inactivity_restart') }}
                        </Button>
                    </Form>
                </CardFooter>
            </Card>
        </div>
    </AppLayout>
</template>
