@extends('layouts.app')

@section('content')


<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">Registered User List</div>
                <div class="card-body">
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
                                <tr>
                                    <td>{{$loop->iteration}}</td>
                                    <td>{{$user->displayName}}</td>
                                    <td>{{$user->phoneNumber}}</td>
                                    <td>{{$user->email}}</td>
                                    <td>
                                        <form action="{{route('user.edit')}}" method="POST">
                                            @csrf
                                            <input type="hidden" name='uid' value="{{$user->uid}}">
                                            <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form action="{{route('user.delete')}}" method="delete">
                                            @method('delete')
                                            {{ csrf_field() }}
                                            <input type="hidden" name='uid' value="{{$user->uid}}">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
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
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <a href="{{route('request.create')}}" class="btn btn-success btn-block">Create New Request</a>
                </div>
                <div class="card-body">
                    There are total {{$totalRequests}} request/s.
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Users Page] DOM loaded, setting up test notification buttons...');

    // Get API token
    const getApiToken = function() {
        return localStorage.getItem('api_token');
    };

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
                const response = await fetch('/apiv/_1/fcm/test-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + getApiToken(),
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
                console.log('[Users Page] Response:', result);

                if (response.ok && result.success) {
                    // Success - show success toast
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Success!',
                            message: 'Test notification sent to ' + userName + '. Check your device for the notification.',
                            position: 'topRight',
                            timeout: 5000
                        });
                    } else {
                        alert('Test notification sent to ' + userName);
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
                        message: 'Failed to send test notification. Please try again.',
                        position: 'topRight',
                        timeout: 5000
                    });
                } else {
                    alert('Error: Failed to send test notification');
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

