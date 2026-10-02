require('./bootstrap');
// resources/js/app.js - Update with optimized loading and auth token retrieval
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import GalleryIndex from './components/Gallery/GalleryIndex.vue';
import axios from 'axios';
import { registerAuthInterceptor } from './axiosInterceptors';
import { refreshApiToken } from './apiTokenRefresh';

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

// Create the gallery app when the DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    const galleryElement = document.getElementById('gallery-app');
    
    if (galleryElement) {
        // Try to get token before initializing app
        await getApiToken();
        
        registerAuthInterceptor();
        const app = createApp(GalleryIndex);
        app.use(pinia);
        app.mount('#gallery-app');

        // Reinitialize Bootstrap dropdowns after Vue app mounts
        if (window.initializeBootstrapDropdowns) {
            window.initializeBootstrapDropdowns();
        }
        
    }
});

// Add a function to check token on page load/refresh
function setupTokenRefresh() {
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