@extends('layouts.app')

@section('content')


<div class="container">
    <div class="row">
        <div class="col-md-12">
            @if (session()->get('error'))
                <h5 class='alert alert-warning'>
                    {{session()->get('error')}}
                </h5>
            @elseif (session()->get('success'))
                <h5 class='alert alert-success'>
                    {{session()->get('success')}}
                </h5>
            @endif
            <div class="row justify-content-center">
        </div>
    </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">Your Request</div>
                <div class="card-body">
                    @if ($data)
                        @foreach ($data as $arrayKey => $key)
                            <table class="table table-bordered" style="table-layout: fixed">
                                <thead>
                                    <tr>
                                        <th scope="col"></th>
                                        <th scope="col">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th>Name</th>
                                        <td>{{$key['name']}}</td>
                                    </tr>
                                    <tr>
                                        <th>Age</th>
                                        <td>{{$key['age']}}</td>
                                    </tr>
                                    <tr>
                                        <th>Phone No</th>
                                        <td>{{$key['phone_no']}}</td>
                                    </tr>
                                    <tr>
                                        <th>Location</th>
                                        <td>{{$key['location']}}</td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td>{{$key['email']}}</td>
                                    </tr>
                                    <tr >
                                        <th>Description</th>
                                        <td>{{$key['description']}}</td>
                                    </tr>
                                    <tr >
                                        <th>Action</th>
                                        <td>
                                            <form action="{{route('request.destroy', $arrayKey)}}" method="POST" style="display: inline-block">
                                                {{ csrf_field() }}
                                                @method('delete')
                                                <input type="hidden" name="ref" value="{{$arrayKey}}">
                                                <button type="submit" name="delete_data" class="btn btn-danger" >Delete</button>
                                            </form>
                                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#updateData{{$loop->iteration}}">
                                                Update Data
                                            </button>
                                            <div class="modal fade" id="updateData{{$loop->iteration}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="updateData{{$loop->iteration}}">Update data</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form action="{{route('request.update', [session()->get('verified_user_id')])}}" method="POST" enctype="multipart/form-data">
                                                                @method('PUT')
                                                                {{ csrf_field() }}
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <label for="title"> Name</label>
                                                                        <input type="text" name="name" class="form-control" value="{{$key['name']}}">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label for="cover">Age</label>
                                                                        <input type="number" name="age" class="form-control" value="{{$key['age']}}">
                                                                    </div>
                                                                </div>
                                                                <br>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <label for="title">Phone No</label>
                                                                        <input type="text" name="phone_no" class="form-control" value="{{$key['phone_no']}}">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label for="cover">Email Address</label>
                                                                        <input type="email" name="email" class="form-control" value="{{$key['email']}}">
                                                                    </div>
                                                                </div>
                                                                <br>
                                                                <div class="row">
                                                                    <div class="col-md-12">
                                                                        <label for="title">Full address</label>
                                                                        <input type="text" name="location" class="form-control" value="{{$key['location']}}">
                                                                    </div>
                                                                </div>
                                                                <br>
                                                                <div class="row">
                                                                    <div class="col-md-12">
                                                                        <label for="description">Description of the request</label>
                                                                        <textarea name="description" rows="3" class="form-control" >{{$key['description']}}</textarea>
                                                                    </div>
                                                                </div>
                                                                <br>
                                                                <input type="hidden" name="ref" value="{{$loop->iteration}}">
                                                                <button class="btn btn-primary" type="submit">Submit update</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        @endforeach
                    @else
                        Please enter request
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <a href="{{route('request.create')}}" class="btn btn-success btn-block">Create New Request</a>
                </div>
                {{-- <div class="card-body"> --}}
                    {{-- There are total --}}
                    <?php
                        // use App\Http\Controllers\FirebaseController;
                        // $controller = new FirebaseController();
                        // $data = $controller->database->getReference('Requests')->getSnapshot()->numchildren();
                        // echo $data;
                        // echo json_encode($data, JSON_PRETTY_PRINT);
                    ?>
                    {{-- request/s. --}}
                {{-- </div> --}}
            </div>
        </div>
    </div>
</div>
@endsection