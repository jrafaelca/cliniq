import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'App';
const defaultLocale = import.meta.env.VITE_APP_LOCALE || 'es';
const languages = import.meta.glob('../../lang/*.json', {
    eager: true,
}) as Record<string, { default: Record<string, string> }>;
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

createServer(
    (page) => {
        const pageLocale =
            (page.props as { locale?: string }).locale ?? defaultLocale;
        const appLocale = resolveLocale(pageLocale);

        return createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(i18nVue, {
                        lang: appLocale,
                        fallbackLang: 'en',
                        fallbackMissingTranslations: true,
                        resolve: (lang: string) =>
                            languages[`../../lang/${resolveLocale(lang)}.json`]
                                ?.default ?? {},
                    }),
        });
    },
    { cluster: true },
);
