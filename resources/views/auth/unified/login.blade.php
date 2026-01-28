@extends('layouts.app')

@section('title', 'Login - Unified Authentication')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    {{ __('Login') }}
                    <small class="text-muted">- Unified Authentication (Firebase & Sanctum)</small>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login.post') }}">
                        @csrf

                        <div class="alert alert-info">
                            <strong>Unified Login:</strong> Use your existing credentials to log in.
                            Your account will be synchronized between both Firebase and MySQL authentication systems.
                        </div>

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Login') }}
                                </button>

                                <a class="btn btn-link" href="{{ route('register') }}">
                                    {{ __('Don\'t have an account? Register') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-3 text-center text-muted small">
                <p>This unified login works with both authentication systems:</p>
                <ul class="list-inline">
                    <li class="list-inline-item"><span class="badge badge-secondary">Firebase</span></li>
                    <li class="list-inline-item">+</li>
                    <li class="list-inline-item"><span class="badge badge-secondary">MySQL/Sanctum</span></li>
                </ul>
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
            message: '{{ session('success') }}',
            position: 'topRight',
            timeout: 3000
        });
    @endif

    @if(session('error'))
        iziToast.error({
            title: 'Error',
            message: '{{ session('error') }}',
            position: 'topRight',
            timeout: 3000
        });
    @endif
</script>
@endsection
