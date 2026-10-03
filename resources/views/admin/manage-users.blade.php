@extends('layouts.app')

@section('content')
<style>
    /* Bootstrap 4's success/info backgrounds need darker shades for small white text. */
    #manage-users .badge-success { background-color: #21783a; }
    #manage-users .badge-info { background-color: #117483; color: #fff; }
    #manage-users .table-responsive:focus-visible { outline: 3px solid #17629b; outline-offset: 3px; }
</style>
<div class="container" id="manage-users">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Manage Users</div>

                <div class="card-body">
                    <div class="table-responsive" role="region" aria-label="User permissions" tabindex="0">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                @php
                                    $targetIsOwner = ($user->customClaims['owner'] ?? null) === true;
                                    $targetIsAdmin = ($user->customClaims['admin'] ?? null) === true;
                                @endphp
                                <tr>
                                    <td>{{ $user->displayName ?? 'No name' }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @if ($targetIsOwner)
                                            <span class="badge badge-dark">Owner</span>
                                        @elseif ($targetIsAdmin)
                                            <span class="badge badge-success">Admin</span>
                                        @else
                                            <span class="badge badge-info">User</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($targetIsOwner)
                                            <span class="text-muted">Protected Owner</span>
                                        @elseif ($viewerIsOwner && $user->uid !== $viewerUid)
                                            @if ($targetIsAdmin)
                                                <form action="{{ route('admin.remove-admin', $user->uid) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-warning">Remove Admin</button>
                                                </form>
                                            @else
                                                <form action="{{ route('admin.make-admin', $user->uid) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary">Make Admin</button>
                                                </form>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
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
@endsection