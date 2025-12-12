<div class="card-body">
  {{ __('You are logged in!') }}
</div>

@if (session('status'))
<script>
    iziToast.success({
        title: 'Success',
        message: '{{ session('status') }}',
        position: 'topRight',
        timeout: 3000
    });
</script>
@endif