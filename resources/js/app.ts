import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'App';
const appLocale = import.meta.env.VITE_APP_LOCALE || 'es';
const languages = import.meta.glob(
    '../../lang/*.json',
) as Record<string, () => Promise<{ default: Record<string, string> }>>;
const normalizeLocale = (locale: string): string =>
    locale.replace('_', '-').toLowerCase();
const i18nLoadTimeoutMs = 1500;

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    async setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) }).use(plugin);
        const targetLocale = normalizeLocale(appLocale);

        let markI18nReady: (() => void) | undefined;
        const i18nReady = new Promise<void>((resolve) => {
            markI18nReady = resolve;
        });

        app.use(i18nVue, {
                lang: appLocale,
                fallbackLang: 'en',
                fallbackMissingTranslations: true,
                onLoad: (loadedLocale: string) => {
                    if (normalizeLocale(loadedLocale) === targetLocale) {
                        markI18nReady?.();
                        markI18nReady = undefined;
                    }
                },
                resolve: async (lang: string) =>
                    (await languages[`../../lang/${lang}.json`]?.()) ?? {
                        default: {},
                    },
            });

        await Promise.race([
            i18nReady,
            new Promise<void>((resolve) =>
                setTimeout(resolve, i18nLoadTimeoutMs),
            ),
        ]);

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
