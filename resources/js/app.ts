import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'App';
const defaultLocale = import.meta.env.VITE_APP_LOCALE || 'es';
const languages = import.meta.glob(
    '../../lang/*.json',
) as Record<string, () => Promise<{ default: Record<string, string> }>>;
const normalizeLocale = (locale: string): string =>
    locale.replace('_', '-').toLowerCase();
const getBaseLocale = (locale: string): string =>
    normalizeLocale(locale).split('-')[0];
const extractLocaleFromPath = (path: string): string | null => {
    const locale = path.match(/\/([^/]+)\.json$/)?.[1];

    if (!locale) {
        return null;
    }

    return normalizeLocale(locale.replace(/^php[_-]/i, ''));
};
const availableLocales = new Set(
    Object.keys(languages)
        .map(extractLocaleFromPath)
        .filter((locale): locale is string => Boolean(locale)),
);
const resolveLocale = (preferredLocale: string): string => {
    const normalizedPreferredLocale = normalizeLocale(preferredLocale);

    if (availableLocales.has(normalizedPreferredLocale)) {
        return normalizedPreferredLocale;
    }

    const basePreferredLocale = getBaseLocale(preferredLocale);

    if (availableLocales.has(basePreferredLocale)) {
        return basePreferredLocale;
    }

    return availableLocales.has('en') ? 'en' : normalizedPreferredLocale;
};

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    async setup({ el, App, props, plugin }) {
        const initialLocale = resolveLocale(
            document.documentElement.lang || defaultLocale,
        );
        const initialLocaleBase = getBaseLocale(initialLocale);

        const app = createApp({ render: () => h(App, props) }).use(plugin);

        let markI18nReady: (() => void) | undefined;
        const i18nReady = new Promise<void>((resolve) => {
            markI18nReady = resolve;
        });

        app.use(i18nVue, {
            lang: initialLocale,
            fallbackLang: 'en',
            fallbackMissingTranslations: true,
            onLoad: (loadedLocale: string) => {
                if (getBaseLocale(loadedLocale) === initialLocaleBase) {
                    markI18nReady?.();
                    markI18nReady = undefined;
                }
            },
            resolve: async (lang: string) =>
                (await languages[`../../lang/${lang}.json`]?.()) ?? {
                    default: {},
                },
        });

        await i18nReady;

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
