<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { update as updateUserSettings } from '@/routes/user-settings';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    settings: {
        auto_advance: boolean;
        auto_advance_delay: number;
    };
};

const props = defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.profile_breadcrumb'),
        href: edit(),
    },
]);

const page = usePage();
const user = computed(() => page.props.auth.user);
const autoAdvanceEnabled = ref<boolean>(props.settings.auto_advance);
const autoAdvanceDelay = ref<string>(String(props.settings.auto_advance_delay));
const autoAdvanceDelayError = ref<string | undefined>(undefined);
const autoAdvanceSaveError = ref<string | null>(null);
const isSavingUserSettings = ref(false);
const autoAdvanceRecentlySaved = ref(false);
let autoAdvanceSavedTimeoutId: ReturnType<typeof setTimeout> | null = null;

function clearAutoAdvanceSavedTimeout(): void {
    if (autoAdvanceSavedTimeoutId !== null) {
        clearTimeout(autoAdvanceSavedTimeoutId);
        autoAdvanceSavedTimeoutId = null;
    }
}

onBeforeUnmount(clearAutoAdvanceSavedTimeout);

function updateAutoAdvanceValue(value: boolean | 'indeterminate'): void {
    autoAdvanceEnabled.value = value === true;
}

async function savePracticeSettings(): Promise<void> {
    if (isSavingUserSettings.value) {
        return;
    }

    isSavingUserSettings.value = true;
    autoAdvanceDelayError.value = undefined;
    autoAdvanceSaveError.value = null;

    try {
        const csrfToken = document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content;
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(updateUserSettings.url(), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({
                auto_advance: autoAdvanceEnabled.value,
                auto_advance_delay: Number(autoAdvanceDelay.value),
            }),
        });

        if (response.status === 422) {
            const payload = (await response.json()) as {
                errors?: { auto_advance_delay?: string[] };
            };

            autoAdvanceDelayError.value = payload.errors?.auto_advance_delay?.[0];

            return;
        }

        if (!response.ok) {
            throw new Error('Could not save practice settings.');
        }

        const payload = (await response.json()) as {
            settings?: { auto_advance?: boolean; auto_advance_delay?: number };
        };

        autoAdvanceEnabled.value = payload.settings?.auto_advance ?? autoAdvanceEnabled.value;
        autoAdvanceDelay.value = String(
            payload.settings?.auto_advance_delay ?? Number(autoAdvanceDelay.value),
        );

        autoAdvanceRecentlySaved.value = true;
        clearAutoAdvanceSavedTimeout();
        autoAdvanceSavedTimeoutId = setTimeout(() => {
            autoAdvanceRecentlySaved.value = false;
            autoAdvanceSavedTimeoutId = null;
        }, 1500);
    } catch {
        autoAdvanceSaveError.value = trans('settings.practice_save_error');
    } finally {
        isSavingUserSettings.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="trans('settings.profile_head_title')" />

        <h1 class="sr-only">{{ trans('settings.profile_sr_title') }}</h1>

        <div class="mx-auto w-full max-w-5xl">
            <SettingsLayout>
                <div class="flex flex-col space-y-6">
                    <Heading
                        variant="small"
                        :title="trans('settings.profile_information_title')"
                        :description="trans('settings.profile_information_description')"
                    />

                    <Form
                        v-bind="ProfileController.update.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">{{ trans('auth.name_label') }}</Label>
                            <Input
                                id="name"
                                class="mt-1 block w-full"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                :placeholder="trans('settings.profile_name_placeholder')"
                            />
                            <InputError class="mt-2" :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">{{ trans('auth.email_address_label') }}</Label>
                            <Input
                                id="email"
                                type="email"
                                class="mt-1 block w-full"
                                name="email"
                                :default-value="user.email"
                                required
                                autocomplete="username"
                                :placeholder="trans('settings.profile_email_placeholder')"
                            />
                            <InputError class="mt-2" :message="errors.email" />
                        </div>

                        <div v-if="mustVerifyEmail && !user.email_verified_at">
                            <p class="-mt-4 text-sm text-muted-foreground">
                                {{ trans('settings.profile_email_unverified_message') }}
                                <Link
                                    :href="send()"
                                    as="button"
                                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                >
                                    {{ trans('settings.profile_resend_verification_link') }}
                                </Link>
                            </p>

                            <div
                                v-if="status === 'verification-link-sent'"
                                class="mt-2 text-sm font-medium text-green-600"
                            >
                                {{ trans('settings.profile_verification_sent_status') }}
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button
                                :disabled="processing"
                                data-test="update-profile-button"
                                >{{ trans('settings.save_button') }}</Button
                            >

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySuccessful"
                                    class="text-sm text-neutral-600"
                                >
                                    {{ trans('settings.saved_message') }}
                                </p>
                            </Transition>
                        </div>
                    </Form>
                </div>

                <div class="space-y-6">
                    <Heading
                        variant="small"
                        :title="trans('settings.practice_title')"
                        :description="trans('settings.practice_description')"
                    />

                    <form class="space-y-6" @submit.prevent="savePracticeSettings">
                        <div class="space-y-2">
                            <Label
                                for="settings-auto-advance"
                                class="hover:bg-accent/50 flex items-center gap-3 rounded-lg border p-3 transition-colors has-[[aria-checked=true]]:border-primary/60 has-[[aria-checked=true]]:bg-primary/5"
                            >
                                <Checkbox
                                    id="settings-auto-advance"
                                    :model-value="autoAdvanceEnabled"
                                    :disabled="isSavingUserSettings"
                                    class="data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                                    @update:model-value="updateAutoAdvanceValue"
                                />
                                <span class="text-sm leading-none font-medium">
                                    {{ trans('settings.practice_auto_advance_label') }}
                                </span>
                            </Label>
                            <p class="text-sm text-muted-foreground">
                                {{ trans('settings.practice_auto_advance_help') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="settings-auto-advance-delay">
                                {{ trans('settings.practice_auto_advance_delay_label') }}
                            </Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    id="settings-auto-advance-delay"
                                    v-model="autoAdvanceDelay"
                                    type="number"
                                    min="1"
                                    max="30"
                                    step="1"
                                    inputmode="numeric"
                                    class="w-28"
                                    :disabled="isSavingUserSettings || !autoAdvanceEnabled"
                                />
                                <span class="text-sm text-muted-foreground">
                                    {{ trans('settings.practice_auto_advance_delay_suffix') }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ trans('settings.practice_auto_advance_delay_help') }}
                            </p>
                            <InputError :message="autoAdvanceDelayError" />
                        </div>

                    <p
                        v-if="autoAdvanceSaveError"
                        class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ autoAdvanceSaveError }}
                    </p>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="isSavingUserSettings">
                            {{
                                isSavingUserSettings
                                    ? trans('settings.practice_save_button_loading')
                                    : trans('settings.practice_save_button')
                            }}
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="autoAdvanceRecentlySaved" class="text-sm text-neutral-600">
                                {{ trans('settings.saved_message') }}
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
        </div>
    </AppLayout>
</template>
