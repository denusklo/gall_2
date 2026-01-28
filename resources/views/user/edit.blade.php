@extends('layouts.app')

@section('content')


<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Edit and Update User Data') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('user.update') }}">
                        @method("PUT")
                        @csrf

                        <input type="hidden" name='uid' value="{{$uid}}">

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

@section('scripts')
<script>
    $(document).ready(function() {
        // Phone number validation on input
        $('#phone').on('input', function() {
            let phone = $(this).val();
            // Only allow + followed by digits
            let cleaned = phone.replace(/[^\d+]/g, '');
            // Ensure + is only at the beginning
            if (cleaned.indexOf('+') > 0) {
                cleaned = cleaned.replace(/\+/g, '');
                cleaned = '+' + cleaned;
            }
            if (cleaned !== phone) {
                $(this).val(cleaned);
                iziToast.warning({
                    title: 'Invalid Format',
                    message: 'Phone number must be in E.164 format: + followed by digits only (e.g., +1234567890)',
                    position: 'topRight',
                    timeout: 3000
                });
            }
        });

        // Form validation before submit
        $('form').on('submit', function(e) {
            let phone = $('#phone').val();
            if (phone && !/^\+[1-9]\d{1,14}$/.test(phone)) {
                e.preventDefault();
                iziToast.error({
                    title: 'Invalid Phone Number',
                    message: 'Phone number must be in E.164 format: + followed by 1-15 digits (e.g., +1234567890)',
                    position: 'topRight',
                    timeout: 5000
                });
                return false;
            }
        });
    });

    // Show toast notifications based on flash messages
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
