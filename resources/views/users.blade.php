@extends('layouts.app')

@section('content')


<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">Registered User List</div>
                <div class="card-body">
                    @if ($loadError ?? null)
                        <div class="alert alert-danger" role="alert">{{ $loadError }}</div>
                    @endif
                    @if (session('success'))
                        <div class="alert alert-success" role="status">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Display Name</th>
                                <th>Phone</th>
                                <th>Email ID</th>
                                <th>Edit</th>
                                <th>Delete</th>
                                <th>Test Notification</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                @php
                                    $tOwner = ($user->customClaims['owner'] ?? null) === true;
                                    $tAdmin = ($user->customClaims['admin'] ?? null) === true;
                                    $isSelf = $user->uid === $viewerUid;
                                    // Server enforces this too; the UI just mirrors it.
                                    $canEdit = $isSelf || (!$tOwner && (!$tAdmin || $viewerIsOwner));
                                    $canDelete = !$tOwner && ($isSelf || !$tAdmin || $viewerIsOwner);
                                @endphp
                                <tr>
                                    <td>{{$loop->iteration}}</td>
                                    <td>{{$user->displayName}}</td>
                                    <td>{{$user->phoneNumber}}</td>
                                    <td>{{$user->email}}</td>
                                    <td>
                                        @if ($canEdit)
                                            <form action="{{route('user.edit')}}" method="POST">
                                                @csrf
                                                <input type="hidden" name='uid' value="{{$user->uid}}">
                                                <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-secondary btn-sm" disabled title="Protected account">{{ $tOwner ? 'Protected Owner' : 'Protected Admin' }}</button>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($tOwner)
                                            <button type="button" class="btn btn-secondary btn-sm" disabled title="Owner accounts cannot be deleted">Protected Owner</button>
                                        @elseif (!$canDelete)
                                            <button type="button" class="btn btn-secondary btn-sm" disabled title="Only an owner can delete an admin">Protected Admin</button>
                                        @else
                                            <form action="{{route('user.delete')}}" method="POST">
                                                @method('delete')
                                                {{ csrf_field() }}
                                                <input type="hidden" name='uid' value="{{$user->uid}}">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="btn btn-info btn-sm test-notification-btn"
                                                data-uid="{{$user->uid}}"
                                                data-name="{{$user->displayName}}"
                                                data-loading-text="Sending...">
                                            <i class="fas fa-bell"></i> Test
                                        </button>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    @if ($loadError ?? null)
                        Request count is unavailable.
                    @else
                        There are total {{ $totalRequests }} {{ (int) $totalRequests === 1 ? 'request' : 'requests' }}.
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Users Page] DOM loaded, setting up test notification buttons...');

    // Handle test notification button clicks
    const buttons = document.querySelectorAll('.test-notification-btn');
    console.log('[Users Page] Found ' + buttons.length + ' test notification buttons');

    buttons.forEach(function(button) {
        button.addEventListener('click', async function() {
            console.log('[Users Page] Test button clicked!');
            const uid = this.dataset.uid;
            const userName = this.dataset.name || 'User';
            const originalText = this.innerHTML;

            console.log('[Users Page] Sending test notification to:', userName, '(UID:', uid + ')');

            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const token = await window.ApiToken.get();
                if (!token) {
                    throw new Error('Unable to authenticate the notification request. Please refresh the page and try again.');
                }

                const response = await fetch('/apiv/_1/fcm/test-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        firebase_uid: uid,
                        title: 'Test Notification',
                        body: 'This is a test notification sent to ' + userName
                    })
                });

                const result = await response.json();
                console.log('[Users Page] ========================================');
                console.log('[Users Page] Test Notification Result:');
                console.log('[Users Page] User:', userName);
                console.log('[Users Page] Success:', result.success);
                console.log('[Users Page] Domain:', result.domain);
                console.log('[Users Page] Tokens Sent:', result.tokens_sent || 0);
                console.log('[Users Page] Total Tokens:', result.all_tokens_count || 0);
                console.log('[Users Page] Tokens Preview:', result.tokens_preview || []);
                console.log('[Users Page] Full Response:', result);
                console.log('[Users Page] ========================================');

                if (response.ok && result.success) {
                    // Success - show success toast
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Success!',
                            message: result.message || ('Test notification sent to ' + userName),
                            position: 'topRight',
                            timeout: 5000
                        });
                    } else {
                        alert(result.message || ('Test notification sent to ' + userName));
                    }
                } else if (result.has_tokens === false) {
                    // User has no tokens
                    if (typeof iziToast !== 'undefined') {
                        iziToast.warning({
                            title: 'No Devices',
                            message: userName + ' has no registered devices. Notifications cannot be sent.',
                            position: 'topRight',
                            timeout: 5000
                        });
                    } else {
                        alert(userName + ' has no registered devices.');
                    }
                } else {
                    // Error
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({
                            title: 'Error',
                            message: result.message || 'Failed to send test notification',
                            position: 'topRight',
                            timeout: 5000
                        });
                    } else {
                        alert('Error: ' + (result.message || 'Failed to send test notification'));
                    }
                }
            } catch (error) {
                console.error('[Users Page] Error sending test notification:', error);
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({
                        title: 'Error',
                        message: error.message || 'Failed to send test notification. Please try again.',
                        position: 'topRight',
                        timeout: 5000
                    });
                } else {
                    alert(error.message || 'Error: Failed to send test notification');
                }
            } finally {
                // Reset button state
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    });
});
</script>
@endsection

@endsection

