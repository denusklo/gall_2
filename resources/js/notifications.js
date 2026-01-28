/**
 * Notifications Dropdown
 * Standalone notification system for navbar
 */

const NotificationService = {
    notifications: [],
    unreadCount: 0,
    pollInterval: null,
    tokenRefreshed: false,

    async init() {
        // Wait a bit for FCM to refresh token if needed
        await this.ensureValidToken();

        this.setupEventListeners();
        this.fetchNotifications();
        this.fetchUnreadCount();
        this.startPolling();
    },

    async ensureValidToken() {
        const token = localStorage.getItem('api_token');
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
                console.log('[Notifications] Token invalid, refreshing...');
                await this.refreshToken();
            }
        } catch (error) {
            console.error('[Notifications] Token validation error:', error);
        }
    },

    async refreshToken() {
        try {
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
                localStorage.setItem('api_token', data.token);
                this.tokenRefreshed = true;
                console.log('[Notifications] Token refreshed successfully');
            }
        } catch (error) {
            console.error('[Notifications] Token refresh failed:', error);
        }
    },

    setupEventListeners() {
        // Toggle dropdown
        const bellButton = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');

        if (bellButton && dropdown) {
            bellButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropdown.classList.toggle('show');

                if (dropdown.classList.contains('show')) {
                    this.fetchNotifications();
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!bellButton.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }

        // Mark all as read button
        const markAllBtn = document.getElementById('markAllAsRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
    },

    async authenticatedFetch(url, options = {}) {
        const token = localStorage.getItem('api_token');
        const headers = {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            ...options.headers
        };

        let response = await fetch(url, { ...options, headers });

        // If 401, try refreshing token once
        if (response.status === 401 && !this.tokenRefreshed) {
            await this.refreshToken();
            const newToken = localStorage.getItem('api_token');
            headers['Authorization'] = `Bearer ${newToken}`;
            response = await fetch(url, { ...options, headers });
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
                this.unreadCount = data.count;
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
                this.fetchNotifications();
                this.fetchUnreadCount();
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },

    async markAllAsRead() {
        try {
            const response = await this.authenticatedFetch('/apiv/_1/notifications/read-all', {
                method: 'PUT'
            });

            if (response.ok) {
                this.fetchNotifications();
                this.fetchUnreadCount();
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
                this.fetchNotifications();
                this.fetchUnreadCount();
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

        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="dropdown-item text-center text-muted py-3">
                    <i class="fas fa-inbox"></i>
                    <p class="mb-0 mt-2">No notifications</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.notifications.map(notif => {
            const isUnread = !notif.read_at;
            const timeAgo = this.getTimeAgo(notif.created_at);
            const typeIcon = this.getTypeIcon(notif.type);

            return `
                <div class="dropdown-item notification-item ${isUnread ? 'unread' : ''}"
                     data-id="${notif.id}"
                     style="cursor: pointer; border-left: 3px solid ${this.getTypeColor(notif.type)};">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1" onclick="NotificationService.handleNotificationClick(${notif.id}, '${notif.data?.type || ''}')">
                            <div class="d-flex align-items-center mb-1">
                                <i class="${typeIcon} mr-2"></i>
                                <strong>${notif.title}</strong>
                            </div>
                            <p class="mb-1 small">${notif.body || ''}</p>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                        <button class="btn btn-sm btn-link text-danger p-0 ml-2"
                                onclick="event.stopPropagation(); NotificationService.deleteNotification(${notif.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    },

    handleNotificationClick(id, type) {
        // Mark as read
        this.markAsRead(id);

        // Navigate based on type
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

        window.location.href = url;
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
    },

    startPolling() {
        // Poll every 30 seconds
        this.pollInterval = setInterval(() => {
            this.fetchUnreadCount();
        }, 30000);
    },

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    }
};

// Initialize when DOM is ready (with slight delay to let FCM refresh token first)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', async () => {
        if (localStorage.getItem('api_token')) {
            // Wait 1.5 seconds for FCM to potentially refresh the token
            setTimeout(() => {
                NotificationService.init().catch(err => {
                    console.error('[Notifications] Initialization failed:', err);
                });
            }, 1500);
        }
    });
} else {
    if (localStorage.getItem('api_token')) {
        // Wait 1.5 seconds for FCM to potentially refresh the token
        setTimeout(() => {
            NotificationService.init().catch(err => {
                console.error('[Notifications] Initialization failed:', err);
            });
        }, 1500);
    }
}

// Make it globally available
window.NotificationService = NotificationService;
