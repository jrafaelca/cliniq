<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ShieldCheck } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.security_breadcrumb'),
        href: edit(),
    },
]);

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="trans('settings.security_head_title')" />

        <h1 class="sr-only">{{ trans('settings.security_sr_title') }}</h1>

        <div class="mx-auto w-full max-w-5xl">
            <SettingsLayout>
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        :title="trans('settings.update_password_title')"
                        :description="trans('settings.update_password_description')"
                    />

                    <Form
                        v-bind="SecurityController.update.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        reset-on-success
                        :reset-on-error="[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]"
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-2">
                            <Label for="current_password">{{
                                trans('settings.current_password_label')
                            }}</Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="mt-1 block w-full"
                                autocomplete="current-password"
                                :placeholder="trans('settings.current_password_placeholder')"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">{{
                                trans('settings.new_password_label')
                            }}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                                :placeholder="trans('settings.new_password_placeholder')"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation"
                                >{{ trans('auth.confirm_password_label') }}</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                                :placeholder="trans('auth.confirm_password_placeholder')"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button
                                :disabled="processing"
                                data-test="update-password-button"
                            >
                                {{ trans('settings.save_password_button') }}
                            </Button>

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

                <div v-if="canManageTwoFactor" class="space-y-6">
                    <Heading
                        variant="small"
                        :title="trans('two_factor.heading_title')"
                        :description="trans('two_factor.heading_description')"
                    />

                    <div
                        v-if="!twoFactorEnabled"
                        class="flex flex-col items-start justify-start space-y-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ trans('two_factor.enable_message') }}
                        </p>

                        <div>
                            <Button
                                v-if="hasSetupData"
                                @click="showSetupModal = true"
                            >
                                <ShieldCheck />
                                {{ trans('two_factor.continue_setup_button') }}
                            </Button>
                            <Form
                                v-else
                                v-bind="enable.form()"
                                @success="showSetupModal = true"
                                #default="{ processing }"
                            >
                                <Button type="submit" :disabled="processing">
                                    {{ trans('two_factor.enable_button') }}
                                </Button>
                            </Form>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-start justify-start space-y-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ trans('two_factor.disable_message') }}
                        </p>

                        <div class="relative inline">
                            <Form v-bind="disable.form()" #default="{ processing }">
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    :disabled="processing"
                                >
                                    {{ trans('two_factor.disable_button') }}
                                </Button>
                            </Form>
                        </div>

                        <TwoFactorRecoveryCodes />
                    </div>

                    <TwoFactorSetupModal
                        v-model:isOpen="showSetupModal"
                        :requiresConfirmation="requiresConfirmation"
                        :twoFactorEnabled="twoFactorEnabled"
                    />
                </div>
            </SettingsLayout>
        </div>
    </AppLayout>
</template>
