import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { MotionPlugin } from '@vueuse/motion';

const inertiaRoot = document.getElementById('app');

if (inertiaRoot && inertiaRoot.dataset.page) {
    createInertiaApp({
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
            const page = pages[`./Pages/${name}.vue`];

            if (!page) {
                throw new Error(`Inertia page not found: ${name}`);
            }

            return page.default;
        },
        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(MotionPlugin)
                .mount(el);
        },
    });
}
