import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Dashboard from './pages/Bi/Dashboard.vue';
import './bi.css';
import './bi-refined.css';

createInertiaApp({
    resolve: (name) => name === 'Bi/Dashboard' ? Dashboard : Promise.reject(new Error(`Página BI desconocida: ${name}`)),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
        if (el.firstElementChild) {
            document.getElementById('bi-loading')?.remove();
        }
    },
});
