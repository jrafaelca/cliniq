<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import {
    AlertCircle,
    BarChart3,
    Clock3,
    History,
    Plus,
    RefreshCcw,
    Sparkles,
    Target,
    Trophy,
    ZapIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref, type Component } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemHeader,
    ItemMedia,
    ItemTitle,
} from '@/components/ui/item';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as practiceIndex, show } from '@/routes/practice';
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

type DashboardIndicator = {
    id: 'attempts' | 'average' | 'best' | 'time';
    label: string;
    value: string;
    hint: string;
    icon: Component;
};

type Props = {
    activeAttemptId: number | null;
    activeAttemptRemainingQuestions: number | null;
    activeAttemptHasProgress: boolean;
    activeAttemptMode: 'practice' | 'review' | 'training' | 'simulation' | null;
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
const isStartingReview = ref(false);
const isReviewReplaceDialogOpen = ref(false);

const weakestCategory = computed(() => stats.value?.category_performance[0] ?? null);

const activeAttemptModeLabel = computed(() => {
    if (props.activeAttemptMode === 'training') {
        return trans('dashboard.session_mode_training');
    }

    if (props.activeAttemptMode === 'simulation') {
        return trans('dashboard.session_mode_simulation');
    }

    return trans('dashboard.session_mode_practice');
});

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

const indicators = computed<DashboardIndicator[]>(() => {
    if (!stats.value) {
        return [];
    }

    return [
        {
            id: 'attempts',
            label: trans('dashboard.total_attempts'),
            value: String(stats.value.total_attempts),
            hint: trans('dashboard.kpi_attempts_hint'),
            icon: Target,
        },
        {
            id: 'average',
            label: trans('dashboard.average_score'),
            value: `${Math.round(stats.value.average_score)}%`,
            hint: trans('dashboard.kpi_average_hint'),
            icon: BarChart3,
        },
        {
            id: 'best',
            label: trans('dashboard.best_score'),
            value: `${Math.round(stats.value.best_score)}%`,
            hint: trans('dashboard.kpi_best_hint'),
            icon: Trophy,
        },
        {
            id: 'time',
            label: trans('dashboard.total_time'),
            value: totalTimeLabel.value,
            hint: trans('dashboard.kpi_total_time_hint'),
            icon: Clock3,
        },
    ];
});

function scoreBadgeVariant(score: number): 'default' | 'secondary' | 'destructive' {
    if (score >= 70) {
        return 'default';
    }

    if (score >= 40) {
        return 'secondary';
    }

    return 'destructive';
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

function startReviewSession(restart: boolean): void {
    if (isStartingReview.value || (stats.value?.incorrect_count ?? 0) === 0) {
        return;
    }

    isStartingReview.value = true;

    const payload: { restart?: 1 } = {};

    if (restart) {
        payload.restart = 1;
    }

    router.post(startReview().url, payload, {
        preserveScroll: true,
        onFinish: () => {
            isStartingReview.value = false;
            isReviewReplaceDialogOpen.value = false;
        },
    });
}

function handleStartReview(): void {
    if (isStartingReview.value || (stats.value?.incorrect_count ?? 0) === 0) {
        return;
    }

    if (props.activeAttemptId === null) {
        startReviewSession(false);

        return;
    }

    isReviewReplaceDialogOpen.value = true;
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

    <AppLayout
        :breadcrumbs="breadcrumbs"
        :page-title="trans('dashboard.title')"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Item variant="outline">
                <ItemMedia variant="icon">
                    <ZapIcon />
                </ItemMedia>

                <ItemContent>
                    <ItemTitle class="text-lg font-semibold">
                        {{ trans('dashboard.main_cta_title') }}
                    </ItemTitle>
                    <ItemDescription class="line-clamp-none">
                        <template v-if="activeAttemptId">
                            {{
                                trans('dashboard.main_cta_active_description_mode', {
                                    remaining: String(activeAttemptRemainingQuestions ?? 0),
                                    mode: activeAttemptModeLabel,
                                })
                            }}
                        </template>
                        <template v-else>
                            {{ trans('dashboard.main_cta_idle_description') }}
                        </template>
                    </ItemDescription>

                    <ItemDescription
                        v-if="practiceError"
                        class="line-clamp-none text-destructive"
                    >
                        {{ practiceError }}
                    </ItemDescription>

                    <ItemDescription
                        v-if="!hasQuestions"
                        class="line-clamp-none"
                    >
                        {{ trans('dashboard.no_questions') }}
                    </ItemDescription>
                </ItemContent>

                <ItemActions class="ml-auto flex-wrap justify-end">
                    <Button
                        as-child
                        variant="outline"
                        size="default"
                        :disabled="!hasQuestions"
                    >
                        <Link :href="practiceIndex()">
                            <Plus class="mr-2 size-4" />
                            {{
                                activeAttemptId
                                    ? trans('dashboard.main_cta_new_session')
                                    : trans('dashboard.main_cta_pick_session')
                            }}
                        </Link>
                    </Button>

                    <Button
                        v-if="activeAttemptId"
                        as-child
                        size="default"
                    >
                        <Link :href="show(activeAttemptId)">
                            {{ trans('dashboard.main_cta_continue') }}
                        </Link>
                    </Button>
                </ItemActions>
            </Item>

            <div v-if="statsLoading" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <div
                    v-for="index in 4"
                    :key="index"
                    class="h-44 animate-pulse rounded-2xl border bg-muted/40"
                />
            </div>

            <p
                v-else-if="statsError"
                class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                {{ statsError }}
            </p>

            <template v-else-if="stats">
                <section>
                    <ItemGroup class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Item
                            v-for="indicator in indicators"
                            :key="indicator.id"
                            variant="outline"
                            class="h-full"
                        >
                            <ItemHeader>
                                <ItemTitle class="text-sm font-medium text-muted-foreground">
                                    {{ indicator.label }}
                                </ItemTitle>
                                <ItemMedia variant="icon">
                                    <component :is="indicator.icon" />
                                </ItemMedia>
                            </ItemHeader>
                            <ItemContent class="gap-2">
                                <p class="text-3xl font-semibold tracking-tight tabular-nums">
                                    {{ indicator.value }}
                                </p>
                                <ItemDescription>
                                    {{ indicator.hint }}
                                </ItemDescription>
                            </ItemContent>
                        </Item>
                    </ItemGroup>
                </section>

                <section class="grid gap-4 xl:grid-cols-5">
                    <Card class="rounded-2xl border border-border/70 xl:col-span-3">
                        <CardHeader>
                            <CardTitle class="inline-flex items-center gap-2">
                                <BarChart3 class="size-5" />
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
                        <CardContent v-else>
                            <ItemGroup class="gap-3">
                                <Item
                                    v-for="category in stats.category_performance"
                                    :key="category.category_id"
                                    variant="outline"
                                    size="sm"
                                >
                                    <ItemContent>
                                        <ItemHeader>
                                            <ItemTitle>{{ category.name }}</ItemTitle>
                                            <Badge
                                                :variant="scoreBadgeVariant(category.score)"
                                            >
                                                {{ Math.round(category.score) }}%
                                            </Badge>
                                        </ItemHeader>
                                        <Progress
                                            :model-value="Math.max(0, Math.min(100, Math.round(category.score)))"
                                        />
                                    </ItemContent>
                                </Item>
                            </ItemGroup>
                        </CardContent>
                    </Card>

                    <Card class="rounded-2xl border border-border/70 xl:col-span-2">
                        <CardHeader>
                            <CardTitle class="inline-flex items-center gap-2">
                                <Sparkles class="size-5" />
                                {{ trans('dashboard.recommendation_title') }}
                            </CardTitle>
                            <CardDescription>
                                {{ trans('dashboard.recommendation_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent v-if="weakestCategory" class="space-y-3">
                            <Item variant="outline" size="sm">
                                <ItemMedia variant="icon">
                                    <Target class="size-4" />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>{{ weakestCategory.name }}</ItemTitle>
                                    <ItemDescription class="line-clamp-none">
                                        {{ trans('dashboard.recommendation_prefix') }}
                                    </ItemDescription>
                                </ItemContent>
                                <ItemActions>
                                    <Badge
                                        :variant="scoreBadgeVariant(weakestCategory.score)"
                                    >
                                        {{ Math.round(weakestCategory.score) }}%
                                    </Badge>
                                </ItemActions>
                            </Item>
                            <Button as-child variant="outline" size="default" class="w-full">
                                <Link :href="practiceIndex()">
                                    <Target class="mr-2 size-4" />
                                    {{ trans('dashboard.recommendation_button') }}
                                </Link>
                            </Button>
                        </CardContent>
                        <CardContent v-else class="text-sm text-muted-foreground">
                            {{ trans('dashboard.recommendation_empty') }}
                        </CardContent>
                    </Card>
                </section>

                <section class="grid gap-4 xl:grid-cols-5">
                    <Card class="rounded-2xl border border-border/70 xl:col-span-3">
                        <CardHeader class="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle class="inline-flex items-center gap-2">
                                    <History class="size-5" />
                                    {{ trans('dashboard.recent_attempts_title') }}
                                </CardTitle>
                                <CardDescription>
                                    {{ trans('dashboard.recent_attempts_description') }}
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" as-child>
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
                        <CardContent v-else>
                            <ItemGroup class="gap-3">
                                <Item
                                    v-for="attempt in stats.recent_attempts"
                                    :key="attempt.id"
                                    variant="outline"
                                    size="sm"
                                >
                                    <ItemMedia variant="icon">
                                        <History class="size-4" />
                                    </ItemMedia>
                                    <ItemContent>
                                        <ItemTitle>
                                            {{
                                                trans('dashboard.recent_attempt_label', {
                                                    id: String(attempt.id),
                                                })
                                            }}
                                        </ItemTitle>
                                        <ItemDescription>
                                            {{ formatDate(attempt.created_at) }}
                                        </ItemDescription>
                                    </ItemContent>
                                    <ItemActions class="flex-wrap">
                                        <Badge
                                            :variant="scoreBadgeVariant(attempt.score)"
                                        >
                                            {{ Math.round(attempt.score) }}%
                                        </Badge>
                                        <Badge variant="outline">
                                            {{
                                                trans('dashboard.recent_attempt_duration', {
                                                    minutes: String(formatMinutes(attempt.duration)),
                                                })
                                            }}
                                        </Badge>
                                        <Button variant="ghost" size="sm" as-child>
                                            <Link :href="show(attempt.id)">
                                                {{ trans('dashboard.view_detail') }}
                                            </Link>
                                        </Button>
                                    </ItemActions>
                                </Item>
                            </ItemGroup>
                        </CardContent>
                    </Card>

                    <Card class="rounded-2xl border border-border/70 xl:col-span-2">
                        <CardHeader>
                            <CardTitle class="inline-flex items-center gap-2">
                                <AlertCircle class="size-5" />
                                {{ trans('dashboard.review_title') }}
                            </CardTitle>
                            <CardDescription>
                                {{ trans('dashboard.review_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Item variant="outline" size="sm">
                                <ItemMedia variant="icon">
                                    <AlertCircle class="size-4" />
                                </ItemMedia>
                                <ItemContent class="gap-1.5">
                                    <ItemDescription class="line-clamp-none">
                                        {{ trans('dashboard.review_prefix') }}
                                    </ItemDescription>
                                    <ItemDescription class="line-clamp-none">
                                        {{ trans('dashboard.review_questions_suffix') }}
                                    </ItemDescription>
                                </ItemContent>
                                <ItemActions class="ml-auto flex-col items-end gap-0">
                                    <p class="text-2xl font-semibold tracking-tight tabular-nums">
                                        {{ stats.incorrect_count }}
                                    </p>
                                    <ItemDescription class="line-clamp-none">
                                        {{ trans('dashboard.review_questions_label') }}
                                    </ItemDescription>
                                </ItemActions>
                            </Item>

                            <p
                                v-if="reviewError"
                                class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            >
                                {{ reviewError }}
                            </p>

                            <Button
                                type="button"
                                variant="destructive"
                                size="default"
                                class="w-full"
                                :disabled="isStartingReview || stats.incorrect_count === 0"
                                @click="handleStartReview"
                            >
                                <RefreshCcw class="mr-2 size-4" />
                                {{
                                    isStartingReview
                                        ? trans('dashboard.review_button_loading')
                                        : trans('dashboard.review_button')
                                }}
                            </Button>
                        </CardContent>
                    </Card>
                </section>
            </template>
        </div>

        <Dialog
            :open="isReviewReplaceDialogOpen"
            @update:open="isReviewReplaceDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ trans('dashboard.review_replace_confirm_title') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            trans('dashboard.review_replace_confirm_description', {
                                remaining: String(activeAttemptRemainingQuestions ?? 0),
                            })
                        }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isStartingReview">
                            {{ trans('dashboard.review_replace_cancel') }}
                        </Button>
                    </DialogClose>

                    <Button
                        type="button"
                        :disabled="isStartingReview"
                        @click="startReviewSession(true)"
                    >
                        {{ trans('dashboard.review_replace_confirm') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
