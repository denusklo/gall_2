// resources/js/storageSettingsApp.js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import StorageSettings from './components/StorageSettings.vue';

// Set up Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Create Pinia (the store)
const pinia = createPinia();

// Function to get API token for authenticated requests
async function getApiToken() {
    try {
        const response = await axios.get('/apiv/_1/token');
        const token = response.data.token;
        localStorage.setItem('gallery_2.localhost.dev_token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        return token;
    } catch (error) {
        console.error('Failed to get API token:', error);
        return null;
    }
}

// Create the storage settings app when DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    const storageSettingsElement = document.getElementById('storage-settings-app');

    if (storageSettingsElement) {
        // Try to get token before initializing app
        await getApiToken();

        const app = createApp(StorageSettings);

        // Use Pinia
        app.use(pinia);

        // Mount app
        app.mount('#storage-settings-app');

        // Add global error handler for Axios
        axios.interceptors.response.use(
            response => response,
            error => {
                // Handle 401 Unauthorized responses
                if (error.response && error.response.status === 401) {
                    // Clear token and redirect to login
                    localStorage.removeItem('gallery_2.localhost.dev_token');
                    window.location.href = '/login';
                    return Promise.reject(error);
                }

                // Handle 419 CSRF token expired
                if (error.response && error.response.status === 419) {
                    alert('Your session has expired. Please refresh the page.');
                    return Promise.reject(error);
                }

                return Promise.reject(error);
            }
        );
    }
});

// Token refresh on visibility change
let lastTokenRefresh = Date.now();
const TOKEN_REFRESH_INTERVAL = 15 * 60 * 1000; // 15 minutes

function setupTokenRefresh() {
    // Check for existing token
    const token = localStorage.getItem('gallery_2.localhost.dev_token');
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }

    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible' && Date.now() - lastTokenRefresh > TOKEN_REFRESH_INTERVAL) {
            await getApiToken();
            lastTokenRefresh = Date.now();
        }
    });
}

// Initialize token handling
setupTokenRefresh();
