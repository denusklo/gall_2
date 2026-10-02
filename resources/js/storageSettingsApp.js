// resources/js/storageSettingsApp.js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import { registerAuthInterceptor } from './axiosInterceptors';
import { refreshApiToken } from './apiTokenRefresh';
import StorageSettings from './components/StorageSettings.vue';

// Set up Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Create Pinia (the store)
const pinia = createPinia();

// Fetch the API token into memory (session-cookie auth); the axios request
// interceptor attaches it to every request. Nothing is persisted.
async function getApiToken() {
    return refreshApiToken();
}

// Create the storage settings app when DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    const storageSettingsElement = document.getElementById('storage-settings-app');

    if (storageSettingsElement) {
        // Try to get token before initializing app
        await getApiToken();

        registerAuthInterceptor();
        const app = createApp(StorageSettings);

        // Use Pinia
        app.use(pinia);

        // Mount app
        app.mount('#storage-settings-app');

    }
});

// Token refresh on visibility change
let lastTokenRefresh = Date.now();
const TOKEN_REFRESH_INTERVAL = 15 * 60 * 1000; // 15 minutes

function setupTokenRefresh() {
document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible' && Date.now() - lastTokenRefresh > TOKEN_REFRESH_INTERVAL) {
            await getApiToken();
            lastTokenRefresh = Date.now();
        }
    });
}

// Initialize token handling
setupTokenRefresh();
