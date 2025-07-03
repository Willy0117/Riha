import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createI18n } from 'vue-i18n';
//import messages from "./lang/messages.js";

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {

        const initialLocale = props.initialPage.props.locale || 'ja'; // Laravelから渡されたロケールを優先
        const initialTranslations = props.initialPage.props.language || {}; 

        // vue-i18n のインスタンスを作成
        const i18n = createI18n({
            locale: initialLocale,
            fallbackLocale: 'en',          // フォールバックロケールを設定
            legacy: false,                 // Vue 3 Composition API で使用するために必須
            messages: initialTranslations,
            globalInjection: true,         // コンポーネント内で $t グローバルヘルパーを使えるようにする
        });

        i18n.global.locale.value = initialLocale;

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
