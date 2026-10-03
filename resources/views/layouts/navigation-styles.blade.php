<style>
    .navbar-toggler { min-width: 44px; min-height: 44px; }
    .navbar .nav-link, .navbar .dropdown-item { min-height: 44px; overflow-wrap: anywhere; white-space: normal; }
    .navbar .nav-link { display: flex; align-items: center; }
    .navbar .account-dropdown .dropdown-item { display: flex; align-items: center; }
    .navbar .account-dropdown .nav-link > span { min-width: 0; }
    .navbar .account-dropdown i,
    .navbar .account-dropdown .dropdown-toggle::after { flex-shrink: 0; }
    .navbar .notification-footer .btn { min-height: 44px; display: inline-flex; align-items: center; }
    .navbar #markAllAsRead { min-height: 44px; }
    .navbar.navbar-light .navbar-nav .nav-link { color: #526070; }
    .navbar.navbar-light .navbar-nav .nav-link:hover, .navbar.navbar-light .navbar-nav .nav-link:focus { color: #263445; }
    .navbar .btn-link { color: #17629b; }
    #notificationBadge { background-color: #b52b27; color: #fff; }
    #notificationDropdown { min-width: 0 !important; width: min(350px, calc(100vw - 30px)); }
    .navbar :focus-visible { outline: 3px solid #17629b; outline-offset: 3px; }
    .notification-modal-close { display: none; }
    #notificationModal .modal-dialog {
        margin: 0;
        max-width: none;
        min-height: 100%;
        display: flex;
        align-items: flex-end;
        padding-top: calc(16px + env(safe-area-inset-top));
        pointer-events: none;
    }
    #notificationModal .modal-content {
        max-height: min(82vh, calc(100vh - 16px - env(safe-area-inset-top)));
        max-height: min(82dvh, calc(100dvh - 16px - env(safe-area-inset-top)));
        border-radius: 12px 12px 0 0;
        border-bottom: 0;
        overflow: hidden;
        color: #263445;
        background: #fff;
    }
    #notificationModal #notificationTray { display: flex; flex-direction: column; min-height: 0; }
    #notificationModal .notification-tray-header {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) 44px;
        padding: 16px 16px 8px;
        flex-shrink: 0;
        color: inherit;
        white-space: normal;
        border-bottom: 1px solid #dce2e8;
    }
    #notificationModal #notificationTrayTitle { font-size: 19px; }
    #notificationModal #markAllAsRead { grid-column: 1 / -1; grid-row: 2; justify-self: end; min-height: 44px; }
    #notificationModal .notification-modal-close {
        display: block;
        grid-column: 2;
        grid-row: 1;
        min-width: 44px;
        min-height: 44px;
        padding: 0;
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 28px;
        line-height: 1;
    }
    #notificationModal .notification-tray-body { overflow-y: auto; overscroll-behavior: contain; min-height: 0; }
    #notificationModal .dropdown-divider { display: none; }
    #notificationModal #notificationList { max-height: none !important; overflow-y: visible !important; }
    #notificationModal .notification-item.unread { background: #f0f7fd; }
    #notificationModal .notification-copy { overflow-wrap: anywhere; }
    #notificationModal .notification-footer { flex-shrink: 0; padding-bottom: env(safe-area-inset-bottom); background: inherit; }
    #notificationModal .notification-footer .btn { min-height: 44px; display: inline-flex; align-items: center; }
    #notificationModal .btn-link { color: #17629b; }
    #notificationModal .text-muted { color: #526070 !important; }
    #notificationModal :focus-visible { outline: 3px solid #17629b; outline-offset: -3px; }
    @media (max-height: 420px) {
        #notificationModal .modal-content {
            max-height: calc(100vh - 16px - env(safe-area-inset-top));
            max-height: calc(100dvh - 16px - env(safe-area-inset-top));
        }
    }
    @media (prefers-color-scheme: dark) {
        /* Theme the shell, not the light content cards on other pages. */
        body { background: #15202c; }
        .navbar { color-scheme: dark; }
        .navbar.bg-white,
        .navbar .dropdown-menu { background: #202b38 !important; color: #edf2f7; }
        .navbar .navbar-brand,
        .navbar.navbar-light .navbar-nav .nav-link,
        .navbar.navbar-light .navbar-nav .nav-link:hover,
        .navbar.navbar-light .navbar-nav .nav-link:focus,
        .navbar .dropdown-item,
        .navbar .dropdown-header { color: #edf2f7; }
        .navbar .text-muted { color: #bec9d6 !important; }
        .navbar .btn-link { color: #8dc9f5; }
        .navbar .text-success { color: #91dbac !important; }
        .navbar .notification-item.unread { background: #293c50; }
        .navbar .notification-item:hover,
        .navbar .notification-item:focus,
        .navbar .notification-item:active,
        .navbar .dropdown-item:hover,
        .navbar .dropdown-item:focus,
        .navbar .dropdown-item:active { background: #35465a; color: #edf2f7; }
        .navbar .dropdown-menu,
        .navbar .dropdown-divider,
        .navbar-toggler { border-color: #929eae; }
        .navbar-toggler-icon { filter: invert(1); }
        .navbar :focus-visible { outline-color: #8dc9f5; }
        #notificationModal { color-scheme: dark; }
        #notificationModal .modal-content { background: #202b38; color: #edf2f7; border-color: #929eae; }
        #notificationModal .notification-tray-header,
        #notificationModal .notification-footer { border-color: #526070 !important; }
        #notificationModal .text-muted { color: #bec9d6 !important; }
        #notificationModal .btn-link { color: #8dc9f5; }
        #notificationModal .notification-item.unread { background: #293c50; }
        #notificationModal :focus-visible { outline-color: #8dc9f5; }
    }
</style>
