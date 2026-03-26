<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { BookOpen, Clock3, Target, Trophy, Zap } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Item,
    ItemContent,
    ItemDescription,
    ItemFooter,
    ItemGroup,
    ItemHeader,
    ItemMedia,
    ItemTitle,
} from '@/components/ui/item';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { show, start } from '@/routes/practice';
import type { BreadcrumbItem } from '@/types';

type AttemptMode = 'training' | 'practice' | 'simulation' | null;

type Props = {
    activeAttemptId: number | null;
    remainingQuestions: number | null;
    activeAttemptMode: AttemptMode;
    activeAttemptProgressPercent: number | null;
    activeAttemptLastActivityAt: string | null;
    hasQuestions: boolean;
};

type SessionModeCard = {
    mode: Exclude<AttemptMode, null>;
    title: string;
    description: string;
    metricLabel: string;
    metricValue: string;
    icon: Component;
};

const props = defineProps<Props>();

const hasActiveAttempt = computed(() => props.activeAttemptId !== null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('practice.breadcrumb'),
        href: props.activeAttemptId ? show(props.activeAttemptId) : dashboard(),
    },
]);

const activeAttemptProgressPercent = computed(() =>
    Math.max(0, Math.min(100, Math.round(props.activeAttemptProgressPercent ?? 0))),
);

const activeAttemptModeLabel = computed(() => {
    if (props.activeAttemptMode === 'training') {
        return trans('practice.entry_start_training');
    }

    if (props.activeAttemptMode === 'simulation') {
        return trans('practice.entry_start_simulation');
    }

    return trans('practice.entry_start_practice');
});

const activeAttemptRelativeTime = computed(() =>
    formatRelativeTime(props.activeAttemptLastActivityAt),
);

const sessionModeCards = computed<SessionModeCard[]>(() => [
    {
        mode: 'training',
        title: trans('practice.entry_start_training'),
        description: trans('practice.entry_mode_training_description'),
        metricLabel: trans('practice.entry_mode_training_meta_label'),
        metricValue: trans('practice.entry_mode_training_meta_value'),
        icon: BookOpen,
    },
    {
        mode: 'practice',
        title: trans('practice.entry_start_practice'),
        description: trans('practice.entry_mode_practice_description'),
        metricLabel: trans('practice.entry_mode_practice_meta_label'),
        metricValue: trans('practice.entry_mode_practice_meta_value'),
        icon: Target,
    },
    {
        mode: 'simulation',
        title: trans('practice.entry_start_simulation'),
        description: trans('practice.entry_mode_simulation_description'),
        metricLabel: trans('practice.entry_mode_simulation_meta_label'),
        metricValue: trans('practice.entry_mode_simulation_meta_value'),
        icon: Trophy,
    },
]);

function formatRelativeTime(isoDate: string | null): string {
    if (!isoDate) {
        return trans('practice.entry_last_activity_recently');
    }

    const target = new Date(isoDate).getTime();

    if (Number.isNaN(target)) {
        return trans('practice.entry_last_activity_recently');
    }

    const seconds = Math.round((target - Date.now()) / 1000);
    const absoluteSeconds = Math.abs(seconds);
    const pageLanguage =
        typeof document !== 'undefined' && document.documentElement.lang.startsWith('es')
            ? 'es'
            : 'en';
    const formatter = new Intl.RelativeTimeFormat(pageLanguage, {
        numeric: 'auto',
    });

    if (absoluteSeconds < 60) {
        return formatter.format(seconds, 'second');
    }

    const minutes = Math.round(seconds / 60);

    if (Math.abs(minutes) < 60) {
        return formatter.format(minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);

    if (Math.abs(hours) < 24) {
        return formatter.format(hours, 'hour');
    }

    const days = Math.round(hours / 24);

    return formatter.format(days, 'day');
}
</script>

<template>
    <Head :title="trans('practice.head_title')" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        :page-title="trans('practice.entry_selector_title')"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 md:p-6">
            <Item
                v-if="hasActiveAttempt && activeAttemptId"
                variant="outline"
                class="w-full"
            >
                <ItemMedia variant="icon">
                    <Zap class="size-4" />
                </ItemMedia>
                <ItemContent class="w-full">
                    <ItemHeader>
                        <ItemTitle>{{ trans('practice.entry_active_badge') }}</ItemTitle>
                    </ItemHeader>
                    <ItemTitle class="text-lg font-semibold">
                        {{ trans('practice.entry_active_headline') }}
                    </ItemTitle>
                    <ItemDescription class="line-clamp-none">
                        {{
                            trans('practice.entry_description_active_mode', {
                                remaining: String(remainingQuestions ?? 0),
                                mode: activeAttemptModeLabel,
                            })
                        }}
                    </ItemDescription>
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{{ trans('practice.entry_progress_label') }}</span>
                        <span class="font-semibold text-foreground"
                            >{{ activeAttemptProgressPercent }}%</span
                        >
                    </div>
                    <Progress :model-value="activeAttemptProgressPercent" />
                </ItemContent>
                <ItemFooter class="w-full">
                    <ItemDescription class="line-clamp-none inline-flex items-center gap-2">
                        <Clock3 class="size-4" />
                        {{ activeAttemptRelativeTime }}
                    </ItemDescription>
                    <Button as-child>
                        <Link :href="show(activeAttemptId)">
                            {{ trans('practice.entry_continue') }}
                        </Link>
                    </Button>
                </ItemFooter>
            </Item>

            <div
                v-if="hasActiveAttempt"
                class="flex items-center gap-4 text-muted-foreground"
            >
                <span class="h-px flex-1 bg-border" />
                <span class="text-sm">{{ trans('practice.entry_or_start_new') }}</span>
                <span class="h-px flex-1 bg-border" />
            </div>

            <p
                v-if="!hasQuestions"
                class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
            >
                {{ trans('practice.entry_no_questions') }}
            </p>

            <section>
                <ItemGroup class="grid gap-4 md:grid-cols-3">
                    <Form
                        v-for="sessionMode in sessionModeCards"
                        :key="sessionMode.mode"
                        v-bind="start.form()"
                        v-slot="{ processing }"
                    >
                        <input
                            v-if="hasActiveAttempt"
                            type="hidden"
                            name="restart"
                            value="1"
                        />
                        <input type="hidden" name="mode" :value="sessionMode.mode" />
                        <Item
                            variant="outline"
                            class="h-full w-full text-left"
                        >
                            <ItemMedia variant="icon">
                                <component :is="sessionMode.icon" class="size-5" />
                            </ItemMedia>
                            <ItemContent>
                                <ItemTitle class="text-base font-semibold">{{ sessionMode.title }}</ItemTitle>
                                <ItemDescription class="line-clamp-none">
                                    {{ sessionMode.description }}
                                </ItemDescription>
                            </ItemContent>
                            <ItemFooter class="mt-1 flex-col items-stretch gap-3 border-t border-border/70 pt-3">
                                <div class="flex w-full items-center justify-between">
                                    <ItemDescription class="line-clamp-none">
                                        {{ sessionMode.metricLabel }}
                                    </ItemDescription>
                                    <span class="text-sm font-semibold text-foreground">
                                        {{ sessionMode.metricValue }}
                                    </span>
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    class="w-full"
                                    :disabled="!hasQuestions || processing"
                                >
                                    {{
                                        processing
                                            ? trans('practice.entry_starting_new')
                                            : sessionMode.title
                                    }}
                                </Button>
                            </ItemFooter>
                        </Item>
                    </Form>
                </ItemGroup>
            </section>

        </div>
    </AppLayout>
</template>
