@extends('layouts.app')

@section('content')
<div id="storage-settings-app">
    <storage-settings></storage-settings>
</div>
@push('scripts')
<script src="{{ mix('js/storageSettingsApp.js') }}"></script>
@endpush
@endsection
