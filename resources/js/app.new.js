// resources/js/app.js - Update with optimized loading
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import GalleryIndex from './components/Gallery/GalleryIndex.vue';
import axios from 'axios';

// Set up Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Create Pinia (the store)
const pinia = createPinia();

// Create the gallery app when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const galleryElement = document.getElementById('gallery-app');
    
    if (galleryElement) {
        const app = createApp(GalleryIndex);
        app.use(pinia);
        app.mount('#gallery-app');
        
        // Add global error handler for Axios
        axios.interceptors.response.use(
            response => response,
            error => {
                // Handle 401 Unauthorized responses
                if (error.response && error.response.status === 401) {
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