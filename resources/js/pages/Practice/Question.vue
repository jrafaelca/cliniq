<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Flag, Play } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemHeader,
    ItemGroup,
    ItemMedia,
} from '@/components/ui/item';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { answer, finish, show } from '@/routes/practice';
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

type QuestionTimerSnapshot = {
    questionStartTimeMs: number;
    pausedTimeMs: number;
    pauseStartTimeMs: number | null;
};

type PracticeSettings = {
    auto_advance?: boolean;
    auto_advance_delay?: number;
};

type Props = {
    attemptId: number;
    attempt_mode: 'practice' | 'review' | 'training' | 'simulation';
    attempt_time_limit_seconds?: number | null;
    attempt_started_at?: string | null;
    settings?: PracticeSettings;
    question: PracticeQuestion;
    progress: ProgressPayload;
};

const AUTO_ADVANCE_DELAY_FALLBACK = 5;
const AUTO_ADVANCE_DELAY_MIN = 1;
const AUTO_ADVANCE_DELAY_MAX = 30;
const AUTO_ADVANCE_FINALIZING_DELAY_MS = 300;
const AUTO_ADVANCE_SAVE_ERROR_DURATION_MS = 4000;
const USER_SETTINGS_ENDPOINT = '/user/settings';

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
const autoAdvanceSaveError = ref<string | null>(null);
const isSubmitting = ref(false);
const isAutoNavigating = ref(false);
const isAutoFinalizing = ref(false);
const autoAdvanceEnabled = ref<boolean>(
    props.attempt_mode === 'practice'
        ? (props.settings?.auto_advance ?? true)
        : false,
);
const countdownSeconds = ref<number | null>(null);
const questionStartTimeMs = ref<number>(Date.now());
const pausedTimeMs = ref<number>(0);
const pauseStartTimeMs = ref<number | null>(null);
const nowMs = ref<number>(Date.now());
let timerIntervalId: ReturnType<typeof setInterval> | null = null;
let countdownIntervalId: ReturnType<typeof setInterval> | null = null;
let autoAdvanceSaveErrorTimeoutId: ReturnType<typeof setTimeout> | null = null;
let autoFinalizingTimeoutId: ReturnType<typeof setTimeout> | null = null;

const isPracticeMode = computed(() => props.attempt_mode === 'practice');
const isSimulationMode = computed(() => props.attempt_mode === 'simulation');
const showsInstantFeedback = computed(() => props.attempt_mode === 'training');
const showsFeedbackExplanation = computed(() => props.attempt_mode === 'training');
const showElapsedTimer = computed(() => !isSimulationMode.value);
const progressModeLabel = computed(() => {
    if (props.attempt_mode === 'training') {
        return trans('practice.entry_start_training');
    }

    if (props.attempt_mode === 'simulation') {
        return trans('practice.entry_start_simulation');
    }

    if (props.attempt_mode === 'review') {
        return trans('review.title');
    }

    return trans('practice.entry_start_practice');
});

const autoAdvanceToggleLabel = computed(() => {
    if (
        autoAdvanceEnabled.value &&
        feedback.value !== null &&
        countdownSeconds.value !== null
    ) {
        return trans('practice.auto_advance_toggle_countdown', {
            seconds: String(countdownSeconds.value),
        });
    }

    return trans('practice.auto_advance_toggle');
});

const autoAdvanceDelay = computed(() => {
    const delay = Number(
        props.settings?.auto_advance_delay ?? AUTO_ADVANCE_DELAY_FALLBACK,
    );

    if (!Number.isFinite(delay)) {
        return AUTO_ADVANCE_DELAY_FALLBACK;
    }

    return Math.min(
        AUTO_ADVANCE_DELAY_MAX,
        Math.max(AUTO_ADVANCE_DELAY_MIN, Math.floor(delay)),
    );
});

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

const selectedOptionIdSet = computed(() => new Set(selectedOptionIds.value));

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

const simulationRemainingInSeconds = computed(() => {
    if (!isSimulationMode.value) {
        return null;
    }

    if (
        props.attempt_time_limit_seconds === null ||
        props.attempt_time_limit_seconds === undefined ||
        props.attempt_started_at === null ||
        props.attempt_started_at === undefined
    ) {
        return null;
    }

    const startedAtMs = new Date(props.attempt_started_at).getTime();

    if (Number.isNaN(startedAtMs)) {
        return null;
    }

    const elapsedSeconds = Math.max(0, Math.floor((nowMs.value - startedAtMs) / 1000));

    return Math.max(0, props.attempt_time_limit_seconds - elapsedSeconds);
});

const simulationRemainingLabel = computed(() => {
    const remainingSeconds = simulationRemainingInSeconds.value;

    if (remainingSeconds === null) {
        return null;
    }

    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;

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

watch(
    () => props.question.id,
    (nextQuestionId, previousQuestionId) => {
        if (typeof previousQuestionId === 'number' && previousQuestionId !== nextQuestionId) {
            clearQuestionTimerSnapshot(previousQuestionId);
        }

        resetQuestionTimer();
        stopAutoAdvanceCountdown();
        isAutoNavigating.value = false;
        isAutoFinalizing.value = false;
        autoAdvanceSaveError.value = null;
        clearAutoFinalizingTimeout();
    },
);

watch(feedback, (nextFeedback) => {
    stopAutoAdvanceCountdown();

    if (
        nextFeedback !== null &&
        isPracticeMode.value &&
        autoAdvanceEnabled.value
    ) {
        startAutoAdvanceCountdown();
    }
});

watch(simulationRemainingInSeconds, (remainingSeconds) => {
    if (!isSimulationMode.value || remainingSeconds === null || remainingSeconds > 0) {
        return;
    }

    if (isAutoNavigating.value || isSubmitting.value) {
        return;
    }

    isAutoNavigating.value = true;
    clearQuestionTimerSnapshot();
    stopAutoAdvanceCountdown();
    clearAutoFinalizingTimeout();

    router.visit(nextQuestionHref.value, {
        preserveScroll: true,
        onFinish: () => {
            isAutoNavigating.value = false;
            isAutoFinalizing.value = false;
        },
    });
});

watch(autoAdvanceEnabled, (isEnabled) => {
    if (!isPracticeMode.value) {
        return;
    }

    void persistAutoAdvancePreference(isEnabled);

    if (!isEnabled) {
        stopAutoAdvanceCountdown();

        return;
    }

    if (feedback.value !== null) {
        startAutoAdvanceCountdown();
    }
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

function questionTimerStorageKey(questionId: number = props.question.id): string {
    return `cliniq:question-timer:${props.attemptId}:${questionId}`;
}

function clearQuestionTimerSnapshot(questionId: number = props.question.id): void {
    if (!showElapsedTimer.value) {
        return;
    }

    try {
        localStorage.removeItem(questionTimerStorageKey(questionId));
    } catch {
        // Ignore storage access errors in private mode.
    }
}

function persistQuestionTimerSnapshot(): void {
    if (!showElapsedTimer.value) {
        return;
    }

    const snapshot: QuestionTimerSnapshot = {
        questionStartTimeMs: questionStartTimeMs.value,
        pausedTimeMs: pausedTimeMs.value,
        pauseStartTimeMs: pauseStartTimeMs.value,
    };

    try {
        localStorage.setItem(questionTimerStorageKey(), JSON.stringify(snapshot));
    } catch {
        // Ignore storage access errors in private mode.
    }
}

function isValidTimerNumber(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value) && value >= 0;
}

function isValidTimestamp(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value) && value > 0;
}

function restoreQuestionTimerSnapshot(): boolean {
    if (!showElapsedTimer.value) {
        return false;
    }

    try {
        const rawSnapshot = localStorage.getItem(questionTimerStorageKey());

        if (!rawSnapshot) {
            return false;
        }

        const parsedSnapshot = JSON.parse(rawSnapshot) as Partial<QuestionTimerSnapshot>;

        if (
            !isValidTimestamp(parsedSnapshot.questionStartTimeMs) ||
            !isValidTimerNumber(parsedSnapshot.pausedTimeMs)
        ) {
            clearQuestionTimerSnapshot();

            return false;
        }

        const now = Date.now();
        questionStartTimeMs.value = Math.min(parsedSnapshot.questionStartTimeMs, now);
        pausedTimeMs.value = parsedSnapshot.pausedTimeMs;
        pauseStartTimeMs.value = isValidTimerNumber(parsedSnapshot.pauseStartTimeMs)
            ? parsedSnapshot.pauseStartTimeMs
            : null;
        nowMs.value = now;

        if (pauseStartTimeMs.value !== null && !document.hidden) {
            pausedTimeMs.value += now - pauseStartTimeMs.value;
            pauseStartTimeMs.value = null;
        }

        if (pauseStartTimeMs.value === null && document.hidden) {
            pauseStartTimeMs.value = now;
        }

        return true;
    } catch {
        clearQuestionTimerSnapshot();

        return false;
    }
}

function resetQuestionTimer(): void {
    if (restoreQuestionTimerSnapshot()) {
        return;
    }

    questionStartTimeMs.value = Date.now();
    pausedTimeMs.value = 0;
    pauseStartTimeMs.value = document.hidden ? Date.now() : null;
    nowMs.value = Date.now();
    persistQuestionTimerSnapshot();
}

function syncPausedTimeFromVisibility(): void {
    const now = Date.now();
    nowMs.value = now;

    if (document.hidden) {
        if (pauseStartTimeMs.value === null) {
            pauseStartTimeMs.value = now;
        }

        persistQuestionTimerSnapshot();

        return;
    }

    if (pauseStartTimeMs.value !== null) {
        pausedTimeMs.value += now - pauseStartTimeMs.value;
        pauseStartTimeMs.value = null;
    }

    persistQuestionTimerSnapshot();
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

function isOptionSelected(optionId: number): boolean {
    return selectedOptionIdSet.value.has(optionId);
}

function optionContainerClass(optionId: number): string {
    const isSelected = isOptionSelected(optionId);

    if (!feedback.value) {
        if (isSelected) {
            return 'border-primary/70 bg-primary/5';
        }

        return 'hover:bg-accent/30';
    }

    const isCorrectOption = feedback.value.correct_option_ids.includes(optionId);

    if (isSelected && isCorrectOption) {
        return 'border-emerald-600 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40';
    }

    if (isSelected && !isCorrectOption) {
        return 'border-destructive/70 bg-destructive/10 dark:border-destructive/60 dark:bg-destructive/20';
    }

    if (isCorrectOption) {
        return 'border-emerald-400/60 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30';
    }

    return 'border-border opacity-80';
}

function clearAutoAdvanceSaveErrorTimeout(): void {
    if (autoAdvanceSaveErrorTimeoutId !== null) {
        clearTimeout(autoAdvanceSaveErrorTimeoutId);
        autoAdvanceSaveErrorTimeoutId = null;
    }
}

function clearAutoFinalizingTimeout(): void {
    if (autoFinalizingTimeoutId !== null) {
        clearTimeout(autoFinalizingTimeoutId);
        autoFinalizingTimeoutId = null;
    }
}

function stopAutoAdvanceCountdown(): void {
    if (countdownIntervalId !== null) {
        clearInterval(countdownIntervalId);
        countdownIntervalId = null;
    }

    countdownSeconds.value = null;
}

function updateAutoAdvanceEnabled(value: boolean | 'indeterminate'): void {
    autoAdvanceEnabled.value = value === true;
}

async function persistAutoAdvancePreference(isEnabled: boolean): Promise<void> {
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    try {
        const response = await fetch(USER_SETTINGS_ENDPOINT, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                auto_advance: isEnabled,
            }),
        });

        if (!response.ok) {
            throw new Error('Unable to persist settings.');
        }

        clearAutoAdvanceSaveErrorTimeout();
        autoAdvanceSaveError.value = null;
    } catch {
        autoAdvanceSaveError.value = trans('practice.auto_advance_save_error');
        clearAutoAdvanceSaveErrorTimeout();

        autoAdvanceSaveErrorTimeoutId = setTimeout(() => {
            autoAdvanceSaveError.value = null;
            autoAdvanceSaveErrorTimeoutId = null;
        }, AUTO_ADVANCE_SAVE_ERROR_DURATION_MS);
    }
}

function advanceAfterFeedback(withFinalizingPause = false): void {
    const currentFeedback = feedback.value;

    if (currentFeedback === null || isAutoNavigating.value) {
        return;
    }

    isAutoNavigating.value = true;
    isAutoFinalizing.value = false;
    stopAutoAdvanceCountdown();
    clearAutoFinalizingTimeout();

    if (currentFeedback.is_last_question) {
        if (withFinalizingPause) {
            isAutoFinalizing.value = true;
            autoFinalizingTimeoutId = setTimeout(() => {
                autoFinalizingTimeoutId = null;

                router.post(finish(props.attemptId).url, {}, {
                    preserveScroll: true,
                    onFinish: () => {
                        isAutoNavigating.value = false;
                        isAutoFinalizing.value = false;
                    },
                });
            }, AUTO_ADVANCE_FINALIZING_DELAY_MS);

            return;
        }

        router.post(finish(props.attemptId).url, {}, {
            preserveScroll: true,
            onFinish: () => {
                isAutoNavigating.value = false;
                isAutoFinalizing.value = false;
            },
        });

        return;
    }

    router.visit(nextQuestionHref.value, {
        preserveScroll: true,
        onFinish: () => {
            isAutoNavigating.value = false;
            isAutoFinalizing.value = false;
        },
    });
}

function startAutoAdvanceCountdown(): void {
    stopAutoAdvanceCountdown();

    if (
        !isPracticeMode.value ||
        !autoAdvanceEnabled.value ||
        feedback.value === null
    ) {
        return;
    }

    countdownSeconds.value = autoAdvanceDelay.value;
    countdownIntervalId = setInterval(() => {
        if (countdownSeconds.value === null) {
            return;
        }

        if (countdownSeconds.value <= 1) {
            stopAutoAdvanceCountdown();
            advanceAfterFeedback(true);

            return;
        }

        countdownSeconds.value -= 1;
    }, 1000);
}

function goToNextQuestion(): void {
    advanceAfterFeedback();
}

function handleManualFinish(): void {
    stopAutoAdvanceCountdown();
}

function handleEnterShortcut(): void {
    if (isSubmitting.value || isAutoNavigating.value) {
        return;
    }

    if (!feedback.value) {
        void submitAnswer();

        return;
    }

    if (!feedback.value.is_last_question) {
        advanceAfterFeedback();

        return;
    }

    stopAutoAdvanceCountdown();
    clearAutoFinalizingTimeout();
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

onMounted(() => {
    document.addEventListener('visibilitychange', syncPausedTimeFromVisibility);
    window.addEventListener('keydown', handleKeyboardShortcuts);

    timerIntervalId = setInterval(() => {
        nowMs.value = Date.now();
        persistQuestionTimerSnapshot();
    }, 1000);
    resetQuestionTimer();
});

onBeforeUnmount(() => {
    document.removeEventListener(
        'visibilitychange',
        syncPausedTimeFromVisibility,
    );
    window.removeEventListener('keydown', handleKeyboardShortcuts);

    if (timerIntervalId !== null) {
        clearInterval(timerIntervalId);
        timerIntervalId = null;
    }

    persistQuestionTimerSnapshot();
    stopAutoAdvanceCountdown();
    clearAutoAdvanceSaveErrorTimeout();
    clearAutoFinalizingTimeout();
});

async function submitAnswer(): Promise<void> {
    if (feedback.value || isSubmitting.value || isAutoNavigating.value) {
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

        clearQuestionTimerSnapshot();

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
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
            <Item variant="outline" size="sm">
                <ItemContent class="gap-2">
                    <ItemHeader>
                        <div class="flex items-center gap-2">
                            <p class="font-medium">
                                {{
                                    trans('practice.progress_question', {
                                        current: String(progress.current),
                                        total: String(progress.total),
                                    })
                                }}
                            </p>
                            <Badge variant="secondary">{{ progressModeLabel }}</Badge>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">
                                {{
                                    trans('practice.progress_completed', {
                                        percent: String(Math.round(progress.percent)),
                                    })
                                }}
                            </p>
                            <p
                                v-if="showElapsedTimer"
                                class="text-xs text-muted-foreground"
                            >
                                {{ trans('practice.timer_label') }}: {{ visibleTimerLabel }}
                            </p>
                            <p
                                v-else-if="isSimulationMode && simulationRemainingLabel"
                                class="text-xs text-muted-foreground"
                            >
                                {{ trans('practice.simulation_timer_label') }}: {{ simulationRemainingLabel }}
                            </p>
                        </div>
                    </ItemHeader>
                    <Progress :model-value="progress.percent" />
                </ItemContent>
            </Item>

            <Card class="overflow-hidden">
                <CardHeader class="flex flex-row items-start justify-between gap-4">
                    <div class="space-y-2">
                        <CardTitle>
                            {{ question.statement }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                question.type === 'single'
                                    ? trans('practice.select_single')
                                    : trans('practice.select_multiple')
                            }}
                        </CardDescription>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :title="trans('practice.flag_question')"
                        disabled
                    >
                        <Flag class="size-4" />
                    </Button>
                </CardHeader>

                <CardContent class="space-y-4">
                    <RadioGroup
                        v-if="question.type === 'single'"
                        v-model="selectedSingleOption"
                        class="gap-3"
                    >
                        <ItemGroup class="gap-3">
                            <Item
                                v-for="option in question.options"
                                :key="option.id"
                                variant="outline"
                                size="sm"
                                class="transition-colors"
                                :class="optionContainerClass(option.id)"
                            >
                                <ItemMedia>
                                    <RadioGroupItem
                                        :id="`option-${option.id}`"
                                        :value="String(option.id)"
                                        :disabled="Boolean(feedback)"
                                    />
                                </ItemMedia>
                                <ItemContent>
                                    <Label
                                        :for="`option-${option.id}`"
                                        class="cursor-pointer"
                                >
                                        {{ option.text }}
                                    </Label>
                                </ItemContent>
                            </Item>
                        </ItemGroup>
                    </RadioGroup>

                    <ItemGroup v-else class="gap-3">
                        <Item
                            v-for="option in question.options"
                            :key="option.id"
                            variant="outline"
                            size="sm"
                            class="transition-colors"
                            :class="optionContainerClass(option.id)"
                        >
                            <ItemMedia>
                                <Checkbox
                                    :id="`option-${option.id}`"
                                    v-model="multipleSelectionMap[option.id]"
                                    :true-value="true"
                                    :false-value="false"
                                    :disabled="Boolean(feedback)"
                                />
                            </ItemMedia>
                            <ItemContent>
                                <Label
                                    :for="`option-${option.id}`"
                                    class="cursor-pointer"
                                >
                                    {{ option.text }}
                                </Label>
                            </ItemContent>
                        </Item>
                    </ItemGroup>

                    <p v-if="formError" class="text-sm text-destructive">
                        {{ formError }}
                    </p>
                </CardContent>

                <CardFooter class="flex flex-wrap items-center gap-3 border-t">
                    <Button
                        v-if="!feedback"
                        type="button"
                        :disabled="isSubmitting || isAutoNavigating"
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
                        type="button"
                        :disabled="isAutoNavigating"
                        @click="goToNextQuestion"
                    >
                        <span>{{ trans('practice.inactivity_continue') }}</span>
                    </Button>

                    <Form
                        v-else
                        v-bind="finish.form(attemptId)"
                        v-slot="{ processing }"
                    >
                        <Button
                            id="practice-finish-button"
                            type="submit"
                            :disabled="processing || isAutoNavigating"
                            @click="handleManualFinish"
                        >
                            <span>{{
                                isAutoFinalizing
                                    ? trans('practice.auto_advance_finalizing')
                                    : trans('practice.finish_session')
                            }}</span>
                            <span
                                v-if="isAutoFinalizing"
                                class="inline-flex items-center"
                            >
                                <Spinner class="size-3 text-primary-foreground" />
                            </span>
                        </Button>
                    </Form>

                    <Item
                        v-if="isPracticeMode"
                        variant="outline"
                        size="sm"
                        class="ml-auto"
                    >
                        <ItemMedia>
                            <Checkbox
                                id="practice-auto-advance-toggle"
                                :model-value="autoAdvanceEnabled"
                                @update:model-value="updateAutoAdvanceEnabled"
                                :disabled="isAutoNavigating"
                            />
                        </ItemMedia>
                        <ItemContent>
                            <Label
                                for="practice-auto-advance-toggle"
                                class="cursor-pointer text-sm text-muted-foreground"
                            >
                                {{ autoAdvanceToggleLabel }}
                            </Label>
                        </ItemContent>
                        <ItemActions>
                            <Play class="size-3.5" />
                        </ItemActions>
                    </Item>

                    <p
                        v-if="autoAdvanceSaveError"
                        class="w-full text-xs text-amber-700 dark:text-amber-300"
                    >
                        {{ autoAdvanceSaveError }}
                    </p>
                </CardFooter>
            </Card>

            <Card
                v-if="showsInstantFeedback && feedback"
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
                    <CardDescription v-if="showsFeedbackExplanation">
                        {{ feedback.explanation }}
                    </CardDescription>
                </CardHeader>
                <CardContent
                    v-if="showsFeedbackExplanation && correctOptionsText.length > 0"
                >
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
