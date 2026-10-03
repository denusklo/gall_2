import { getApiToken, refreshApiToken, clearApiToken } from './apiTokenRefresh';

const PREFERENCE_KEY = 'gall2.push.device.v1';
const STOP_KEY = 'gall2.push.stop.v1';
const REGISTRATION_KEY = 'gall2.push.registration.v1';
const INSTALL_KEY = 'gall2.push.install.v1';
const RETIRED_KEY = 'gall2.push.retired.v1';
const TIMEOUT_MS = 6000;

const FcmService = {
    messaging: null,
    token: null,
    generation: null,
    registrationUid: null,
    pageUid: null,
    installId: null,
    setupError: null,
    retryRequired: false,
    retiredTokens: new Set(),
    swRegistration: null,
    verifiedUid: null,
    ready: false,
    busy: false,
    stopped: false,
    epoch: 0,
    state: 'preparing',
    controllers: new Set(),
    installPrompt: null,
    channel: null,

    installation() {
        try {
            let id = localStorage.getItem(INSTALL_KEY);
            if (!id) {
                id = window.crypto.randomUUID();
                localStorage.setItem(INSTALL_KEY, id);
            }
            localStorage.setItem(INSTALL_KEY, id);
            return id;
        } catch (_) { return null; }
    },

    receiveStop(message) {
        if (!message || message.type !== 'stop' || message.origin !== window.location.origin ||
            !this.installId || message.installId !== this.installId ||
            !this.pageUid || message.uid !== this.pageUid) return;
        this.stopLocally(false);
        this.render('disabled', 'Device notifications stopped in another tab. Reload to change settings.');
    },

    tokenRetired(token) {
        try {
            const retired = JSON.parse(localStorage.getItem(RETIRED_KEY) || '[]');
            if (!Array.isArray(retired)) return true;
            // Probe writes before enrollment: retirement must survive a reload.
            localStorage.setItem(RETIRED_KEY, JSON.stringify(retired));
            return retired.includes(token) || this.retiredTokens.has(token) || this.preference()?.retiredToken === token;
        } catch (_) { return true; }
    },

    preference() {
        try {
            const value = localStorage.getItem(PREFERENCE_KEY);
            // A read-only storage area cannot reliably remember a later opt-out.
            localStorage.setItem(PREFERENCE_KEY, value || 'null');
            return JSON.parse(value) || null;
        }
        catch (_) { return { enabled: false, storageUnavailable: true }; }
    },

    registration() {
        try { return JSON.parse(localStorage.getItem(REGISTRATION_KEY)) || null; }
        catch (_) { return null; }
    },

    saveRegistration(value) {
        try { localStorage.setItem(REGISTRATION_KEY, JSON.stringify(value)); }
        catch (_) { /* Memory remains available for this page's cleanup. */ }
    },

    savePreference(value) {
        try { localStorage.setItem(PREFERENCE_KEY, JSON.stringify(value)); return true; }
        catch (_) { return false; }
    },

    standalone() {
        return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    },

    ios() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    },

    render(state, message) {
        this.state = state;
        const messages = {
            preparing: 'Checking device notification support…',
            insecure: 'Device notifications need HTTPS. Your in-app notifications still work.',
            unavailable: 'Device notifications are unavailable in this browser. Your in-app notifications still work.',
            install: 'On iOS or iPadOS 16.4+, add this site to your Home Screen, open its icon, then sign in. In Safari, use Share → Add to Home Screen.',
            default: 'Device notifications are optional. Your in-app notifications still work.',
            disabled: 'Device notifications are turned off here.',
            denied: 'Notifications are blocked. Change this site’s browser or device notification settings to enable them.',
            incomplete: 'Device setup is incomplete. Enable to finish setup.',
            sending: 'Saving this device…',
            enabled: 'Notifications are enabled on this device.',
            sendingerror: 'Device setup could not be saved. Try again when connected.',
            stopping: 'Turning off this device…'
        };
        const status = document.getElementById('devicePushStatus');
        if (status) { status.textContent = message || messages[state]; status.dataset.state = state; }
        const enable = document.getElementById('devicePushEnable');
        const disable = document.getElementById('devicePushDisable');
        if (enable) {
            enable.hidden = !this.ready || this.stopped || ['denied', 'enabled', 'stopping'].includes(state);
            enable.disabled = this.busy;
        }
        if (disable) {
            disable.hidden = !this.ready || this.stopped || !['enabled', 'sendingerror', 'incomplete'].includes(state);
            disable.disabled = this.busy;
        }
    },

    permissionState() {
        if (Notification.permission === 'denied') return 'denied';
        if (this.preference()?.enabled === false) return 'disabled';
        return Notification.permission === 'granted' ? 'incomplete' : 'default';
    },

    bindControls() {
        document.getElementById('devicePushEnable')?.addEventListener('click', () => {
            // No await before requestPermission: Safari needs the original tap activation.
            this.requestPermissionAndGetToken();
        });
        document.getElementById('devicePushDisable')?.addEventListener('click', () => this.deactivateDevice());
        document.getElementById('deviceInstall')?.addEventListener('click', () => this.install());
        document.getElementById('devicePushControls')?.addEventListener('click', event => event.stopPropagation());
        window.addEventListener('beforeinstallprompt', event => {
            event.preventDefault();
            this.installPrompt = event;
            this.renderInstall();
        });
        window.addEventListener('appinstalled', () => {
            this.installPrompt = null;
            this.renderInstall();
        });
        window.addEventListener('storage', event => {
            if (event.key !== STOP_KEY && event.key !== PREFERENCE_KEY) return;
            try {
                const value = JSON.parse(event.newValue);
                this.receiveStop(event.key === STOP_KEY ? value : value?.stop);
            } catch (_) { /* Ignore legacy/unscoped events, never infer identity from storage. */ }
        });
        if ('BroadcastChannel' in window) {
            try {
                this.channel = new BroadcastChannel('gall2.push.device');
                this.channel.onmessage = event => {
                    this.receiveStop(event.data);
                };
            } catch (_) { /* Storage events remain the fallback. */ }
        }
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.resumeOptedIn();
        });
        this.renderInstall();
    },

    renderInstall() {
        const button = document.getElementById('deviceInstall');
        if (button) button.hidden = !this.installPrompt || this.standalone();
        const help = document.getElementById('deviceInstallHelp');
        if (help) {
            help.textContent = this.standalone() ? 'Installed app. An internet connection is required.' :
                this.ios() ? 'Home Screen apps need iOS or iPadOS 16.4+ for notifications. You may need to sign in again.' :
                    'Installation is optional for supported Android browsers. If offered, use your browser’s install or home-screen menu. An internet connection is required.';
        }
    },

    async install() {
        const prompt = this.installPrompt;
        if (!prompt) return;
        this.installPrompt = null;
        this.renderInstall();
        try { await prompt.prompt(); await prompt.userChoice; }
        catch (_) { /* Browser may withdraw the install offer. */ }
    },

    async init() {
        this.pageUid = document.getElementById('notificationBell')?.dataset.authUid || null;
        this.installId = this.installation();
        this.bindControls();
        // The bundle is authenticated-only; also fail closed if loaded on a guest page.
        if (!document.getElementById('notificationBell')) return false;
        if (!window.isSecureContext) { this.render('insecure'); return false; }
        if (this.ios() && !this.standalone()) { this.render('install'); return false; }
        if (!('Notification' in window) || !('PushManager' in window) || !('serviceWorker' in navigator) ||
            typeof firebase === 'undefined' || typeof firebase.messaging?.isSupported !== 'function') {
            this.render('unavailable'); return false;
        }
        const epoch = this.epoch;
        try {
            if (!await this.bounded(firebase.messaging.isSupported())) { this.render('unavailable'); return false; }
            if (this.stopped || epoch !== this.epoch) return false;
            if (!firebase.apps.length) firebase.initializeApp(window.FIREBASE_CONFIG || {});
            this.messaging = firebase.messaging();
            this.swRegistration = await this.bounded(navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                scope: '/', updateViaCache: 'none'
            }));
            await this.bounded(navigator.serviceWorker.ready);
            if (this.stopped || epoch !== this.epoch) return false;
            this.setupMessageHandler();
            this.ready = true;
            this.render(this.permissionState());
            await this.resumeOptedIn();
            return true;
        } catch (_) {
            if (!this.stopped) this.render('unavailable');
            return false;
        }
    },

    bounded(promise, milliseconds = TIMEOUT_MS) {
        let timer;
        return Promise.race([
            promise,
            new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('timeout')), milliseconds); })
        ]).finally(() => clearTimeout(timer));
    },

    async request(path, method = 'GET', body = null, cleanup = false) {
        const epoch = this.epoch;
        const valid = () => epoch === this.epoch && (cleanup || !this.stopped);
        let bearer = await this.bounded(getApiToken(), cleanup ? 1500 : TIMEOUT_MS);
        for (let attempt = 0; attempt < (cleanup ? 1 : 2); attempt++) {
            if (!bearer || !valid()) return null;
            const controller = new AbortController();
            controller.pushRegistration = method === 'POST' && path === '/apiv/_1/fcm/token';
            this.controllers.add(controller);
            const timer = setTimeout(() => controller.abort(), cleanup ? 1500 : TIMEOUT_MS);
            let response;
            try {
                response = await fetch(path, {
                    method, credentials: 'same-origin', signal: controller.signal,
                    headers: {
                        'Authorization': `Bearer ${bearer}`, 'Accept': 'application/json',
                        'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    ...(body ? { body: JSON.stringify(body) } : {})
                });
                // A late POST receipt still needs its exact generation cleaned up.
                if (!valid() && method !== 'POST') return null;
                if (response.ok) return await response.json();
                if (response.status === 409) return { conflict: true };
                if (response.status === 503) return { setupError: 'Device notification service is unavailable. Try again later.' };
                if (response.status === 422) return { setupError: 'Device setup was rejected. Check that you are using the correct site address, then retry.' };
            } finally {
                clearTimeout(timer);
                this.controllers.delete(controller);
            }
            if (!valid() || response.status !== 401 || attempt !== 0 || cleanup) return null;
            bearer = await this.bounded(refreshApiToken());
        }
        return null;
    },

    async testAuth() {
        this.verifiedUid = null;
        try {
            const epoch = this.epoch;
            const data = await this.request('/apiv/_1/test-auth');
            if (this.stopped || epoch !== this.epoch || typeof data?.firebase_uid !== 'string' || !data.firebase_uid) return false;
            if (this.pageUid && this.pageUid !== data.firebase_uid) {
                this.stopLocally(false);
                this.render('disabled', 'Your account changed. Reload before changing device notifications.');
                return false;
            }
            this.pageUid = data.firebase_uid;
            this.verifiedUid = data.firebase_uid;
            return true;
        } catch (_) { return false; }
    },

    async resumeOptedIn() {
        if (!this.ready || this.stopped || this.busy || this.retryRequired) return false;
        if (Notification.permission !== 'granted') { this.render(this.permissionState()); return false; }
        const preference = this.preference();
        if (preference?.enabled === false || preference?.retryRequired) return false;
        // Legacy granted installations can enroll without another permission prompt.
        return this.enroll(typeof preference?.uid === 'string' ? preference.uid : null);
    },

    requestPermissionAndGetToken() {
        if (!this.ready || this.busy || this.stopped) return Promise.resolve(false);
        if (Notification.permission === 'denied') { this.render('denied'); return Promise.resolve(false); }
        let permission;
        try {
            permission = Notification.permission === 'default' ? Notification.requestPermission() : Promise.resolve(Notification.permission);
        } catch (_) { this.render('sendingerror'); return Promise.resolve(false); }
        const epoch = this.epoch;
        this.busy = true;
        this.render('sending');
        return Promise.resolve(permission).then(result => {
            if (this.stopped || epoch !== this.epoch) return false;
            this.busy = false;
            if (result !== 'granted') { this.render(this.permissionState()); return false; }
            this.retryRequired = false;
            return this.enroll(this.preference()?.uid || null);
        }).catch(() => {
            if (!this.stopped && epoch === this.epoch) { this.busy = false; this.render('sendingerror'); }
            return false;
        });
    },

    async enroll(expectedUid = null) {
        if (this.stopped || this.busy || !this.ready || Notification.permission !== 'granted') return false;
        const epoch = this.epoch;
        this.busy = true;
        this.render('sending');
        this.setupError = null;
        let success = false;
        try {
            if (!this.installId || this.installation() !== this.installId || this.preference()?.storageUnavailable) return false;
            if (!await this.testAuth()) return false;
            if (expectedUid !== null && expectedUid !== this.verifiedUid) {
                await this.retireToken(this.registration()?.token || this.token, epoch);
                return false;
            }
            if (epoch !== this.epoch || this.stopped) return false;
            const uid = this.verifiedUid;
            const token = await this.bounded(this.messaging.getToken({
                vapidKey: window.FIREBASE_CONFIG?.vapidKey || '', serviceWorkerRegistration: this.swRegistration
            }));
            if (!token || epoch !== this.epoch || this.stopped) return false;
            if (this.tokenRetired(token)) return false;
            if (this.token !== token) this.generation = null;
            this.token = token;
            this.registrationUid = uid;
            this.pendingPost = this.registerTokenWithServer(token, uid, epoch);
            if (!await this.pendingPost) return false;
            if (epoch !== this.epoch || this.stopped) return false;
            const saved = this.savePreference({ enabled: true, uid });
            success = true;
            this.busy = false;
            this.render('enabled', saved ? null : 'Enabled for this session. Device preferences cannot be saved; automatic setup is off on your next visit.');
            return true;
        } catch (_) { return false; }
        finally {
            if (epoch === this.epoch && !this.stopped) {
                this.busy = false;
                if (!success) {
                    this.retryRequired = true;
                    this.savePreference({ ...this.preference(), retryRequired: true });
                    this.render('sendingerror', this.setupError || 'Setup failed. Click Enable to retry. If this device changed accounts, a new subscription is required.');
                }
            }
        }
    },

    async retireToken(token, epoch) {
        if (token) {
            this.retiredTokens.add(token);
            try {
                const retired = JSON.parse(localStorage.getItem(RETIRED_KEY) || '[]');
                localStorage.setItem(RETIRED_KEY, JSON.stringify([...new Set([...retired, token])]));
            } catch (_) { /* Preference tombstone below is a second persistence attempt. */ }
        }
        this.registrationUid = null;
        this.retryRequired = true;
        this.verifiedUid = null;
        this.savePreference({ enabled: false, retryRequired: true, retiredToken: token || null });
        this.saveRegistration(null);
        this.token = null;
        this.generation = null;
        // One bounded retirement, never a getToken retry in this operation.
        if (epoch === this.epoch && !this.stopped) {
            try { await this.bounded(this.messaging.deleteToken(), 1500); }
            catch (_) { /* Explicit retry must still reject the retired token. */ }
        }
    },

    async registerTokenWithServer(token, uid, epoch) {
        const result = await this.request('/apiv/_1/fcm/token', 'POST', {
            token, device_info: this.getDeviceInfo(), domain: window.location.origin
        });
        if (result?.conflict) {
            if (epoch === this.epoch && !this.stopped) await this.retireToken(token, epoch);
            return false;
        }
        if (epoch === this.epoch && !this.stopped) this.setupError = result?.setupError || null;
        if (result?.success !== true || typeof result.generation !== 'string' || !result.generation) return false;
        if (epoch !== this.epoch || this.stopped) {
            try { await this.bounded(this.request('/apiv/_1/fcm/token', 'DELETE', { token, generation: result.generation }, true), 3500); }
            catch (_) { /* Logout also revokes the session independently. */ }
            return false;
        }
        this.generation = result.generation;
        this.saveRegistration({ token, generation: result.generation, uid });
        return true;
    },

    async verifyNotificationRecipient(payload) {
        if (!await this.testAuth()) return false;
        return typeof payload?.data?.recipient_uid === 'string' && payload.data.recipient_uid === this.verifiedUid;
    },

    setupMessageHandler() {
        this.unsubscribeMessage = this.messaging.onMessage(async payload => {
            if (this.stopped) return;
            const epoch = this.epoch;
            const matches = await this.verifyNotificationRecipient(payload);
            if (this.stopped || epoch !== this.epoch) return;
            if (this.verifiedUid && window.NotificationService) {
                window.NotificationService.fetchUnreadCount();
                window.NotificationService.fetchNotifications();
            }
            if (!matches || !payload?.notification || typeof iziToast === 'undefined') return;
            const escape = value => String(value || '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[char]));
            iziToast.info({
                title: escape(payload.notification.title || 'Notification'),
                message: escape(payload.notification.body), position: 'topRight', timeout: 5000,
                buttons: [['<button>View</button>', (instance, toast) => {
                    window.location.href = '/images';
                    instance.hide({ transitionOut: 'fadeOutUp' }, toast, 'buttonName');
                }, true]]
            });
        });
    },

    stopLocally(clearBearer = true) {
        this.stopped = true;
        this.epoch++;
        this.busy = false;
        this.verifiedUid = null;
        this.controllers.forEach(controller => { if (!controller.pushRegistration) controller.abort(); });
        this.controllers.clear();
        this.token = null;
        this.generation = null;
        this.registrationUid = null;
        if (clearBearer) clearApiToken();
        try { localStorage.removeItem('fcm_last_firebase_uid'); } catch (_) { /* No persisted identity required. */ }
    },

    // Call before native logout form.submit(), while the session still exists.
    // Returns an honest cleanup result; callers must allow logout even on failure.
    async deactivateDevice({ logout = false } = {}) {
        if (this.deactivation) return this.deactivation;
        const registration = this.token && this.generation ?
            { token: this.token, generation: this.generation, uid: this.registrationUid } : this.registration();
        const uid = this.pageUid;
        const stop = { type: 'stop', origin: window.location.origin, installId: this.installId, uid,
            eventId: `${Date.now()}-${Math.random()}` };
        this.stopLocally(false);
        const saved = this.savePreference({ ...this.preference(), enabled: false, stop });
        try { localStorage.setItem(STOP_KEY, JSON.stringify(stop)); } catch (_) { /* Channel fallback. */ }
        try { this.channel?.postMessage(stop); } catch (_) { /* Storage fallback. */ }
        this.render('stopping');
        this.deactivation = (async () => {
            let server = false;
            let sdk = false;
            let authenticated = false;
            // Give an in-flight POST time to return its generation and revoke it
            // before deleting the SDK subscription. Late receipts also self-clean.
            try { if (this.pendingPost) await this.bounded(this.pendingPost, 3500); }
            catch (_) { /* Session logout remains the independent fallback. */ }
            try {
                const auth = await this.request('/apiv/_1/test-auth', 'GET', null, true);
                authenticated = !!uid && auth?.firebase_uid === uid && !!this.installId &&
                    this.installation() === this.installId;
                // Stored registration UID only narrows cleanup; it never authenticates it.
                if (authenticated && registration?.uid === uid &&
                    registration?.token && typeof registration.generation === 'string') {
                    const result = await this.bounded(this.request('/apiv/_1/fcm/token', 'DELETE', {
                        token: registration.token, generation: registration.generation
                    }, true), 3500);
                    server = result?.success === true;
                }
            } catch (_) { /* Logout must not wait indefinitely for the server. */ }
            try {
                if (authenticated && this.messaging) sdk = await this.bounded(this.messaging.deleteToken(), 1500) === true;
            } catch (_) { /* Report incomplete cleanup below. */ }
            try {
                const notifications = await this.bounded(this.swRegistration?.getNotifications() || Promise.resolve([]), 500);
                notifications.forEach(notification => notification.close());
            } catch (_) { /* Already delivered OS messages cannot always be retracted. */ }
            this.token = null;
            this.verifiedUid = null;
            clearApiToken();
            const success = server && sdk && saved;
            const stored = this.registration();
            if (stored?.uid === uid && stored?.generation === registration?.generation) this.saveRegistration(null);
            // Stay stopped if opt-out cannot survive reload; explicit settings remain safe.
            if (!logout && saved) this.stopped = false;
            this.render('disabled', success ? null : 'Turned off locally. Server cleanup could not be confirmed; device alerts may still arrive. Check browser notification settings.');
            return { success, server, sdk };
        })();
        try { return await this.deactivation; }
        finally { this.deactivation = null; }
    },

    async logout(form) {
        if (!form || form.dataset.pushSubmitting) return;
        form.dataset.pushSubmitting = 'true';
        try {
            const result = await this.bounded(this.deactivateDevice({ logout: true }), 6000);
            if (!result.success) this.logoutWarning();
        } catch (_) { this.logoutWarning(); }
        finally { HTMLFormElement.prototype.submit.call(form); }
    },

    logoutWarning() {
        const message = 'Device notification cleanup could not be confirmed. Logging out anyway; queued alerts may still arrive. Use browser notification settings to block alerts.';
        this.render('disabled', message);
        if (typeof iziToast !== 'undefined') iziToast.warning({ title: 'Logout', message, timeout: 5000 });
    },

    deleteToken() { return this.deactivateDevice({ logout: true }); },
    getDeviceInfo() { return this.ios() ? 'iOS / iPadOS' : /Android/.test(navigator.userAgent) ? 'Android' : 'Desktop browser'; }
};

window.FcmService = FcmService;
document.addEventListener('DOMContentLoaded', () => FcmService.init());
