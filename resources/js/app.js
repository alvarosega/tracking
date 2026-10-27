import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { Ziggy } from './ziggy';
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

if (typeof document !== 'undefined') {
    const metaToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = metaToken;
    }

    window.axios.interceptors.request.use((config) => {
        const token = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
        }
        return config;
    });
}

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