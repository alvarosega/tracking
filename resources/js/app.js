import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { Ziggy } from './ziggy';

const activeZiggy = typeof window !== 'undefined' && window.Ziggy ? window.Ziggy : Ziggy;

if (typeof window !== 'undefined') {
    activeZiggy.url = window.location.origin;
}

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, activeZiggy)
            .mount(el);
    },
});