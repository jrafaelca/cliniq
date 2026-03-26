<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { show } from '@/routes/practice';
import { index } from '@/routes/results';
import type { BreadcrumbItem } from '@/types';

type ResultItem = {
    id: number;
    score: number;
    finished_at: string | null;
    duration: number;
};

type ResultsPayload = {
    data: ResultItem[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: {
        next: string | null;
        prev: string | null;
    };
};

type Props = {
    results: ResultsPayload;
};

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('dashboard.title'),
        href: dashboard(),
    },
    {
        title: trans('results.title'),
        href: index(),
    },
]);

function scoreBadgeClass(score: number): string {
    if (score >= 75) {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (score >= 50) {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-destructive/40 bg-destructive/10 text-destructive';
}

function formatDate(value: string | null): string {
    if (!value) {
        return trans('dashboard.not_available');
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return trans('dashboard.not_available');
    }

    return date.toLocaleString();
}

function formatMinutes(value: number): number {
    const parsedValue = Number(value);

    if (!Number.isFinite(parsedValue) || parsedValue <= 0) {
        return 0;
    }

    return Math.ceil(parsedValue);
}
</script>

<template>
    <Head :title="trans('results.head_title')" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        :page-title="trans('results.title')"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Card>
                <CardContent
                    v-if="results.data.length === 0"
                    class="pt-6 text-sm text-muted-foreground"
                >
                    {{ trans('results.empty') }}
                </CardContent>
                <CardContent v-else class="space-y-3 pt-6">
                    <div
                        v-for="attempt in results.data"
                        :key="attempt.id"
                        class="flex flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <p class="text-sm font-medium">
                                {{
                                    trans('results.attempt_label', {
                                        id: String(attempt.id),
                                    })
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(attempt.finished_at) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :class="scoreBadgeClass(attempt.score)">
                                {{ Math.round(attempt.score) }}%
                            </Badge>
                            <Badge variant="outline">
                                {{
                                    trans('results.duration', {
                                        minutes: String(formatMinutes(attempt.duration)),
                                    })
                                }}
                            </Badge>
                            <Button variant="ghost" as-child>
                                <Link :href="show(attempt.id)">
                                    {{ trans('results.view_detail') }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center justify-between gap-3">
                <Button
                    variant="secondary"
                    :disabled="!results.links.prev"
                    as-child
                >
                    <Link :href="results.links.prev || '#'">
                        {{ trans('results.previous_page') }}
                    </Link>
                </Button>

                <p class="text-sm text-muted-foreground">
                    {{
                        trans('results.page_indicator', {
                            page: String(results.meta.current_page),
                            total: String(results.meta.last_page),
                        })
                    }}
                </p>

                <Button
                    variant="secondary"
                    :disabled="!results.links.next"
                    as-child
                >
                    <Link :href="results.links.next || '#'">
                        {{ trans('results.next_page') }}
                    </Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
