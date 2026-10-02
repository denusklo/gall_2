/**
 * Single in-flight API token refresh shared by every bundle on the page
 * (fcm.js, notifications.js, ...). The promise lives on `window` because each
 * webpack entry has its own module scope.
 * Resolves with the new token, or null when the user is not authenticated.
 */
export function refreshSharedApiToken(storageKey = 'api_token') {
    if (window.__apiTokenRefreshPromise) {
        return window.__apiTokenRefreshPromise;
    }

    window.__apiTokenRefreshPromise = (async () => {
        try {
            const response = await fetch('/apiv/_1/token', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            if (!response.ok) return null;
            const data = await response.json();
            if (!data.token) return null;
            localStorage.setItem(storageKey, data.token);
            return data.token;
        } catch (error) {
            console.error('[ApiToken] Refresh failed:', error);
            return null;
        } finally {
            window.__apiTokenRefreshPromise = null;
        }
    })();

    return window.__apiTokenRefreshPromise;
}
