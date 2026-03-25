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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Resultado',
        href: dashboard(),
    },
];

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
    <Head title="Resultado de práctica" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-2xl">Sesión finalizada</CardTitle>
                    <CardDescription>
                        Tu score final es
                        <span class="font-semibold text-foreground">
                            {{ scoreValue }}%
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            Correctas
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ correct_count }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            Incorrectas
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ incorrect_count }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3">
                        <p class="text-xs uppercase text-muted-foreground">
                            Total
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
                        Duración aproximada: {{ durationInMinutes }} minuto(s)
                    </p>
                    <p v-else>Duración no disponible</p>
                </CardFooter>
            </Card>

            <div class="flex flex-wrap gap-3">
                <Form
                    v-bind="start.form()"
                    v-slot="{ processing }"
                    class="w-full sm:w-auto"
                >
                    <Button type="submit" class="w-full sm:w-auto">
                        {{ processing ? 'Iniciando...' : 'Volver a intentar' }}
                    </Button>
                </Form>

                <Button variant="secondary" as-child class="w-full sm:w-auto">
                    <Link :href="dashboard()">Ir al dashboard</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
