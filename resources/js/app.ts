import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, Fragment, h } from 'vue';
import '../css/app.css';
import GlobalNavigationProgress from './components/shared/GlobalNavigationProgress.vue';
import GlobalPopupHost from './components/shared/GlobalPopupHost.vue';
import GlobalToastProvider from './components/shared/GlobalToastProvider.vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () =>
                h(Fragment, null, [h(App, props), h(GlobalNavigationProgress), h(GlobalPopupHost), h(GlobalToastProvider)]),
        })
            .use(plugin)
            .mount(el);
    },
});

// This will set light / dark mode on page load...
initializeTheme();
