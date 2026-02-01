/**
 * Firebase Cloud Messaging Service Worker
 * This file handles background push notifications
 */

// First, load the config
importScripts('/fcm-config.js?' + new Date().getTime());

// Import Firebase scripts in service worker
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

// Initialize Firebase with the loaded config
if (self.FIREBASE_CONFIG && !firebase.apps.length) {
    firebase.initializeApp(self.FIREBASE_CONFIG);
}

// Retrieve an instance of Firebase Messaging
const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage(function(payload) {
    console.log('[FCM SW] ==================================');
    console.log('[FCM SW] Received background message!');
    console.log('[FCM SW] Payload:', payload);
    console.log('[FCM SW] Notification:', payload.notification);
    console.log('[FCM SW] Data:', payload.data);
    console.log('[FCM SW] ==================================');

    const notification = payload.notification;
    const notificationTitle = notification.title || 'Notification';
    const notificationOptions = {
        body: notification.body || '',
        icon: notification.icon || '/favicon.ico',
        badge: notification.badge || '/favicon.ico',
        tag: payload.data?.type || 'default',
        data: payload.data || {}
    };

    // Show the notification
    console.log('[FCM SW] Showing notification:', notificationTitle);
    return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Handle notification click
self.addEventListener('notificationclick', function(event) {
    console.log('[FCM SW] Notification clicked:', event);

    event.notification.close();

    const data = event.notification.data || {};
    const type = data.type || '';

    // Determine URL based on notification type
    let url = '/requests/my';

    switch (type) {
        case 'request_completed':
        case 'new_completion':
        case 'fully_completed':
            url = '/requests/my';
            break;
        case 'completion_confirmation':
            url = '/requests/all';
            break;
        default:
            url = '/requests/my';
    }

    // Open the app and navigate to the appropriate page
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(function(clientList) {
            // Check if there's already a window open
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                // Focus the window if it's already open
                if (client.url.includes(url) || client.url === 'https://gallery_2.localhost.dev/') {
                    return client.focus();
                }
            }
            // If no window is open, open a new one
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

// ========================================
// INSTALL & ACTIVATE EVENTS
// ========================================

self.addEventListener('install', function(event) {
    console.log('[FCM SW] Service Worker installing...');
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('[FCM SW] Service Worker activating...');
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (!cacheName.includes('firebase-messaging') &&
                            !cacheName.includes('workbox')) {
                            console.log('[FCM SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
        ])
    );
});
