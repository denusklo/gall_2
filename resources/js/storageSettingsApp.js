// resources/js/storageSettingsApp.js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import StorageSettings from './components/StorageSettings.vue';
import { useStorageSettingsStore } from './stores/storageSettings';

// Set up axios defaults
const token = localStorage.getItem('gallery_2.localhost.dev_token');
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

// Token refresh on visibility change
let lastTokenRefresh = Date.now();
const TOKEN_REFRESH_INTERVAL = 15 * 60 * 1000; // 15 minutes

document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState === 'visible' && Date.now() - lastTokenRefresh > TOKEN_REFRESH_INTERVAL) {
        try {
            const response = await axios.get('/apiv/_1/token');
            const newToken = response.data.token;
            localStorage.setItem('gallery_2.localhost.dev_token', newToken);
            axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
            lastTokenRefresh = Date.now();
        } catch (error) {
            console.error('Failed to refresh token:', error);
            // Redirect to login if token refresh fails
            if (error.response?.status === 401 || error.response?.status === 419) {
                window.location.href = '/login';
            }
        }
    }
});

// Create Vue app
const app = createApp({
    setup() {
        const store = useStorageSettingsStore();

        return {
            store
        };
    }
});

// Use Pinia
app.use(createPinia());

// Register component
app.component('storage-settings', StorageSettings);

// Mount app
app.mount('#storage-settings-app');
