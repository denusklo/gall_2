@extends('layouts.app')

@section('body-class', 'profile-settings-page')

@section('content')


<style>
    #profile-settings { --surface: #fff; --ink: #263445; --border: #758190; --accent: #17629b; --on-accent: #fff; --error: #a12222; color: var(--ink); }
    #profile-settings .card, #profile-settings .card-header, #profile-settings .form-control { background: var(--surface); color: var(--ink); border-color: var(--border); }
    #profile-settings .btn-primary { background: var(--accent); color: var(--on-accent); border-color: var(--accent); }
    #profile-settings .btn { min-height: 44px; }
    #profile-settings .btn, #profile-settings .form-control { transition: none; }
    #profile-settings :focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
    #email-verification { border-top: 1px solid var(--border); margin-top: 1.5rem; padding-top: 1.5rem; }
    #email-verification .email-address { overflow-wrap: anywhere; }
    #email-verification .email-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
    #email-verification .btn-outline-secondary { color: var(--ink); border-color: var(--border); background: var(--surface); }
    #email-verification .email-message { min-height: 1.5em; margin-top: .75rem; }
    #profile-settings .invalid-feedback, #email-verification [role="alert"] { color: var(--error); }
    @media (max-width: 575.98px) { #email-verification .email-actions { flex-direction: column; } }
    @media (prefers-color-scheme: dark) {
        body.profile-settings-page { color: #edf2f7; color-scheme: dark; }
        #profile-settings { --surface: #202b38; --ink: #edf2f7; --border: #929eae; --accent: #8dc9f5; --on-accent: #152332; --error: #ffb4b4; }
    }
</style>
<div class="container" id="profile-settings">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Edit and Update User Data') }}</div>

                <div class="card-body">
                    <form id="profile-form" method="POST" action="{{ route('user.update') }}">
                        @method("PUT")
                        @csrf

                        @if($profileUnavailable ?? false)
                            <p role="alert">Firebase is unavailable. Profile editing is paused until you reload this page successfully.</p>
                        @endif
                        <fieldset @if($profileUnavailable ?? false) disabled @endif>
                        <input type="hidden" name='uid' value="{{$uid}}">

                        <div class="form-group row">
                            <label for="name" class="col-md-4 col-form-label text-md-right">{{ __('Display Name') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{$name}}" >

                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="phone" class="col-md-4 col-form-label text-md-right">{{ __('Phone Number') }}</label>

                            <div class="col-md-6">
                                <input id="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{$phone}}" >

                                @error('phone')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Update User') }}
                                </button>
                            </div>
                        </div>
                        </fieldset>
                    </form>

                    @if($emailSelf ?? false)
                    <section id="email-verification" aria-labelledby="email-heading"
                        data-send-url="{{ route('user.email-verification.send') }}"
                        data-status-url="{{ route('user.email-verification.status') }}"
                        data-csrf="{{ csrf_token() }}"
                        data-verified="{{ isset($emailVerified) ? ($emailVerified ? 'true' : 'false') : 'unknown' }}">
                        <h2 id="email-heading" class="h5">Email</h2>
                        <p class="email-address" id="verification-email">{{ $email ?? 'No email address available' }}</p>
                        <p id="verification-state">{{ !isset($emailVerified) ? 'Status unavailable' : ($emailVerified ? 'Verified' : 'Not verified') }}</p>
                        <p id="verification-prompt" @if($emailVerified ?? false) hidden @endif>Verify your email address. You can keep using the gallery. After following the link in your email, return here to check its status.</p>
                        <div class="email-actions">
                            <button type="button" id="verification-send" class="btn btn-primary" @if($emailVerified ?? false) hidden @endif>Send verification email</button>
                            <button type="button" id="verification-check" class="btn btn-outline-secondary">Check status</button>
                        </div>
                        <p id="verification-message" class="email-message" role="status" aria-live="polite" aria-atomic="true"></p>
                        <p id="verification-error" role="alert" aria-atomic="true"></p>
                        <noscript>Enable JavaScript to send a verification email or check its status.</noscript>
                    </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Phone number validation on input
        $('#phone').on('input', function() {
            let phone = $(this).val();
            // Only allow + followed by digits
            let cleaned = phone.replace(/[^\d+]/g, '');
            // Ensure + is only at the beginning
            if (cleaned.indexOf('+') > 0) {
                cleaned = cleaned.replace(/\+/g, '');
                cleaned = '+' + cleaned;
            }
            if (cleaned !== phone) {
                $(this).val(cleaned);
                iziToast.warning({
                    title: 'Invalid Format',
                    message: 'Phone number must be in E.164 format: + followed by digits only (e.g., +1234567890)',
                    position: 'topRight',
                    timeout: 3000
                });
            }
        });

        // Form validation before submit
        $('#profile-form').on('submit', function(e) {
            let phone = $('#phone').val();
            if (phone && !/^\+[1-9]\d{1,14}$/.test(phone)) {
                e.preventDefault();
                iziToast.error({
                    title: 'Invalid Phone Number',
                    message: 'Phone number must be in E.164 format: + followed by 1-15 digits (e.g., +1234567890)',
                    position: 'topRight',
                    timeout: 5000
                });
                return false;
            }
        });
    });

    // Show toast notifications based on flash messages
    @if(session('success'))
        iziToast.success({
            title: 'Success',
            message: @json(session('success')),
            position: 'topRight',
            timeout: 3000
        });
    @endif

    @if(session('error'))
        iziToast.error({
            title: 'Error',
            message: @json(session('error')),
            position: 'topRight',
            timeout: 3000
        });
    @endif
</script>
<script>
(() => {
    const section = document.getElementById('email-verification');
    if (!section) return;
    const send = document.getElementById('verification-send');
    const check = document.getElementById('verification-check');
    const state = document.getElementById('verification-state');
    const email = document.getElementById('verification-email');
    const message = document.getElementById('verification-message');
    const error = document.getElementById('verification-error');
    let verified = section.dataset.verified === 'true';
    let busy = false;
    let retryAt = 0;
    let requestRetryAt = 0;
    let attempted = false;
    const update = () => {
        const requestSeconds = Math.max(0, Math.ceil((requestRetryAt - Date.now()) / 1000));
        const seconds = Math.max(requestSeconds, Math.max(0, Math.ceil((retryAt - Date.now()) / 1000)));
        send.hidden = verified;
        document.getElementById('verification-prompt').hidden = verified;
        send.disabled = busy || seconds > 0;
        check.disabled = busy || requestSeconds > 0;
        check.textContent = requestSeconds > 0 ? `Check status in ${requestSeconds}s` : 'Check status';
        send.textContent = seconds > 0 ? `Send email in ${seconds}s` : (attempted ? 'Resend email' : 'Send verification email');
    };
    setInterval(() => { if (!busy) update(); }, 1000);
    async function request(action) {
        if (busy || Date.now() < requestRetryAt || (action === 'send' && (verified || Date.now() < retryAt))) return;
        // Disabling a focused button can blur it to BODY. Remember intent, not
        // just the final activeElement: a user may move elsewhere and back to BODY.
        let recoverFocus = action === 'send' && document.activeElement === send;
        const movedFocus = () => { recoverFocus = false; };
        const focusedElsewhere = event => {
            if (![send, document.body, document.documentElement].includes(event.target)) movedFocus();
        };
        const navigated = event => { if (event.key === 'Tab') movedFocus(); };
        document.addEventListener('focusin', focusedElsewhere, true);
        document.addEventListener('pointerdown', movedFocus, true);
        document.addEventListener('keydown', navigated, true);
        window.addEventListener('blur', movedFocus);
        busy = true;
        error.textContent = '';
        message.textContent = action === 'send' ? 'Sending…' : 'Checking…';
        section.setAttribute('aria-busy', 'true');
        if (action === 'send') {
            attempted = true;
            // A lost response can still mean delivery. Never automatically retry.
            retryAt = Math.max(retryAt, Date.now() + 60000);
        }
        update();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(action === 'send' ? section.dataset.sendUrl : section.dataset.statusUrl, {
                method: 'POST', credentials: 'same-origin', redirect: 'error', signal: controller.signal,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': section.dataset.csrf },
                body: '{}'
            });
            const headerRetry = Number(response.headers.get('Retry-After'));
            if (response.status === 429) {
                let limited;
                try { limited = await response.json(); } catch (_) { /* Unknown scope blocks both actions. */ }
                const bodyRetry = Number(limited && limited.retry_after);
                const waits = [headerRetry, bodyRetry].filter(value => Number.isFinite(value) && value > 0);
                const wait = waits.length ? Math.max(...waits) : 60;
                const until = Date.now() + Math.min(wait, 86400) * 1000;
                const sendOnly = action === 'send' && limited && limited.outcome === 'rate_limited' && limited.rate_limit_scope === 'send';
                if (sendOnly) retryAt = Math.max(retryAt, until);
                else requestRetryAt = Math.max(requestRetryAt, until);
                throw new Error(sendOnly ? 'Too many emails requested. Wait before sending again. You can still check status.' : 'Too many requests. Wait before checking status or sending an email.');
            }
            if (action === 'send' && Number.isFinite(headerRetry) && headerRetry > 0) retryAt = Math.max(retryAt, Date.now() + headerRetry * 1000);
            if (response.status === 401) throw new Error('Your session expired. Sign in again, then return here.');
            if (response.status === 419) throw new Error('This page expired. Reload it before trying again.');
            let data;
            try { data = await response.json(); } catch (_) { throw new Error('Could not read the response. Check status before trying another send.'); }
            if (!data || typeof data.outcome !== 'string') throw new Error('Unexpected response. Check status before trying another send.');
            // Validate success before trusting any verdict or email. Error responses
            // may still carry an authoritative verdict, e.g. a local sync failure.
            if (response.ok && (typeof data.verified !== 'boolean'
                || !['verified', 'unverified', 'sent'].includes(data.outcome)
                || (data.outcome === 'verified') !== data.verified
                || (data.outcome === 'sent' && action !== 'send'))) {
                throw new Error('Unexpected response. Check status before trying another send.');
            }
            if (typeof data.verified === 'boolean') {
                verified = data.verified;
                state.textContent = verified ? 'Verified' : 'Not verified';
            } else {
                state.textContent = 'Status unavailable';
            }
            if (typeof data.email === 'string') email.textContent = data.email;
            const failures = {
                sync_failed: 'Firebase status was checked, but the local account could not be synced. Check status again later.',
                provider_unavailable: 'Email status is unavailable. Try checking again later.',
                limiter_unavailable: action === 'send' ? 'Sending is temporarily unavailable. Try again later.' : 'Checking status is temporarily unavailable. Try again later.',
                send_failed: 'Delivery could not be confirmed. Check your inbox before trying again.',
                missing_email: 'No email address is available for this account.',
                unexpected_fields: 'The request was not accepted. Reload this page and try again.'
            };
            if (!response.ok) throw new Error(failures[data.outcome] || 'The request failed. Try checking status later.');
            message.textContent = data.outcome === 'sent' ? 'Verification email sent. Check your inbox and spam folder.' : (verified ? 'Your email is verified.' : 'Your email is not verified yet.');
            if (data.mirror === 'email_mismatch') error.textContent = 'Your account has conflicting email addresses. Contact support to update your account details.';
            if (data.mirror === 'not_linked') error.textContent = 'We could not find your linked account. Sign in again.';
        } catch (failure) {
            message.textContent = '';
            error.textContent = failure.name === 'AbortError' ? 'The request timed out. Check your inbox and check status before resending.' : (failure instanceof TypeError ? 'Could not connect. Check status before resending.' : failure.message);
        } finally {
            clearTimeout(timeout);
            busy = false;
            section.setAttribute('aria-busy', 'false');
            update();
            document.removeEventListener('focusin', focusedElsewhere, true);
            document.removeEventListener('pointerdown', movedFocus, true);
            document.removeEventListener('keydown', navigated, true);
            window.removeEventListener('blur', movedFocus);
            if (recoverFocus && verified && !check.disabled && [send, document.body, document.documentElement].includes(document.activeElement)) check.focus();
        }
    }
    send.addEventListener('click', () => request('send'));
    check.addEventListener('click', () => request('status'));
    update();
})();
</script>
@endsection
