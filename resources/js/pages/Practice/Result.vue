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
import { dashboard } from '@/routes';
import { start } from '@/routes/practice';
import type { BreadcrumbItem } from '@/types';

type Props = {
    attemptId: number;
    score: number | string | null;
    correct_count: number;
    incorrect_count: number;
    total_questions: number;
    started_at: string | null;
    finished_at: string | null;
};

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('result.breadcrumb'),
        href: dashboard(),
    },
]);

const scoreValue = computed(() => Math.round(Number(props.score ?? 0)));

const durationInMinutes = computed(() => {
    if (!props.started_at || !props.finished_at) {
        return null;
    }

    const startDate = new Date(props.started_at);
    const endDate = new Date(props.finished_at);
    const diffMs = endDate.getTime() - startDate.getTime();

    if (Number.isNaN(diffMs) || diffMs <= 0) {
        return 0;
    }

    return Math.round(diffMs / 60000);
});
</script>

<template>
    <Head :title="trans('result.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-2xl">{{
                        trans('result.session_finished')
                    }}</CardTitle>
                    <CardDescription>
                        {{
                            trans('result.final_score', {
                                score: String(scoreValue),
                            })
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            {{ trans('dashboard.correct') }}
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ correct_count }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            {{ trans('dashboard.incorrect') }}
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ incorrect_count }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            {{ trans('dashboard.total') }}
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ total_questions }}
                        </p>
                    </div>
                </CardContent>
                <CardFooter
                    class="flex flex-col items-start gap-3 border-t pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                >
                    <p v-if="durationInMinutes !== null">
                        {{
                            trans('result.duration', {
                                minutes: String(durationInMinutes),
                            })
                        }}
                    </p>
                    <p v-else>{{ trans('result.duration_not_available') }}</p>
                </CardFooter>
            </Card>

            <div class="flex flex-wrap gap-3">
                <Form
                    v-bind="start.form()"
                    v-slot="{ processing }"
                    class="w-full sm:w-auto"
                >
                    <Button type="submit" class="w-full sm:w-auto">
                        {{
                            processing
                                ? trans('result.starting')
                                : trans('result.retry')
                        }}
                    </Button>
                </Form>

                <Button variant="secondary" as-child class="w-full sm:w-auto">
                    <Link :href="dashboard()">{{
                        trans('result.back_dashboard')
                    }}</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
