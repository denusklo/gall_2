/**
 * In-memory bearer API token shared by every bundle on the page (fcm.js,
 * notifications.js, the Vue entry apps). The token is never persisted: it is
 * fetched on demand from /apiv/_1/token using the session cookie.
 *
 * Each webpack entry has its own module scope, so shared state (the token and
 * the in-flight refresh promise) lives on `window`.
 */

// Tokens used to be persisted in localStorage; drop any leftovers (one-time migration).
['api_token', 'gallery_2.localhost.dev_token'].forEach((key) => {
    try {
        localStorage.removeItem(key);
    } catch (e) {
        // localStorage unavailable (private mode / blocked): nothing to clean.
    }
});

/** Current in-memory token, or null (does not fetch). */
export function peekApiToken() {
    return window.__apiToken || null;
}

/** Forget the in-memory token (e.g. on logout / 401). */
export function clearApiToken() {
    window.__apiToken = null;
}

/**
 * Fetch a fresh token with the session cookie. Concurrent callers share one
 * request. Resolves with the token, or null when the user is not authenticated.
 */
export function refreshApiToken() {
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
            if (!response.ok) {
                window.__apiToken = null;
                return null;
            }
            const data = await response.json();
            window.__apiToken = data.token || null;
            return window.__apiToken;
        } catch (error) {
            console.error('[ApiToken] Refresh failed:', error);
            return null;
        } finally {
            window.__apiTokenRefreshPromise = null;
        }
    })();

    return window.__apiTokenRefreshPromise;
}

/** Cached token if present, otherwise fetch one. */
export async function getApiToken() {
    return peekApiToken() || refreshApiToken();
}

// For non-bundled inline scripts (e.g. Blade views): `await window.ApiToken.get()`.
window.ApiToken = { get: getApiToken, refresh: refreshApiToken, peek: peekApiToken, clear: clearApiToken };
