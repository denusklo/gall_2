@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Create New Request</div>
            <div class="card-body">
                <form action="{{route('requests.store')}}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <label for="name">Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="age">Age</label>
                            <input type="number" name="age" id="age" class="form-control" required>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="phone_no">Phone No</label>
                            <input type="text" name="phone_no" id="phone_no" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="location">Full address</label>
                            <input type="text" name="location" id="location" class="form-control" required>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="description">Description of the request</label>
                            <textarea name="description" id="description" rows="3" class="form-control" required></textarea>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="allow_multiple_completers" id="allow_multiple_completers" value="1" class="custom-control-input">
                                    <label class="custom-control-label" for="allow_multiple_completers">Allow multiple users to complete this request</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="required-completers-group" style="display: none;">
                        <div class="col-md-6">
                            <label for="required_completers">Number of users needed</label>
                            <input type="number" name="required_completers" id="required_completers" class="form-control" value="2" min="2" max="50">
                            <small class="form-text text-muted">Minimum 2, maximum 50 users</small>
                        </div>
                    </div>
                    <br>
                    <button class="btn btn-primary" type="submit">Create request</button>
                    <a href="{{ route('requests.my') }}" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Show/hide required completers input based on checkbox
        $('#allow_multiple_completers').on('change', function() {
            if ($(this).is(':checked')) {
                $('#required-completers-group').slideDown();
            } else {
                $('#required-completers-group').slideUp();
            }
        });
    });
</script>
@endsection