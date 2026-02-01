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
    swRegistration: null, // Store service worker registration

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
            this.swRegistration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                scope: '/'
            });
            console.log('[FCM] Service Worker registered:', this.swRegistration);

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

            // Use the stored service worker registration
            // This prevents Firebase from creating its own internal SW
            console.log('[FCM] Using SW registration for getToken:', this.swRegistration?.scope);

            // Get FCM token with explicit service worker registration
            // This prevents Firebase from creating its own internal SW
            const currentToken = await this.messaging.getToken({
                vapidKey: window.FIREBASE_CONFIG?.vapidKey || '',
                serviceWorkerRegistration: this.swRegistration
            });

            if (!currentToken) {
                console.log('No FCM token available');
                return false;
            }

            // Save token
            this.token = currentToken;

            // Log current FCM token info
            const domain = window.location.origin;
            const deviceInfo = this.getDeviceInfo();
            console.log('[FCM] ========================================');
            console.log('[FCM] Current FCM Token Info:');
            console.log('[FCM] Token:', currentToken.substring(0, 30) + '...');
            console.log('[FCM] Full Token:', currentToken);
            console.log('[FCM] Domain:', domain);
            console.log('[FCM] Device:', deviceInfo);
            console.log('[FCM] ========================================');

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

            // Get current domain (origin) to ensure notifications only go to this domain
            const domain = window.location.origin;

            console.log('[FCM] Registering token with server...');
            console.log('[FCM] API Token present:', !!apiToken);
            console.log('[FCM] API Token preview:', apiToken.substring(0, 20) + '...');
            console.log('[FCM] CSRF Token present:', !!csrfToken);
            console.log('[FCM] Domain:', domain);

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
                    device_info: deviceInfo,
                    domain: domain
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
        // Detect browser
        const isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
        const isChrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;

        console.log('[FCM] Setting up message handler...');
        console.log('[FCM] Browser: Firefox=' + isFirefox + ', Chrome=' + isChrome);
        console.log('[FCM] User Agent:', navigator.userAgent);

        if (isFirefox) {
            console.warn('[FCM] Firefox detected - FCM may have limited support');
        }

        this.messaging.onMessage(async (payload) => {
            console.log('[FCM] ==================================');
            console.log('[FCM] Message received!', payload);
            console.log('[FCM] Notification:', payload.notification);
            console.log('[FCM] Data:', payload.data);
            console.log('[FCM] ==================================');

            // Verify the notification is for the current user
            const isForCurrentUser = await this.verifyNotificationRecipient(payload);

            if (!isForCurrentUser) {
                console.warn('[FCM] Notification not for current user, ignoring');
                return;
            }

            console.log('[FCM] Notification verified for current user, displaying...');

            // Show notification in foreground
            const notification = payload.notification;
            const data = payload.data || {};

            if (notification) {
                console.log('[FCM] Showing notification toast...');

                // Immediately update notification badge and list
                // This ensures the badge updates in real-time when user is on page
                if (window.NotificationService) {
                    console.log('[FCM] Updating notification service...');
                    window.NotificationService.fetchUnreadCount();
                    window.NotificationService.fetchNotifications();
                }

                // Show in-app notification using iziToast
                if (typeof iziToast !== 'undefined') {
                    console.log('[FCM] Calling iziToast.info()...');
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
                    console.log('[FCM] iziToast.info() called successfully');
                } else {
                    console.error('[FCM] iziToast is not defined!');
                }
            } else {
                console.warn('[FCM] No notification object in payload');
            }
        });

        console.log('[FCM] Message handler setup complete');
    },

    /**
     * Verify the FCM notification is for the currently logged-in user
     * This prevents users from seeing notifications meant for other users
     * when sharing the same browser/device
     */
    async verifyNotificationRecipient(payload) {
        try {
            const apiToken = localStorage.getItem(this.getApiKey());
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Get current user's Firebase UID
            const response = await fetch('/apiv/_1/test-auth', {
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                // Handle 401 - token invalid/expired, try to refresh and retry
                if (response.status === 401) {
                    console.warn('[FCM] Token invalid during verification, attempting refresh...');
                    const newToken = await this.refreshApiToken();

                    if (newToken) {
                        // Retry verification with new token
                        const retryResponse = await fetch('/apiv/_1/test-auth', {
                            headers: {
                                'Authorization': `Bearer ${newToken}`,
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (retryResponse.ok) {
                            const data = await retryResponse.json();
                            const currentFirebaseUid = data.firebase_uid;

                            if (currentFirebaseUid) {
                                // Check if we have the last known Firebase UID stored
                                const lastKnownUid = localStorage.getItem('fcm_last_firebase_uid');

                                // If the Firebase UID has changed, re-register the FCM token
                                if (lastKnownUid !== currentFirebaseUid) {
                                    console.log('[FCM] User changed, re-registering FCM token:', {
                                        old: lastKnownUid,
                                        new: currentFirebaseUid
                                    });

                                    // Update the stored UID
                                    localStorage.setItem('fcm_last_firebase_uid', currentFirebaseUid);

                                    // Re-register the FCM token with the new user
                                    if (this.token) {
                                        await this.registerTokenWithServer(this.token);
                                    }
                                }
                            }

                            return true;
                        } else {
                            console.error('[FCM] Verification retry failed after token refresh');
                            return false;
                        }
                    } else {
                        console.error('[FCM] Failed to refresh token during verification');
                        return false;
                    }
                }

                return false;
            }

            const data = await response.json();
            const currentFirebaseUid = data.firebase_uid;

            if (!currentFirebaseUid) {
                return false;
            }

            // Check if we have the last known Firebase UID stored
            const lastKnownUid = localStorage.getItem('fcm_last_firebase_uid');

            // If the Firebase UID has changed, re-register the FCM token
            if (lastKnownUid !== currentFirebaseUid) {
                console.log('[FCM] User changed, re-registering FCM token:', {
                    old: lastKnownUid,
                    new: currentFirebaseUid
                });

                // Update the stored UID
                localStorage.setItem('fcm_last_firebase_uid', currentFirebaseUid);

                // Re-register the FCM token with the new user
                if (this.token) {
                    await this.registerTokenWithServer(this.token);
                }
            }

            // The notification is valid since we're authenticated
            return true;

        } catch (error) {
            console.error('[FCM] Error verifying notification recipient:', error);
            return true; // Show notification if verification fails
        }
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
    },

    /**
     * Fetch API token from server if not in localStorage
     */
    async fetchApiToken() {
        try {
            console.log('[FCM] Fetching API token from server...');
            const response = await fetch('/apiv/_1/token', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                const token = data.token;
                localStorage.setItem(this.getApiKey(), token);
                console.log('[FCM] API token fetched and stored');
                return token;
            } else {
                console.error('[FCM] Failed to fetch API token. Status:', response.status);
                return null;
            }
        } catch (error) {
            console.error('[FCM] Error fetching API token:', error);
            return null;
        }
    }
};

// ========================================
// SERVICE WORKER MESSAGE HANDLER
// Handles messages from the service worker
// (e.g., keep-alive pings to maintain Firefox connection)
// ========================================
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
        const data = event.data;

        // Handle keep-alive messages silently
        if (data && data.type === 'fcm-keep-alive') {
            console.log('[FCM] Keep-alive ping received from service worker');
            return;
        }

        // Log other messages for debugging
        console.log('[FCM] Message from service worker:', data);
    });
}

// Initialize FCM when DOM is ready
document.addEventListener('DOMContentLoaded', async function() {
    console.log('[FCM] DOM loaded, checking initialization...');

    // Check Firebase availability first
    console.log('[FCM] Firebase available:', typeof firebase !== 'undefined');
    console.log('[FCM] Firebase config:', window.FIREBASE_CONFIG);

    if (typeof firebase === 'undefined') {
        console.error('[FCM] Skipping initialization: Firebase SDK not loaded');
        return;
    }

    // Check if user is authenticated (API token in localStorage or get from server)
    let apiToken = localStorage.getItem(FcmService.getApiKey());
    console.log('[FCM] API token found in localStorage:', !!apiToken);

    // If no token in localStorage, try to fetch it from server
    if (!apiToken) {
        console.log('[FCM] No token in localStorage, attempting to fetch from server...');
        apiToken = await FcmService.fetchApiToken();
    }

    if (apiToken) {
        console.log('[FCM] Initializing FCM service...');
        // Delay initialization to ensure Firebase is ready
        setTimeout(() => {
            FcmService.init();
        }, 1000);
    } else {
        console.log('[FCM] Skipping initialization: User not authenticated (no session)');
    }
});

// Make FcmService available globally
window.FcmService = FcmService;

// Global debug function to show current FCM info
window.showFcmInfo = function() {
    console.log('[FCM DEBUG] ========================================');
    console.log('[FCM DEBUG] Current FCM Status:');
    console.log('[FCM DEBUG] ----------------------------------------');
    console.log('[FCM DEBUG] Token:', FcmService.token ? FcmService.token.substring(0, 30) + '...' : 'NOT SET');
    console.log('[FCM DEBUG] Full Token:', FcmService.token || 'NOT SET');
    console.log('[FCM DEBUG] Domain:', window.location.origin);
    console.log('[FCM DEBUG] Device:', FcmService.getDeviceInfo());
    console.log('[FCM DEBUG] Service Worker:', navigator.serviceWorker.controller ? 'ACTIVE' : 'NOT ACTIVE');
    console.log('[FCM DEBUG] ----------------------------------------');
    console.log('[FCM DEBUG] Usage: Call showFcmInfo() in console to see this info');
    console.log('[FCM DEBUG] ========================================');

    return {
        token: FcmService.token,
        domain: window.location.origin,
        device: FcmService.getDeviceInfo(),
        swActive: !!navigator.serviceWorker.controller
    };
};

console.log('[FCM] Debug function available: showFcmInfo()');
