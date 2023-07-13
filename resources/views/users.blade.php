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
                                        <form action="{{route('user.edit')}}" method="get">
                                            {{ csrf_field() }}
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
                    There are total {{$numchildren}} user/s.
                </div>
            </div>
        </div>
    </div>
</div>



@endsection

