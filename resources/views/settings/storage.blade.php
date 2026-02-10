@extends('layouts.app')

@section('content')
<div id="storage-settings-app">
    <storage-settings></storage-settings>
</div>
@section('scripts')
<script src="{{ mix('js/storageSettingsApp.js') }}"></script>
@endsection
@endsection
