<!-- resources/views/galleries/index.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container">
    <div id="gallery-app"></div>
</div>
@endsection

@section('scripts')
<script src="{{ mix('js/galleryApp.js') }}"></script>
@endsection
