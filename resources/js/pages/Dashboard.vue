<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemTitle,
} from '@/components/ui/item';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { show, start } from '@/routes/practice';
import { stats as dashboardStats } from '@/routes/dashboard';
import { index as results } from '@/routes/results';
import { start as startReview } from '@/routes/review';
import type { BreadcrumbItem } from '@/types';

type CategoryPerformance = {
    category_id: number;
    name: string;
    score: number;
};

type RecentAttempt = {
    id: number;
    score: number;
    created_at: string | null;
    duration: number;
};

type DashboardStats = {
    total_attempts: number;
    average_score: number;
    best_score: number;
    total_time: number;
    category_performance: CategoryPerformance[];
    incorrect_count: number;
    recent_attempts: RecentAttempt[];
};

type Props = {
    activeAttemptId: number | null;
    activeAttemptRemainingQuestions: number | null;
    hasQuestions: boolean;
    practiceError: string | null;
    reviewError: string | null;
};

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('dashboard.title'),
        href: dashboard(),
    },
]);

const stats = ref<DashboardStats | null>(null);
const statsLoading = ref(true);
const statsError = ref<string | null>(null);

const weakestCategory = computed(() => stats.value?.category_performance[0] ?? null);

const totalTimeLabel = computed(() => {
    const totalMinutes = stats.value?.total_time ?? 0;

    if (totalMinutes >= 60) {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;

        return trans('dashboard.time_hours_minutes', {
            hours: String(hours),
            minutes: String(minutes),
        });
    }

    return trans('dashboard.time_minutes', { minutes: String(totalMinutes) });
});

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

async function loadStats(): Promise<void> {
    statsLoading.value = true;
    statsError.value = null;

    try {
        const response = await fetch(dashboardStats().url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            statsError.value = trans('dashboard.stats_load_error');

            return;
        }

        stats.value = (await response.json()) as DashboardStats;
    } catch {
        statsError.value = trans('dashboard.stats_load_error');
    } finally {
        statsLoading.value = false;
    }
}

onMounted(loadStats);
</script>

<template>
    <Head :title="trans('dashboard.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4">
            <div class="flex w-full flex-col gap-6">
                <Item variant="outline">
                    <ItemContent>
                        <ItemTitle>{{ trans('dashboard.main_cta_title') }}</ItemTitle>
                        <ItemDescription v-if="activeAttemptId">
                            {{
                                trans('dashboard.main_cta_active_description', {
                                    remaining: String(activeAttemptRemainingQuestions ?? 0),
                                })
                            }}
                        </ItemDescription>
                        <ItemDescription v-else>
                            {{ trans('dashboard.main_cta_idle_description') }}
                        </ItemDescription>

                        <p
                            v-if="practiceError"
                            class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        >
                            {{ practiceError }}
                        </p>

                        <p
                            v-if="!hasQuestions"
                            class="rounded-md border border-border bg-muted px-3 py-2 text-sm text-muted-foreground"
                        >
                            {{ trans('dashboard.no_questions') }}
                        </p>
                    </ItemContent>

                    <ItemActions>
                        <Button v-if="activeAttemptId" as-child class="w-full sm:w-auto">
                            <Link :href="show(activeAttemptId)">
                                {{ trans('dashboard.main_cta_continue') }}
                            </Link>
                        </Button>

                        <Form
                            v-else
                            v-bind="start.form()"
                            class="w-full sm:w-auto"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="!hasQuestions || processing"
                            >
                                {{ trans('dashboard.main_cta_start') }}
                            </Button>
                        </Form>
                    </ItemActions>
                </Item>
            </div>

            <div v-if="statsLoading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="index in 4"
                    :key="index"
                    class="h-28 animate-pulse rounded-lg border bg-muted/30"
                />
            </div>

            <p
                v-else-if="statsError"
                class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                {{ statsError }}
            </p>

            <template v-else-if="stats">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                {{ trans('dashboard.total_attempts') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-semibold">
                                {{ stats.total_attempts }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                {{ trans('dashboard.average_score') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-semibold">
                                {{ Math.round(stats.average_score) }}%
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                {{ trans('dashboard.best_score') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-semibold">
                                {{ Math.round(stats.best_score) }}%
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                {{ trans('dashboard.total_time') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-lg font-semibold">
                                {{ totalTimeLabel }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-5">
                    <Card class="xl:col-span-3">
                        <CardHeader>
                            <CardTitle>
                                {{ trans('dashboard.category_performance_title') }}
                            </CardTitle>
                            <CardDescription>
                                {{ trans('dashboard.category_performance_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent
                            v-if="stats.category_performance.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ trans('dashboard.category_performance_empty') }}
                        </CardContent>
                        <CardContent v-else class="space-y-4">
                            <div
                                v-for="category in stats.category_performance"
                                :key="category.category_id"
                                class="space-y-2"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium">{{ category.name }}</p>
                                    <Badge :class="scoreBadgeClass(category.score)">
                                        {{ Math.round(category.score) }}%
                                    </Badge>
                                </div>
                                <Progress :model-value="category.score" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>
                                {{ trans('dashboard.recommendation_title') }}
                            </CardTitle>
                            <CardDescription>
                                {{ trans('dashboard.recommendation_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent v-if="weakestCategory" class="space-y-2">
                            <p class="text-sm text-muted-foreground">
                                {{ trans('dashboard.recommendation_prefix') }}
                            </p>
                            <p class="text-2xl font-semibold">
                                {{ weakestCategory.name }}
                            </p>
                            <Badge :class="scoreBadgeClass(weakestCategory.score)">
                                {{ Math.round(weakestCategory.score) }}%
                            </Badge>
                        </CardContent>
                        <CardContent v-else class="text-sm text-muted-foreground">
                            {{ trans('dashboard.recommendation_empty') }}
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-5">
                    <Card class="xl:col-span-3">
                        <CardHeader class="flex flex-row items-center justify-between gap-4">
                            <div>
                                <CardTitle>{{ trans('dashboard.recent_attempts_title') }}</CardTitle>
                                <CardDescription>
                                    {{ trans('dashboard.recent_attempts_description') }}
                                </CardDescription>
                            </div>
                            <Button variant="secondary" as-child>
                                <Link :href="results()">
                                    {{ trans('dashboard.recent_attempts_view_all') }}
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent
                            v-if="stats.recent_attempts.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ trans('dashboard.recent_attempts_empty') }}
                        </CardContent>
                        <CardContent v-else class="space-y-3">
                            <div
                                v-for="attempt in stats.recent_attempts"
                                :key="attempt.id"
                                class="flex flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{
                                            trans('dashboard.recent_attempt_label', {
                                                id: String(attempt.id),
                                            })
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(attempt.created_at) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :class="scoreBadgeClass(attempt.score)">
                                        {{ Math.round(attempt.score) }}%
                                    </Badge>
                                    <Badge variant="outline">
                                        {{
                                            trans('dashboard.recent_attempt_duration', {
                                                minutes: String(formatMinutes(attempt.duration)),
                                            })
                                        }}
                                    </Badge>
                                    <Button variant="ghost" as-child>
                                        <Link :href="show(attempt.id)">
                                            {{ trans('dashboard.view_detail') }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>{{ trans('dashboard.review_title') }}</CardTitle>
                            <CardDescription>
                                {{ trans('dashboard.review_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <p class="text-sm text-muted-foreground">
                                {{ trans('dashboard.review_prefix') }}
                            </p>
                            <p class="text-3xl font-semibold">
                                {{ stats.incorrect_count }}
                            </p>
                            <p
                                v-if="reviewError"
                                class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            >
                                {{ reviewError }}
                            </p>
                        </CardContent>
                        <CardFooter>
                            <Form v-bind="startReview.form()" class="w-full sm:w-auto" v-slot="{ processing }">
                                <Button
                                    type="submit"
                                    class="w-full sm:w-auto"
                                    :disabled="processing || stats.incorrect_count === 0"
                                >
                                    {{
                                        processing
                                            ? trans('dashboard.review_button_loading')
                                            : trans('dashboard.review_button')
                                    }}
                                </Button>
                            </Form>
                        </CardFooter>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
