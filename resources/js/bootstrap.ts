import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

// Public connection settings are supplied at runtime so one image can be promoted.
const reverb = JSON.parse(document.querySelector('meta[name="opts-reverb"]')?.getAttribute('content') ?? '{}') as {
    key?: string; host?: string; port?: number; scheme?: string;
};
const reverbKey = reverb.key ?? import.meta.env.VITE_REVERB_APP_KEY;
if (reverbKey) window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: reverb.host ?? import.meta.env.VITE_REVERB_HOST,
    wsPort: reverb.port ?? import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: reverb.port ?? import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (reverb.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
