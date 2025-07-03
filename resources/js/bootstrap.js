// resources/js/bootstrap.js

import _ from 'lodash';
window._ = _;

//import * as Popper from '@popperjs/core';
//window.Popper = Popper;

//import 'bootstrap'; // もしBootstrap CSSフレームワークを使っていなければ、この行はなくてもOK

/**
 * We'll load the axios HTTP library which provides a simple interface
 * for making HTTP requests. This library is automatically loaded in
 * this application's JavaScript.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * makes it easy to build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     wsHost: import.meta.env.VITE_PUSHER_APP_HOST ? import.meta.env.VITE_PUSHER_APP_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_APP_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_APP_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_APP_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });