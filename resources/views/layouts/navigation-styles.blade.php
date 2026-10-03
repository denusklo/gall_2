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
    }
</style>
