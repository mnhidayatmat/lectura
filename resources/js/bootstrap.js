import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo + Reverb for real-time broadcasting
 * Only initialise when Reverb key is configured
 */
if (import.meta.env.VITE_REVERB_APP_KEY) {
    import('pusher-js').then((Pusher) => {
        window.Pusher = Pusher.default;

        import('laravel-echo').then((Echo) => {
            window.Echo = new Echo.default({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        });
    });
}
