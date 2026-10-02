/**
 * Notifications Dropdown
 * Standalone notification system for navbar
 */

import { getApiToken, refreshApiToken } from './apiTokenRefresh';

const NotificationService = {
    notifications: [],
    unreadCount: 0,

    async init() {
        // Wait a bit for FCM to refresh token if needed
        await this.ensureValidToken();

        this.setupEventListeners();
        this.fetchNotifications();
        this.fetchUnreadCount();
        // Polling removed - relying on real-time FCM updates instead
    },

    async ensureValidToken() {
        const token = await getApiToken();
        if (!token) return;

        // Quick test to see if token is valid
        try {
            const response = await fetch('/apiv/_1/test-auth', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });

            if (response.status === 401) {
                await this.refreshToken();
            }
        } catch (error) {
            console.error('[Notifications] Token validation error:', error);
        }
    },

    async refreshToken() {
        return refreshApiToken();
    },

    setupEventListeners() {
        // Open/close, outside-click, Escape, aria-expanded and mutual exclusion with
        // the account menu are all owned by Bootstrap's dropdown plugin
        // (#notificationBell carries data-toggle="dropdown"). No custom show-class handling.
        const bellButton = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        const $ = window.jQuery;

        if (bellButton && dropdown && $) {
            $(bellButton.parentElement)
                .on('show.bs.dropdown', () => this.fetchNotifications())
                // Bootstrap closes a menu on any click inside it; keep the tray open only for
                // real (native) clicks that started inside it. composedPath() is captured at
                // dispatch, so it survives a re-render detaching the target. Synthetic clicks
                // (Bootstrap's Escape handling triggers one on the menu) carry no native
                // event and must not veto.
                .on('hide.bs.dropdown', (e) => {
                    const native = e.clickEvent && e.clickEvent.originalEvent;
                    if (!native) return;
                    const path = typeof native.composedPath === 'function' ? native.composedPath() : [];
                    const inside = path.length ? path.includes(dropdown) : dropdown.contains(native.target);
                    if (inside) e.preventDefault();
                });

            // Escape while focus is on <body> (e.g. the focused control was re-rendered away)
            // never reaches Bootstrap's handlers; close through the plugin and restore focus.
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape' || !dropdown.classList.contains('show')) return;
                if (bellButton.parentElement.contains(document.activeElement)) return; // Bootstrap handles it
                $(bellButton).dropdown('toggle'); // open -> Bootstrap clears menus (resets aria-expanded)
                bellButton.focus();
            });
        }

        // Mark all as read button
        const markAllBtn = document.getElementById('markAllAsRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
    },

    async authenticatedFetch(url, options = {}) {
        const token = await getApiToken();
        const headers = {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            ...options.headers
        };

        let response = await fetch(url, { ...options, headers });

        // If 401, refresh the token (shared with FCM) and retry once
        if (response.status === 401) {
            const newToken = await this.refreshToken();
            if (newToken) {
                headers['Authorization'] = `Bearer ${newToken}`;
                response = await fetch(url, { ...options, headers });
            }
        }

        return response;
    },

    async fetchNotifications() {
        try {
            const response = await this.authenticatedFetch('/apiv/_1/notifications?per_page=10');

            if (response.ok) {
                const data = await response.json();
                this.notifications = data.data;
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    },

    async fetchUnreadCount() {
        try {
            const response = await this.authenticatedFetch('/apiv/_1/notifications/unread-count');

            if (response.ok) {
                const data = await response.json();
                this.unreadCount = data.count || 0;
                this.updateBadge();
            }
        } catch (error) {
            console.error('Failed to fetch unread count:', error);
        }
    },

    async markAsRead(id) {
        try {
            const response = await this.authenticatedFetch(`/apiv/_1/notifications/${id}/read`, {
                method: 'PUT'
            });

            if (response.ok) {
                // Update locally instead of refetching
                this.markAsReadLocally(id);
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },

    markAsReadLocally(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (notification) {
            const wasUnread = !notification.read_at;
            notification.read_at = new Date().toISOString();
            if (wasUnread) {
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }
            this.renderNotifications();
            this.updateBadge();
        }
    },

    async markAllAsRead() {
        try {
            const response = await this.authenticatedFetch('/apiv/_1/notifications/read-all', {
                method: 'PUT'
            });

            if (response.ok) {
                // Update locally instead of refetching
                this.notifications.forEach(n => {
                    if (!n.read_at) n.read_at = new Date().toISOString();
                });
                this.unreadCount = 0;
                this.updateBadge();
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    },

    async deleteNotification(id) {
        try {
            const response = await this.authenticatedFetch(`/apiv/_1/notifications/${id}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                // Update locally instead of refetching
                const notif = this.notifications.find(n => n.id === id);
                const wasUnread = !notif?.read_at;
                this.notifications = this.notifications.filter(n => n.id !== id);
                if (wasUnread) {
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                }
                this.updateBadge();
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to delete notification:', error);
        }
    },

    updateBadge() {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    },

    renderNotifications() {
        const container = document.getElementById('notificationList');
        if (!container) return;

        // Remember focus only if it was on a control inside the list (re-render detaches it).
        const active = document.activeElement;
        let focusIdx = -1;
        if (active && container.contains(active)) {
            const item = active.closest('.notification-item');
            focusIdx = item ? Array.prototype.indexOf.call(container.children, item) : 0;
        }
        const restoreFocus = () => {
            if (focusIdx < 0) return;
            const items = container.querySelectorAll('.notification-item');
            let target = items.length ? items[Math.min(focusIdx, items.length - 1)].querySelector('button') : null;
            if (!target) target = document.getElementById('markAllAsRead');
            const tray = document.getElementById('notificationDropdown');
            if (!target || !tray || !tray.classList.contains('show')) target = document.getElementById('notificationBell');
            if (target) target.focus();
        };

        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="dropdown-item text-center text-muted py-3">
                    <i class="fas fa-inbox"></i>
                    <p class="mb-0 mt-2">No notifications</p>
                </div>
            `;
            restoreFocus();
            return;
        }

        const el = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };

        const iconButton = (className, title, iconClass, onClick) => {
            const btn = el('button', className);
            btn.type = 'button';
            btn.title = title;
            btn.setAttribute('aria-label', title);
            btn.appendChild(el('i', iconClass));
            btn.addEventListener('click', () => onClick());
            return btn;
        };

        const fragment = document.createDocumentFragment();

        this.notifications.forEach(notif => {
            const isUnread = !notif.read_at;

            const item = el('div', `notification-item ${isUnread ? 'unread' : ''}`);
            item.dataset.id = String(notif.id);
            item.style.cssText = `cursor: pointer; border-left: 3px solid ${this.getTypeColor(notif.type)}; padding: 0.75rem 1rem;`;

            const row = el('div', 'd-flex justify-content-between align-items-start');

            const content = el('div', 'flex-grow-1');
            content.addEventListener('click', () => this.handleNotificationClick(notif.id, notif.data?.type || ''));

            const titleRow = el('div', 'd-flex align-items-center mb-1');
            const icon = el('i', `${this.getTypeIcon(notif.type)} mr-2`);
            icon.style.color = 'inherit';
            const title = el('strong', '', notif.title ?? '');
            title.style.color = '#212529';
            titleRow.append(icon, title);

            const body = el('p', 'mb-1 small', notif.body || '');
            body.style.color = '#6c757d';

            const time = el('small', '', this.getTimeAgo(notif.created_at));
            time.style.color = '#6c757d';

            content.append(titleRow, body, time);

            const actions = el('div', 'd-flex align-items-center');
            if (isUnread) {
                actions.appendChild(iconButton('btn btn-sm btn-link text-success p-0 ml-2', 'Mark as read', 'fas fa-check',
                    () => this.markAsRead(notif.id)));
            }
            actions.appendChild(iconButton('btn btn-sm btn-link text-muted p-0 ml-2', 'Delete', 'fas fa-times',
                () => this.deleteNotification(notif.id)));

            row.append(content, actions);
            item.appendChild(row);
            fragment.appendChild(item);
        });

        container.replaceChildren(fragment);
        restoreFocus();
    },

    handleNotificationClick(id, type) {
        // Just mark as read without redirecting
        this.markAsRead(id);
    },

    getTypeIcon(type) {
        const icons = {
            success: 'fas fa-check-circle text-success',
            info: 'fas fa-info-circle text-info',
            warning: 'fas fa-exclamation-triangle text-warning',
            error: 'fas fa-times-circle text-danger'
        };
        return icons[type] || icons.info;
    },

    getTypeColor(type) {
        const colors = {
            success: '#28a745',
            info: '#17a2b8',
            warning: '#ffc107',
            error: '#dc3545'
        };
        return colors[type] || colors.info;
    },

    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        const intervals = {
            year: 31536000,
            month: 2592000,
            week: 604800,
            day: 86400,
            hour: 3600,
            minute: 60
        };

        for (const [unit, secondsInUnit] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInUnit);
            if (interval >= 1) {
                return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
            }
        }

        return 'Just now';
    }
};

// Initialize once the DOM is ready. If no token is stored yet (first visit),
// fetch one through the shared refresh so the dropdown works without a reload.
async function startNotifications() {
    const token = await getApiToken();
    if (!token) return;

    try {
        await NotificationService.init();
    } catch (err) {
        console.error('[Notifications] Initialization failed:', err);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startNotifications);
} else {
    startNotifications();
}

// Make it globally available
window.NotificationService = NotificationService;
