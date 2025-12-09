require('./bootstrap');
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import GalleriesIndex from './components/Gallery/GalleriesIndex.vue';
import axios from 'axios';

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
        localStorage.setItem('api_token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        return token;
    } catch (error) {
        console.error('Failed to get API token:', error);
        return null;
    }
}

// Create the galleries app when the DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    const galleriesElement = document.getElementById('galleries-app');

    if (galleriesElement) {
        // Try to get token before initializing app
        await getApiToken();

        const app = createApp(GalleriesIndex);
        app.use(pinia);
        app.mount('#galleries-app');

        // Add global error handler for Axios
        axios.interceptors.response.use(
            response => response,
            error => {
                // Handle 401 Unauthorized responses
                if (error.response && error.response.status === 401) {
                    // Clear token and redirect to login
                    localStorage.removeItem('api_token');
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

// Add a function to check token on page load/refresh
function setupTokenRefresh() {
    // Check for existing token
    const token = localStorage.getItem('api_token');
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }

    // Track last refresh time
    let lastRefresh = Date.now();
    const REFRESH_INTERVAL = 15 * 60 * 1000; // 15 minutes in milliseconds

    // Set up token refresh with time-based check
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            // Only refresh if sufficient time has passed
            const now = Date.now();
            if (now - lastRefresh > REFRESH_INTERVAL) {
                lastRefresh = now;
                getApiToken();
            }
        }
    });
}

// Initialize token handling
setupTokenRefresh();
