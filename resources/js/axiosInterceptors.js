import axios from 'axios';
import { peekApiToken, refreshApiToken, clearApiToken } from './apiTokenRefresh';

let registered = false;

function showSessionExpiredBanner() {
    if (document.getElementById('session-expired-banner')) return;
    const banner = document.createElement('div');
    banner.id = 'session-expired-banner';
    banner.setAttribute('role', 'alert');
    banner.className = 'alert alert-warning d-flex justify-content-between align-items-center';
    banner.style.cssText = 'position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:2000;max-width:90%;';

    const text = document.createElement('span');
    text.textContent = 'Your session has expired. Please refresh the page.';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-warning ml-3';
    btn.textContent = 'Refresh';
    btn.addEventListener('click', () => window.location.reload());

    banner.append(text, btn);
    document.body.appendChild(banner);
}

/**
 * Register the shared auth handling once. Call BEFORE app.mount() so requests
 * fired from mounted hooks are covered.
 *  - request: attach the in-memory bearer token (fetched via the session cookie)
 *  - 401: refresh the token once and retry; if the session itself is gone, go to /login
 *  - 419: non-blocking "session expired" banner
 */
export function registerAuthInterceptor() {
    if (registered) return;
    registered = true;

    axios.interceptors.request.use((config) => {
        const token = peekApiToken();
        if (token) {
            config.headers = config.headers || {};
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    });

    axios.interceptors.response.use(
        response => response,
        async (error) => {
            const status = error.response?.status;
            const config = error.config;
            const url = config?.url || '';

            if (status === 401 && config && !url.includes('/apiv/_1/token')) {
                if (!config._authRetried) {
                    config._authRetried = true;
                    const token = await refreshApiToken();
                    if (token) {
                        config.headers = { ...(config.headers || {}), Authorization: `Bearer ${token}` };
                        return axios(config);
                    }
                }
                clearApiToken();
                window.location.href = '/login';
            } else if (status === 419) {
                showSessionExpiredBanner();
            }
            return Promise.reject(error);
        }
    );
}
