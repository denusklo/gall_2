@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Manage Users</div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

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
                                @if ($user->uid !== session()->get('verified_user_id'))
                                    <tr>
                                        <td>{{ $user->displayName ?? 'No name' }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            @if (isset($user->customClaims['admin']) && $user->customClaims['admin'] === true)
                                                <span class="badge bg-success">Admin</span>
                                            @else
                                                <span class="badge badge-info">Regular User</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (isset($user->customClaims['admin']) && $user->customClaims['admin'] === true)
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
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection