import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, Fragment, h } from 'vue';
import '../css/app.css';
import GlobalNavigationProgress from './components/shared/GlobalNavigationProgress.vue';
import GlobalPopupHost from './components/shared/GlobalPopupHost.vue';
import GlobalToastProvider from './components/shared/GlobalToastProvider.vue';
import { initializeTheme } from './composables/useAppearance';
import { activeLocale, translate, translationDirective } from './composables/useI18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${translate(title, activeLocale.value)} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        activeLocale.value = props.initialPage.props.locale === 'en' ? 'en' : 'id';
        document.documentElement.lang = activeLocale.value;
        const vueApp = createApp({
            render: () =>
                h(Fragment, null, [
                    h(App, props),
                    h(GlobalNavigationProgress),
                    h(GlobalPopupHost),
                    h(GlobalToastProvider),
                ]),
        }).use(plugin);
        vueApp.directive('translate', translationDirective);
        vueApp.mount(el);
    },
});

// This will set light / dark mode on page load...
initializeTheme();
