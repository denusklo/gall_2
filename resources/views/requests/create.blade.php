@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Create New Request</div>
            <div class="card-body">
                <form action="{{route('request.store')}}" method="POST" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="col-md-6">
                            <label for="title">Name</label>
                            <input type="text" name="name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="cover">Age</label>
                            <input type="number" name="age" class="form-control">
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="title">Phone No</label>
                            <input type="text" name="phone_no" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="cover">Email Address</label>
                            <input type="email" name="email" class="form-control" value="@gmail.com">
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="title">Full address</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="description">Description of the request</label>
                            <textarea name="description" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                    <br>
                    <button class="btn btn-primary" type="submit">Create request</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection