// /**
//  * We'll load the axios HTTP library which allows us to easily issue requests
//  * to our Laravel back-end. This library automatically handles sending the
//  * CSRF token as a header based on the value of the "XSRF" token cookie.
//  */

// import axios from 'axios';
// window.axios = axios;

// window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// /**
//  * Echo exposes an expressive API for subscribing to channels and listening
//  * for events that are broadcast by Laravel. Echo and event broadcasting
//  * allows your team to easily build robust real-time web applications.
//  */

// import Echo from 'laravel-echo';
// import { Reverb } from 'reverb';

// // Initialize Reverb (NOT Pusher)
// window.Echo = new Echo({
//     broadcaster: Reverb,
//     key: import.meta.env.VITE_REVERB_APP_KEY,
//     wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
//     wsPort: import.meta.env.VITE_REVERB_PORT || 6001,
//     wssPort: import.meta.env.VITE_REVERB_PORT || 6001,
//     forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
//     auth: {
//         headers: {
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
//         }
//     }
// });

// console.log('✅ Echo initialized with Reverb');

// // Dispatch event when Echo is ready
// document.addEventListener('DOMContentLoaded', function() {
//     setTimeout(() => {
//         document.dispatchEvent(new Event('echo:ready'));
//         console.log('🚀 Echo ready event dispatched');
//     }, 100);
// });