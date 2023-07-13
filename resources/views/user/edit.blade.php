@extends('layouts.app')

@section('content')


<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if (session()->get('error'))
                <h5 class='alert alert-warning'>
                    {{session()->get('error')}}
                </h5>
            @elseif (session()->get('success'))
                <h5 class='alert alert-success'>
                    {{session()->get('success')}}
                </h5>
            @endif
            <div class="card">
                <div class="card-header">{{ __('Edit and Update User Data') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('user.update') }}">
                        @method("PUT")
                        @csrf

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
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('Phone Number') }}</label>

                            <div class="col-md-6">
                                <input id="phone" type="phone" class="form-control @error('phone number') is-invalid @enderror" name="phone" value="{{$phone}}" >

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
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
