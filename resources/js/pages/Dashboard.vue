<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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
import { show, start } from '@/routes/practice';
import type { BreadcrumbItem } from '@/types';

type LatestResult = {
    attemptId: number;
    score: number | string;
    correct_count: number;
    incorrect_count: number;
    total_questions: number;
    finished_at: string | null;
};

type Props = {
    activeAttemptId: number | null;
    hasQuestions: boolean;
    practiceError: string | null;
    latestResult: LatestResult | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const latestScore = computed(() => {
    if (!props.latestResult) {
        return null;
    }

    return Math.round(Number(props.latestResult.score));
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Entrenamiento de práctica</CardTitle>
                    <CardDescription>
                        Responde preguntas con feedback inmediato y mide tu
                        progreso.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
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
                        Aún no hay preguntas cargadas para iniciar la práctica.
                    </p>
                </CardContent>
                <CardFooter class="flex gap-3">
                    <Button
                        v-if="activeAttemptId"
                        as-child
                        class="w-full sm:w-auto"
                    >
                        <Link :href="show(activeAttemptId)">
                            Reanudar práctica
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
                            Comenzar práctica
                        </Button>
                    </Form>
                </CardFooter>
            </Card>

            <Card v-if="latestResult">
                <CardHeader>
                    <CardTitle>Último resultado</CardTitle>
                    <CardDescription>
                        Terminaste esta sesión con
                        <span class="font-semibold text-foreground">
                            {{ latestScore }}%
                        </span>
                        de aciertos.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            Correctas
                        </p>
                        <p class="text-xl font-semibold">
                            {{ latestResult.correct_count }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            Incorrectas
                        </p>
                        <p class="text-xl font-semibold">
                            {{ latestResult.incorrect_count }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            Total
                        </p>
                        <p class="text-xl font-semibold">
                            {{ latestResult.total_questions }}
                        </p>
                    </div>
                </CardContent>
                <CardFooter>
                    <Button variant="secondary" as-child>
                        <Link :href="show(latestResult.attemptId)">
                            Ver detalle
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
            <div
                v-else
                class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
            >
                Todavía no tienes resultados finalizados. Inicia tu primera
                práctica para ver tu score.
            </div>
        </div>
    </AppLayout>
</template>
