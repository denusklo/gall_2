import axios from 'axios';

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
 * Register the shared 401/419 response handling once. Call BEFORE app.mount()
 * so requests fired from mounted hooks are covered.
 * @param {string[]} tokenKeys localStorage keys to clear on 401
 */
export function registerAuthInterceptor(tokenKeys = ['api_token']) {
    if (registered) return;
    registered = true;

    axios.interceptors.response.use(
        response => response,
        error => {
            const status = error.response?.status;
            const url = error.config?.url || '';
            // The token bootstrap call is handled by its own caller.
            if (status === 401 && !url.includes('/apiv/_1/token')) {
                tokenKeys.forEach(key => localStorage.removeItem(key));
                window.location.href = '/login';
            } else if (status === 419) {
                showSessionExpiredBanner();
            }
            return Promise.reject(error);
        }
    );
}
