// Own clicks before Firebase installs its listener, including its FCM_MSG envelope.
// Payload URLs are deliberately ignored. Authentication remains the page's job.
self.addEventListener('notificationclick', event => {
    event.stopImmediatePropagation();
    event.notification.close();
    event.waitUntil((async () => {
        const target = new URL('/images', self.location.origin);
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const sameOrigin = windows.filter(client => {
            try { return new URL(client.url).origin === target.origin; }
            catch (_) { return false; }
        });
        const existing = sameOrigin.find(client => {
            const url = new URL(client.url);
            return url.pathname === target.pathname && !url.search && !url.hash;
        });
        if (existing) {
            try { return await existing.focus(); }
            catch (_) { /* The matching tab may have closed after matchAll. */ }
        }
        const client = sameOrigin.find(client => typeof client.navigate === 'function');
        if (client) {
            try {
                const navigated = await client.navigate(target.href);
                if (navigated) return await navigated.focus();
            } catch (_) { /* A closed tab may no longer be navigable. */ }
        }
        return self.clients.openWindow(target.href);
    })());
});

importScripts('/fcm-config.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

if (self.FIREBASE_CONFIG && !firebase.apps.length) firebase.initializeApp(self.FIREBASE_CONFIG);
// Firebase displays notification payloads once. Data-only messages need no display.
// Do not add onBackgroundMessage/showNotification here: that duplicates SDK display.
firebase.messaging();

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
// Online only: no fetch handler and no ownership of other applications' caches.
