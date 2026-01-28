/**
 * Firebase Cloud Messaging (FCM) Setup
 * This file handles FCM notification permission and token registration
 */

// Firebase configuration - these should be loaded from environment
const firebaseConfig = {
    apiKey: window.FIREBASE_CONFIG?.apiKey || '',
    authDomain: window.FIREBASE_CONFIG?.authDomain || '',
    databaseURL: window.FIREBASE_CONFIG?.databaseURL || '',
    projectId: window.FIREBASE_CONFIG?.projectId || '',
    storageBucket: window.FIREBASE_CONFIG?.storageBucket || '',
    messagingSenderId: window.FIREBASE_CONFIG?.messagingSenderId || '',
    appId: window.FIREBASE_CONFIG?.appId || '',
    measurementId: window.FIREBASE_CONFIG?.measurementId || ''
};

// FCM Service
const FcmService = {
    messaging: null,
    token: null,

    /**
     * Initialize FCM
     */
    async init() {
        try {
            // Check if Firebase is loaded
            if (typeof firebase === 'undefined') {
                console.warn('[FCM] Firebase SDK not loaded');
                return false;
            }

            // Check if service worker is supported
            if (!('serviceWorker' in navigator)) {
                console.error('[FCM] Service Worker not supported');
                return false;
            }

            // Register service worker
            console.log('[FCM] Registering service worker...');
            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                scope: '/'
            });
            console.log('[FCM] Service Worker registered:', registration);

            // Wait for service worker to be ready
            await navigator.serviceWorker.ready;
            console.log('[FCM] Service Worker is ready and active');

            // Initialize Firebase
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }

            // Get messaging instance
            this.messaging = firebase.messaging();

            // Handle incoming messages
            this.setupMessageHandler();

            // Test authentication first
            await this.testAuth();

            // Request permission and get token
            await this.requestPermissionAndGetToken();

            return true;
        } catch (error) {
            console.error('[FCM] Initialization failed:', error);
            return false;
        }
    },

    /**
     * Request notification permission and get token
     */
    async requestPermissionAndGetToken() {
        try {
            // Request permission
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                console.log('Notification permission denied');
                return false;
            }

            // Get FCM token
            const currentToken = await this.messaging.getToken({
                vapidKey: window.FIREBASE_CONFIG?.vapidKey || ''
            });

            if (!currentToken) {
                console.log('No FCM token available');
                return false;
            }

            // Save token
            this.token = currentToken;

            // Register token with server
            await this.registerTokenWithServer(currentToken);

            return true;
        } catch (error) {
            console.error('Error getting FCM token:', error);
            return false;
        }
    },

    /**
     * Refresh API token from server
     */
    async refreshApiToken() {
        try {
            console.log('[FCM] Refreshing API token...');
            const response = await fetch('/apiv/_1/token', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin' // Include cookies for session auth
            });

            if (response.ok) {
                const data = await response.json();
                const newToken = data.token;
                localStorage.setItem(this.getApiKey(), newToken);
                console.log('[FCM] API token refreshed successfully');
                console.log('[FCM] New token preview:', newToken.substring(0, 20) + '...');
                return newToken;
            } else {
                console.error('[FCM] Failed to refresh token. Status:', response.status);
                return null;
            }
        } catch (error) {
            console.error('[FCM] Error refreshing token:', error);
            return null;
        }
    },

    /**
     * Test authentication before registering FCM token
     */
    async testAuth() {
        try {
            let apiToken = localStorage.getItem(this.getApiKey());
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            console.log('[FCM] Testing authentication...');
            console.log('[FCM] Token length:', apiToken ? apiToken.length : 0);
            console.log('[FCM] Token preview:', apiToken ? apiToken.substring(0, 20) + '...' : 'null');

            const response = await fetch('/apiv/_1/test-auth', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                console.log('[FCM] Auth test successful:', data);
                return true;
            } else if (response.status === 401) {
                // Token is invalid/expired, try to refresh
                console.warn('[FCM] Token invalid, attempting refresh...');
                const newToken = await this.refreshApiToken();

                if (newToken) {
                    // Retry auth test with new token
                    const retryResponse = await fetch('/apiv/_1/test-auth', {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${newToken}`,
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (retryResponse.ok) {
                        const data = await retryResponse.json();
                        console.log('[FCM] Auth test successful after refresh:', data);
                        return true;
                    }
                }

                console.error('[FCM] Auth failed even after token refresh');
                return false;
            } else {
                const error = await response.text();
                console.error('[FCM] Auth test failed. Status:', response.status);
                console.error('[FCM] Error:', error);
                return false;
            }
        } catch (error) {
            console.error('[FCM] Auth test error:', error);
            return false;
        }
    },

    /**
     * Register FCM token with server
     */
    async registerTokenWithServer(token) {
        try {
            // Get API token (should be fresh from testAuth)
            const apiToken = localStorage.getItem(this.getApiKey());

            if (!apiToken) {
                console.warn('[FCM] No API token found, skipping FCM registration');
                return false;
            }

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Get device info
            const deviceInfo = this.getDeviceInfo();

            console.log('[FCM] Registering token with server...');
            console.log('[FCM] API Token present:', !!apiToken);
            console.log('[FCM] API Token preview:', apiToken.substring(0, 20) + '...');
            console.log('[FCM] CSRF Token present:', !!csrfToken);

            // Register token
            const response = await fetch('/apiv/_1/fcm/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    token: token,
                    device_info: deviceInfo
                })
            });

            if (response.ok) {
                const result = await response.json();
                console.log('[FCM] Token registered successfully:', result);
                return true;
            } else {
                const errorText = await response.text();
                console.error('[FCM] Failed to register token. Status:', response.status);
                console.error('[FCM] Response:', errorText);
                return false;
            }
        } catch (error) {
            console.error('[FCM] Error registering FCM token:', error);
            return false;
        }
    },


    /**
     * Setup foreground message handler
     */
    setupMessageHandler() {
        this.messaging.onMessage((payload) => {
            console.log('FCM message received:', payload);

            // Show notification in foreground
            const notification = payload.notification;
            const data = payload.data || {};

            if (notification) {
                // Show in-app notification using iziToast
                iziToast.info({
                    title: notification.title || 'Notification',
                    message: notification.body || '',
                    position: 'topRight',
                    timeout: 5000,
                    buttons: [
                        ['<button>View</button>', (instance, toast) => {
                            // Handle notification click based on type
                            this.handleNotificationClick(data);
                            instance.hide({
                                transitionOut: 'fadeOutUp'
                            }, toast, 'buttonName');
                        }, true]
                    ]
                });
            }
        });
    },

    /**
     * Handle notification click
     */
    handleNotificationClick(data) {
        const type = data.type || '';

        switch (type) {
            case 'request_completed':
            case 'new_completion':
            case 'fully_completed':
                // Redirect to my requests
                window.location.href = '/requests/my';
                break;
            case 'completion_confirmation':
                // Redirect to all requests
                window.location.href = '/requests/all';
                break;
            default:
                // Default to my requests
                window.location.href = '/requests/my';
        }
    },

    /**
     * Get device information
     */
    getDeviceInfo() {
        const userAgent = navigator.userAgent;
        let browser = 'Unknown';
        let os = 'Unknown';

        // Detect browser
        if (userAgent.includes('Chrome')) browser = 'Chrome';
        else if (userAgent.includes('Firefox')) browser = 'Firefox';
        else if (userAgent.includes('Safari')) browser = 'Safari';
        else if (userAgent.includes('Edge')) browser = 'Edge';

        // Detect OS
        if (userAgent.includes('Windows')) os = 'Windows';
        else if (userAgent.includes('Mac')) os = 'MacOS';
        else if (userAgent.includes('Linux')) os = 'Linux';
        else if (userAgent.includes('Android')) os = 'Android';
        else if (userAgent.includes('iOS')) os = 'iOS';

        return `${os} - ${browser}`;
    },

    /**
     * Get API key name for localStorage
     */
    getApiKey() {
        // API token is stored with simple 'api_token' key
        return 'api_token';
    },

    /**
     * Delete current token (for logout)
     */
    async deleteToken() {
        if (this.token) {
            try {
                await this.messaging.deleteToken(this.token);
                this.token = null;
                console.log('FCM token deleted');
            } catch (error) {
                console.error('Error deleting FCM token:', error);
            }
        }
    }
};

// Initialize FCM when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('[FCM] DOM loaded, checking initialization...');

    // Check if user is authenticated
    const apiToken = localStorage.getItem(FcmService.getApiKey());
    console.log('[FCM] API token found:', !!apiToken);

    console.log('[FCM] Firebase available:', typeof firebase !== 'undefined');
    console.log('[FCM] Firebase config:', window.FIREBASE_CONFIG);

    if (apiToken && typeof firebase !== 'undefined') {
        console.log('[FCM] Initializing FCM service...');
        // Delay initialization to ensure Firebase is ready
        setTimeout(() => {
            FcmService.init();
        }, 1000);
    } else {
        if (!apiToken) {
            console.log('[FCM] Skipping initialization: No API token found (user not authenticated)');
        }
        if (typeof firebase === 'undefined') {
            console.error('[FCM] Skipping initialization: Firebase SDK not loaded');
        }
    }
});

// Make FcmService available globally
window.FcmService = FcmService;
