import { getApiToken, refreshApiToken } from './apiTokenRefresh';

const el = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className || '';
    if (text !== undefined) node.textContent = text;
    return node;
};
const queryState = () => ({ items: [], loading: false, error: '', request: 0, controller: null });
const NotificationService = {
    notifications: [], unreadCount: 0,
    tray: queryState(),
    history: { ...queryState(), page: 1, lastPage: 1, total: 0, unread: false },
    countRequest: 0, countController: null, countError: '', mutationError: '', mutating: false,

    init() {
        this.setupEventListeners();
        return this.fetchNotifications();
    },
    refreshToken() { return refreshApiToken(); },
    setupEventListeners() {
        const bell = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        const $ = window.jQuery;
        if (bell && dropdown && $) {
            $(bell.parentElement).on('show.bs.dropdown', () => this.fetchNotifications())
                .on('hide.bs.dropdown', e => {
                    const native = e.clickEvent && e.clickEvent.originalEvent;
                    if (!native) return;
                    const path = typeof native.composedPath === 'function' ? native.composedPath() : [];
                    // Links deliberately close/navigate; action clicks keep the tray open.
                    if (native.target.closest && native.target.closest('a')) return;
                    if (path.length ? path.includes(dropdown) : dropdown.contains(native.target)) e.preventDefault();
                });
            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape' || !dropdown.classList.contains('show')) return;
                if (bell.parentElement.contains(document.activeElement)) return;
                $(bell).dropdown('toggle');
                bell.focus();
            });
        }
        ['markAllAsRead', 'historyMarkAll'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', () => this.markAllAsRead());
        });
        ['all', 'unread'].forEach(filter => {
            document.getElementById(`historyFilter-${filter}`)?.addEventListener('click', () => {
                this.history.unread = filter === 'unread';
                this.history.page = 1;
                this.fetchHistory();
            });
        });
        ['previous', 'next'].forEach(direction => {
            document.getElementById(`history-${direction}`)?.addEventListener('click', () => {
                if (this.history.loading || this.mutating) return;
                this.history.page = Math.max(1, Math.min(this.history.lastPage,
                    this.history.page + (direction === 'next' ? 1 : -1)));
                this.fetchHistory();
            });
        });
    },
    async authenticatedFetch(url, options = {}) {
        // Include token acquisition in the deadline, not just the API request.
        const controller = new AbortController();
        const abort = () => controller.abort();
        options.signal?.addEventListener('abort', abort, { once: true });
        if (options.signal?.aborted) abort();
        let timer;
        const deadline = new Promise((_, reject) => {
            timer = setTimeout(() => { abort(); reject(new Error('timeout')); }, 15000);
        });
        const request = async () => {
            let token = await getApiToken();
            if (typeof token !== 'string' || !token.trim()) throw new Error('authentication');
            const send = () => {
                if (controller.signal.aborted) throw new Error('aborted');
                return fetch(url, { ...options, signal: controller.signal, credentials: 'same-origin',
                    headers: { ...options.headers, Accept: 'application/json', Authorization: `Bearer ${token}` } });
            };
            let response = await send();
            if (response.status === 401) {
                token = await this.refreshToken();
                if (typeof token !== 'string' || !token.trim()) throw new Error('authentication');
                response = await send();
            }
            if (!response.ok) throw new Error('request');
            // JSON parsing also belongs inside the deadline.
            return response.json();
        };
        try { return await Promise.race([request(), deadline]); }
        finally {
            clearTimeout(timer);
            options.signal?.removeEventListener('abort', abort);
        }
    },
    fetchNotifications() {
        if (this.mutating) return Promise.resolve();
        const tasks = [this.fetchList(this.tray, false), this.fetchUnreadCount()];
        if (document.getElementById('notificationHistory')) tasks.push(this.fetchHistory());
        return Promise.all(tasks);
    },
    fetchHistory() {
        if (this.mutating) { this.renderHistoryControls(); return Promise.resolve(); }
        return this.fetchList(this.history, true);
    },
    async fetchList(state, history) {
        state.controller?.abort();
        const request = ++state.request;
        state.controller = new AbortController();
        state.loading = true;
        state.error = '';
        this.renderList(state, history);
        const page = history ? state.page : 1;
        const unread = history && state.unread;
        try {
            const data = await this.authenticatedFetch(`/apiv/_1/notifications?per_page=${history ? 15 : 5}&page=${page}&unread_only=${unread ? 1 : 0}`,
                { signal: state.controller.signal });
            if (request !== state.request) return;
            if (!Array.isArray(data.data) || !Number.isInteger(data.current_page) ||
                !Number.isInteger(data.last_page) || data.last_page < 1 ||
                !Number.isInteger(data.total) || data.total < 0 ||
                data.data.some(n => !n || !Number.isInteger(n.id))) throw new Error('format');
            if (history && page > data.last_page) {
                state.page = data.last_page;
                return this.fetchHistory();
            }
            state.items = data.data;
            if (history) Object.assign(state, { page: data.current_page, lastPage: data.last_page, total: data.total });
            else this.notifications = state.items;
        } catch (error) {
            if (request !== state.request) return;
            state.error = 'Could not load notifications. Try again.';
        } finally {
            if (request === state.request) {
                state.loading = false;
                this.renderList(state, history);
            }
        }
    },
    async fetchUnreadCount() {
        if (this.mutating) return;
        this.countController?.abort();
        const request = ++this.countRequest;
        this.countController = new AbortController();
        try {
            const data = await this.authenticatedFetch('/apiv/_1/notifications/unread-count', { signal: this.countController.signal });
            if (request !== this.countRequest) return;
            if (!Number.isInteger(data.count) || data.count < 0) throw new Error('format');
            this.unreadCount = data.count;
            this.countError = '';
            this.updateBadge();
        } catch (error) {
            if (request !== this.countRequest) return;
            this.countError = 'Could not refresh the unread count. Try again.';
        } finally {
            if (request === this.countRequest) this.renderErrors();
        }
    },
    invalidateReads() {
        [this.tray, this.history].forEach(state => {
            ++state.request;
            state.controller?.abort();
            state.loading = false;
        });
        ++this.countRequest;
        this.countController?.abort();
    },
    markAsRead(id) { return this.mutate(`/apiv/_1/notifications/${id}/read`, 'PUT', id); },
    deleteNotification(id) { return this.mutate(`/apiv/_1/notifications/${id}`, 'DELETE', id); },
    markAllAsRead() { return this.mutate('/apiv/_1/notifications/read-all', 'PUT'); },
    async mutate(url, method, id) {
        if (this.mutating) return;
        this.mutating = true;
        this.mutationError = '';
        this.invalidateReads();
        this.setMutationControls();
        this.renderErrors();
        try {
            const data = await this.authenticatedFetch(url, { method });
            if (data.success !== true) throw new Error('format');
            const known = [...this.tray.items, ...this.history.items].find(n => n.id === id);
            if (id !== undefined && known && !known.read_at) {
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateBadge();
            }
            // Local accepted changes remain visible even if the reconciliation GET fails.
            [this.tray, this.history].forEach(state => {
                state.items = state.items.filter(n => !(method === 'DELETE' && n.id === id));
                state.items.forEach(n => {
                    if (method === 'PUT' && (id === undefined || n.id === id)) n.read_at = new Date().toISOString();
                });
                if (state === this.history && state.unread) state.items = state.items.filter(n => !n.read_at);
            });
            this.notifications = this.tray.items;
            if (id === undefined) { this.unreadCount = 0; this.updateBadge(); }
        } catch (error) {
            this.mutationError = 'Could not update the notification. Retry the action.';
        } finally {
            this.mutating = false;
            this.renderList(this.tray, false);
            this.renderList(this.history, true);
            this.setMutationControls();
            this.renderErrors();
            await this.fetchNotifications();
        }
    },
    setMutationControls() {
        document.querySelectorAll('[data-notification-action], #markAllAsRead, #historyMarkAll')
            .forEach(button => { button.setAttribute('aria-disabled', String(this.mutating)); });
        this.renderHistoryControls();
    },
    updateBadge() {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = this.unreadCount > 99 ? '99+' : String(this.unreadCount);
            badge.style.display = this.unreadCount > 0 ? 'inline-block' : 'none';
        }
    },
    renderErrors() {
        ['notificationList', 'historyList'].forEach(id => {
            const list = document.getElementById(id);
            if (!list) return;
            let box = document.getElementById(`${id}-errors`);
            if (!box) {
                box = el('div', 'px-3 py-2 small');
                box.id = `${id}-errors`;
                box.setAttribute('role', 'status');
                list.before(box);
            }
            const focused = box.contains(document.activeElement);
            box.replaceChildren();
            const message = this.mutationError || this.countError;
            box.hidden = !message;
            if (message) {
                box.append(el('p', 'mb-1', message));
                if (this.countError && !this.mutationError) box.append(this.button('Retry', () => this.fetchUnreadCount()));
            }
            if (focused) {
                const fallback = document.getElementById(id === 'historyList' ? 'historyFilter-all' : 'notificationBell');
                (box.querySelector('button') || fallback)?.focus({ preventScroll: true });
            }
        });
    },
    button(label, callback) {
        const button = el('button', 'btn btn-sm btn-link', label);
        button.type = 'button';
        button.style.minHeight = '44px';
        button.style.minWidth = '44px';
        button.addEventListener('click', () => {
            if (button.getAttribute('aria-disabled') !== 'true') callback();
        });
        return button;
    },
    renderHistoryControls() {
        if (!document.getElementById('notificationHistory')) return;
        ['all', 'unread'].forEach(filter => {
            const button = document.getElementById(`historyFilter-${filter}`);
            const selected = this.history.unread === (filter === 'unread');
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('active', selected);
        });
        ['previous', 'next'].forEach(direction => {
            const button = document.getElementById(`history-${direction}`);
            const bound = direction === 'previous' ? this.history.page <= 1 : this.history.page >= this.history.lastPage;
            const focused = document.activeElement === button;
            button.disabled = bound;
            button.setAttribute('aria-disabled', String(bound || this.history.loading || this.mutating));
            if (focused && bound) document.getElementById('historyFilter-all').focus({ preventScroll: true });
        });
        document.getElementById('historyPage').textContent = `Page ${this.history.page} of ${this.history.lastPage}`;
    },
    renderNotifications() { this.renderList(this.tray, false); },
    renderList(state, history) {
        const container = document.getElementById(history ? 'historyList' : 'notificationList');
        if (!container) return;
        container.setAttribute('aria-busy', String(state.loading));
        if (history) this.renderHistoryControls();
        // Capture focus at render time, never when the asynchronous request starts.
        const active = document.activeElement;
        const focused = container.contains(active);
        const oldItem = focused ? active.closest('.notification-item') : null;
        const oldId = oldItem?.dataset.id;
        const action = active?.dataset.notificationAction;
        const oldIndex = oldItem ? Array.from(container.children).indexOf(oldItem) : 0;
        const fragment = document.createDocumentFragment();
        if (state.loading || state.error) {
            const status = el('div', 'px-3 py-3 small', state.loading ? 'Loading notifications…' : state.error);
            status.setAttribute('role', 'status');
            if (state.error) status.append(this.button('Retry', () => history ? this.fetchHistory() : this.fetchNotifications()));
            fragment.append(status);
        }
        if (!state.loading && !state.error && !state.items.length) {
            const empty = el('p', 'text-muted text-center p-3 mb-0', history && state.unread ? 'No unread notifications.' : 'No notifications yet.');
            empty.setAttribute('role', 'status');
            fragment.append(empty);
        }
        state.items.forEach(n => {
            const item = el('div', `notification-item ${n.read_at ? '' : 'unread'}`);
            item.dataset.id = String(n.id);
            item.style.cssText = 'padding: .75rem 1rem; border-bottom: 1px solid rgba(128,128,128,.2);';
            const content = el('div', 'notification-copy');
            content.style.cssText = 'min-width:0; overflow-wrap:anywhere;';
            content.append(el('strong', 'd-block', n.title ?? ''), el('p', 'small mb-1', n.body ?? ''),
                el('small', 'text-muted', this.getTimeAgo(n.created_at)));
            const actions = el('div', 'd-flex flex-wrap mt-2');
            const add = (label, kind, callback) => {
                const button = this.button(label, callback);
                button.classList.add('mr-2', 'mb-1');
                button.dataset.notificationAction = kind;
                button.setAttribute('aria-disabled', String(this.mutating));
                button.setAttribute('aria-label', `${label}: ${n.title ?? 'notification'}`);
                actions.append(button);
            };
            if (!n.read_at) add('Mark as read', 'read', () => this.markAsRead(n.id));
            add('Delete', 'delete', () => this.deleteNotification(n.id));
            item.append(content, actions);
            fragment.append(item);
        });
        container.replaceChildren(fragment);
        if (focused) {
            const items = Array.from(container.querySelectorAll('.notification-item'));
            const item = items.find(n => n.dataset.id === oldId) || items[Math.min(oldIndex, items.length - 1)];
            let target = item?.querySelector(`[data-notification-action="${action || 'read'}"]`) || item?.querySelector('button') || container.querySelector('button');
            if (!target) target = document.getElementById(history ? 'historyFilter-all' : 'markAllAsRead');
            const dropdown = document.getElementById('notificationDropdown');
            if (!history && !dropdown?.classList.contains('show')) target = document.getElementById('notificationBell');
            target?.focus({ preventScroll: true });
        }
    },
    getTimeAgo(value) {
        const seconds = Math.floor((Date.now() - new Date(value).getTime()) / 1000);
        if (!Number.isFinite(seconds)) return '';
        for (const [unit, size] of Object.entries({ year: 31536000, month: 2592000, week: 604800, day: 86400, hour: 3600, minute: 60 })) {
            const amount = Math.floor(seconds / size);
            if (amount >= 1) return `${amount} ${unit}${amount === 1 ? '' : 's'} ago`;
        }
        return 'Just now';
    }
};

window.NotificationService = NotificationService;
const startNotifications = () => NotificationService.init();
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startNotifications);
else startNotifications();
