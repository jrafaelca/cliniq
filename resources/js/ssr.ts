import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'App';
const appLocale = import.meta.env.VITE_APP_LOCALE || 'es';

createServer(
    (page) =>
        createInertiaApp({
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
                        resolve: (lang: string) => {
                            const langs = import.meta.glob('../../lang/*.json', {
                                eager: true,
                            }) as Record<string, { default: Record<string, string> }>;

                            return langs[`../../lang/${lang}.json`].default;
                        },
                    }),
        }),
    { cluster: true },
);
