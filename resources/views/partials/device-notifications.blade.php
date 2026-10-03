{{-- Include once on the authenticated notification settings page. IDs are used by fcm.js. --}}
<section id="devicePushControls" aria-labelledby="devicePushHeading">
    <h2 id="devicePushHeading" class="h6 mb-1">This device</h2>
    <p id="devicePushStatus" class="small mb-2" role="status" aria-live="polite" aria-atomic="true">Checking device notification support…</p>
    <div class="d-flex flex-wrap">
        <button id="devicePushEnable" type="button" class="btn btn-sm btn-primary mr-2 mb-2" aria-describedby="devicePushStatus" hidden disabled>Enable notifications</button>
        <button id="devicePushDisable" type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2" aria-describedby="devicePushStatus" hidden>Disable on this device</button>
        <button id="deviceInstall" type="button" class="btn btn-sm btn-outline-secondary mb-2" aria-describedby="deviceInstallHelp" hidden>Install app</button>
    </div>
    <p class="small mb-2">Device push is optional and stays on until you disable it or log out. Web-session expiry does not disable it. Already queued alerts cannot be recalled.</p>
    <p id="deviceInstallHelp" class="small mb-0">Installation is optional on supported Android browsers. An internet connection is required.</p>
</section>
<style>
    .notification-page #devicePushControls { white-space: normal; overflow-wrap: anywhere; }
    .notification-page #devicePushControls [hidden] { display: none !important; }
    .notification-page #devicePushControls .btn { min-height: 44px; white-space: normal; }
    .notification-page #devicePushControls .btn-primary { background-color: #1769aa; border-color: #1769aa; color: #fff; }
    .notification-page #devicePushControls .btn-primary:hover { background-color: #12568c; border-color: #12568c; }
    .notification-page #devicePushControls .btn-outline-secondary { color: inherit; border-color: currentColor; }
    .notification-page #devicePushControls .btn-outline-secondary:hover { background-color: #526070; color: #fff; }
    .notification-page #devicePushControls .btn:focus-visible { outline: 3px solid #17629b; outline-offset: 3px; }
    @media (prefers-color-scheme: dark) {
        .notification-page #devicePushControls .btn-outline-secondary:hover { background-color: #424b57; color: #fff; }
        .notification-page #devicePushControls .btn:focus-visible { outline-color: #8dc9f5; }
    }
</style>
