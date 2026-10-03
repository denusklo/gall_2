{{-- Include inside the authenticated tray, outside #notificationList. --}}
<section id="devicePushControls" class="px-3 py-2 border-top" aria-labelledby="devicePushHeading">
    <h2 id="devicePushHeading" class="h6 mb-1">This device</h2>
    <p id="devicePushStatus" class="small mb-2" role="status" aria-live="polite" aria-atomic="true">Checking device notification support…</p>
    <div class="d-flex flex-wrap">
        <button id="devicePushEnable" type="button" class="btn btn-sm btn-primary mr-2 mb-2" aria-describedby="devicePushStatus" hidden disabled>Enable notifications</button>
        <button id="devicePushDisable" type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2" aria-describedby="devicePushStatus" hidden>Disable on this device</button>
        <button id="deviceInstall" type="button" class="btn btn-sm btn-outline-secondary mb-2" aria-describedby="deviceInstallHelp" hidden>Install app</button>
    </div>
    <p class="small mb-2">Device push is optional and stays on until you disable it or log out. Web-session expiry does not disable it. Already queued alerts cannot be recalled.</p>
    <p id="deviceInstallHelp" class="small mb-0">Installation is optional. An internet connection is required.</p>
</section>
<style>
    #notificationDropdown { min-width: min(350px, calc(100vw - 2rem)) !important; max-width: calc(100vw - 2rem) !important; }
    #devicePushControls { white-space: normal; overflow-wrap: anywhere; }
    #devicePushControls [hidden] { display: none !important; }
    #devicePushControls .btn { min-height: 36px; white-space: normal; }
    #devicePushControls .btn-primary { background-color: #1769aa; border-color: #1769aa; color: #fff; }
    #devicePushControls .btn-outline-secondary { color: inherit; border-color: currentColor; }
    #devicePushControls .btn:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    @media (prefers-color-scheme: dark) {
        #devicePushControls { background-color: #252b33; color: #f1f3f5; border-color: #56616f !important; }
        #devicePushControls .btn-outline-secondary:hover { background-color: #424b57; color: #fff; }
    }
</style>
