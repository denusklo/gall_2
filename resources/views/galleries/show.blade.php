@extends('layouts.app')

@section('content')
<div id="gallery-detail-app" data-gallery-id="{{ $galleryId }}"></div>
@endsection

@section('scripts')
<script src="{{ mix('js/galleryDetailApp.js') }}"></script>
@endsection
