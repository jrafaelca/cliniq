<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import AppLayout from '@/layouts/AppLayout.vue';
import { finish, answer, show } from '@/routes/practice';
import type { BreadcrumbItem } from '@/types';

type QuestionOption = {
    id: number;
    text: string;
};

type PracticeQuestion = {
    id: number;
    statement: string;
    type: 'single' | 'multiple';
    options: QuestionOption[];
};

type ProgressPayload = {
    current: number;
    total: number;
    percent: number;
};

type AnswerFeedback = {
    is_correct: boolean;
    correct_option_ids: number[];
    explanation: string;
    answered_count: number;
    total_questions: number;
    is_last_question: boolean;
};

type Props = {
    attemptId: number;
    question: PracticeQuestion;
    progress: ProgressPayload;
};

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('practice.breadcrumb'),
        href: show(props.attemptId),
    },
]);

const selectedSingleOption = ref<string>('');
const multipleSelectionMap = ref<
    Record<number, boolean | 'indeterminate' | null>
>({});
const feedback = ref<AnswerFeedback | null>(null);
const formError = ref<string | null>(null);
const isSubmitting = ref(false);
const questionStartTimeMs = ref<number>(Date.now());
const pausedTimeMs = ref<number>(0);
const pauseStartTimeMs = ref<number | null>(null);
const nowMs = ref<number>(Date.now());
const showKeyboardHints = ref(false);
let timerIntervalId: ReturnType<typeof setInterval> | null = null;
let pointerMediaQuery: MediaQueryList | null = null;

const selectedOptionIds = computed(() => {
    if (props.question.type === 'single') {
        return selectedSingleOption.value ? [Number(selectedSingleOption.value)] : [];
    }

    return Object.entries(multipleSelectionMap.value)
        .filter(
            ([, isChecked]) =>
                Boolean(isChecked) && isChecked !== 'indeterminate',
        )
        .map(([optionId]) => Number(optionId))
        .sort((left, right) => left - right);
});

function resolveSelectedOptionIds(): number[] {
    if (props.question.type === 'single') {
        return selectedOptionIds.value;
    }

    const selectedFromDom = props.question.options
        .filter((option) => {
            const checkboxElement = document.getElementById(`option-${option.id}`);
            const dataState = checkboxElement?.getAttribute('data-state');
            const ariaChecked = checkboxElement?.getAttribute('aria-checked');

            return dataState === 'checked' || ariaChecked === 'true';
        })
        .map((option) => option.id);

    return Array.from(
        new Set([...selectedOptionIds.value, ...selectedFromDom]),
    ).sort((left, right) => left - right);
}

const correctOptionsText = computed(() => {
    if (!feedback.value) {
        return [];
    }

    return props.question.options
        .filter((option) => feedback.value?.correct_option_ids.includes(option.id))
        .map((option) => option.text);
});
const nextQuestionHref = computed(() => show(props.attemptId).url);

const visibleTimerInSeconds = computed(() => {
    let totalPausedTimeMs = pausedTimeMs.value;

    if (pauseStartTimeMs.value !== null) {
        totalPausedTimeMs += nowMs.value - pauseStartTimeMs.value;
    }

    const activeTimeMs = Math.max(
        0,
        nowMs.value - questionStartTimeMs.value - totalPausedTimeMs,
    );

    return Math.floor(activeTimeMs / 1000);
});

const visibleTimerLabel = computed(() => {
    const totalSeconds = visibleTimerInSeconds.value;
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

watch(selectedSingleOption, (value) => {
    if (value) {
        formError.value = null;
    }
});

watch(selectedOptionIds, (value) => {
    if (value.length > 0) {
        formError.value = null;
    }
});

function resetQuestionTimer(): void {
    questionStartTimeMs.value = Date.now();
    pausedTimeMs.value = 0;
    pauseStartTimeMs.value = document.hidden ? Date.now() : null;
    nowMs.value = Date.now();
}

function syncPausedTimeFromVisibility(): void {
    const now = Date.now();
    nowMs.value = now;

    if (document.hidden) {
        if (pauseStartTimeMs.value === null) {
            pauseStartTimeMs.value = now;
        }

        return;
    }

    if (pauseStartTimeMs.value !== null) {
        pausedTimeMs.value += now - pauseStartTimeMs.value;
        pauseStartTimeMs.value = null;
    }
}

function calculateTimeSpentInSeconds(): number {
    return Math.max(1, visibleTimerInSeconds.value);
}

function shouldIgnoreShortcutTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tagName = target.tagName.toLowerCase();

    if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') {
        return true;
    }

    return target.isContentEditable;
}

function toggleOptionByIndex(index: number): void {
    const option = props.question.options[index];

    if (!option) {
        return;
    }

    if (props.question.type === 'single') {
        selectedSingleOption.value = String(option.id);

        return;
    }

    multipleSelectionMap.value[option.id] =
        multipleSelectionMap.value[option.id] === true ? false : true;
}

function handleEnterShortcut(): void {
    if (isSubmitting.value) {
        return;
    }

    if (!feedback.value) {
        void submitAnswer();

        return;
    }

    if (!feedback.value.is_last_question) {
        router.visit(nextQuestionHref.value);

        return;
    }

    document.getElementById('practice-finish-button')?.click();
}

function handleKeyboardShortcuts(event: KeyboardEvent): void {
    if (event.defaultPrevented) {
        return;
    }

    if (event.metaKey || event.ctrlKey || event.altKey) {
        return;
    }

    if (shouldIgnoreShortcutTarget(event.target)) {
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        handleEnterShortcut();

        return;
    }

    if (feedback.value || isSubmitting.value) {
        return;
    }

    if (!/^[1-9]$/.test(event.key)) {
        return;
    }

    const optionIndex = Number(event.key) - 1;

    if (optionIndex >= props.question.options.length) {
        return;
    }

    event.preventDefault();
    toggleOptionByIndex(optionIndex);
}

function updateKeyboardHintsVisibility(): void {
    showKeyboardHints.value = pointerMediaQuery?.matches ?? false;
}

watch(
    () => props.question.id,
    () => {
        resetQuestionTimer();
    },
);

onMounted(() => {
    document.addEventListener('visibilitychange', syncPausedTimeFromVisibility);
    window.addEventListener('keydown', handleKeyboardShortcuts);

    pointerMediaQuery = window.matchMedia('(pointer: fine)');
    updateKeyboardHintsVisibility();
    pointerMediaQuery.addEventListener('change', updateKeyboardHintsVisibility);

    timerIntervalId = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
    resetQuestionTimer();
});

onBeforeUnmount(() => {
    document.removeEventListener(
        'visibilitychange',
        syncPausedTimeFromVisibility,
    );
    window.removeEventListener('keydown', handleKeyboardShortcuts);

    pointerMediaQuery?.removeEventListener(
        'change',
        updateKeyboardHintsVisibility,
    );
    pointerMediaQuery = null;

    if (timerIntervalId !== null) {
        clearInterval(timerIntervalId);
        timerIntervalId = null;
    }
});

async function submitAnswer(): Promise<void> {
    if (feedback.value || isSubmitting.value) {
        return;
    }

    const resolvedSelectedOptionIds = resolveSelectedOptionIds();

    if (resolvedSelectedOptionIds.length === 0) {
        formError.value = trans('practice.select_one_error');

        return;
    }

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    formError.value = null;
    isSubmitting.value = true;

    try {
        const response = await fetch(answer(props.attemptId).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                question_id: props.question.id,
                selected_options: resolvedSelectedOptionIds,
                time_spent_seconds: calculateTimeSpentInSeconds(),
            }),
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            const firstError = payload?.errors
                ? Object.values(payload.errors).flat()[0]
                : null;

            formError.value =
                (typeof firstError === 'string' && firstError) ||
                payload?.message ||
                trans('practice.save_error');

            return;
        }

        feedback.value = payload as AnswerFeedback;
    } catch {
        formError.value = trans('practice.network_error');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="trans('practice.head_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <p class="font-medium text-muted-foreground">
                        {{
                            trans('practice.progress_question', {
                                current: String(progress.current),
                                total: String(progress.total),
                            })
                        }}
                    </p>
                    <div class="text-right">
                        <p class="font-semibold text-foreground">
                            {{
                                trans('practice.progress_completed', {
                                    percent: String(Math.round(progress.percent)),
                                })
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ trans('practice.timer_label') }}: {{ visibleTimerLabel }}
                        </p>
                    </div>
                </div>
                <Progress :model-value="progress.percent" />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="leading-relaxed">
                        {{ question.statement }}
                    </CardTitle>
                    <CardDescription>
                        {{
                            question.type === 'single'
                                ? trans('practice.select_single')
                                : trans('practice.select_multiple')
                        }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-3">
                    <RadioGroup
                        v-if="question.type === 'single'"
                        v-model="selectedSingleOption"
                        class="gap-3"
                    >
                        <div
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex items-center gap-3 rounded-md border p-3"
                        >
                            <RadioGroupItem
                                :id="`option-${option.id}`"
                                :value="String(option.id)"
                                :disabled="Boolean(feedback)"
                            />
                            <Label
                                :for="`option-${option.id}`"
                                class="flex-1 cursor-pointer leading-snug"
                            >
                                {{ option.text }}
                            </Label>
                        </div>
                    </RadioGroup>

                    <div v-else class="space-y-3">
                        <div
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex items-center gap-3 rounded-md border p-3"
                        >
                            <Checkbox
                                :id="`option-${option.id}`"
                                v-model="multipleSelectionMap[option.id]"
                                :true-value="true"
                                :false-value="false"
                                :disabled="Boolean(feedback)"
                            />
                            <Label
                                :for="`option-${option.id}`"
                                class="flex-1 cursor-pointer leading-snug"
                            >
                                {{ option.text }}
                            </Label>
                        </div>
                    </div>

                    <p v-if="formError" class="text-sm text-destructive">
                        {{ formError }}
                    </p>
                </CardContent>

                <CardFooter class="flex flex-wrap items-center gap-3">
                    <Button
                        v-if="!feedback"
                        type="button"
                        :disabled="isSubmitting"
                        @click="submitAnswer"
                    >
                        <span>{{
                            isSubmitting
                                ? trans('practice.validating')
                                : trans('practice.answer')
                        }}</span>
                    </Button>

                    <Button
                        v-else-if="!feedback.is_last_question"
                        as-child
                        type="button"
                    >
                        <Link :href="show(attemptId)" class="inline-flex items-center gap-2">
                            <span>{{ trans('practice.next_question') }}</span>
                        </Link>
                    </Button>

                    <Form
                        v-else
                        v-bind="finish.form(attemptId)"
                        v-slot="{ processing }"
                    >
                        <Button
                            id="practice-finish-button"
                            type="submit"
                            :disabled="processing"
                        >
                            <span>{{ trans('practice.finish_session') }}</span>
                        </Button>
                    </Form>

                    <p
                        v-if="showKeyboardHints"
                        class="w-full text-right text-xs text-muted-foreground"
                    >
                        <span>{{ trans('practice.shortcuts_hint_prefix') }}</span>
                        <kbd
                            class="mx-1 rounded border bg-muted px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-foreground"
                        >
                            1-9
                        </kbd>
                        <span>{{ trans('practice.shortcuts_hint_options') }}</span>
                        <kbd
                            class="ml-1 rounded border bg-muted px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-foreground"
                        >
                            Enter
                        </kbd>
                    </p>
                </CardFooter>
            </Card>

            <Card
                v-if="feedback"
                :class="
                    feedback.is_correct
                        ? 'border-emerald-500/40 bg-emerald-500/5'
                        : 'border-destructive/40 bg-destructive/5'
                "
            >
                <CardHeader>
                    <CardTitle>
                        {{
                            feedback.is_correct
                                ? trans('practice.correct_feedback')
                                : trans('practice.incorrect_feedback')
                        }}
                    </CardTitle>
                    <CardDescription>
                        {{ feedback.explanation }}
                    </CardDescription>
                </CardHeader>
                <CardContent v-if="correctOptionsText.length > 0">
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ trans('practice.correct_options') }}
                    </p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li
                            v-for="text in correctOptionsText"
                            :key="text"
                            class="rounded bg-background/80 px-2 py-1"
                        >
                            {{ text }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
