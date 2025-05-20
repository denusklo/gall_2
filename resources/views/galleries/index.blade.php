<!-- resources/views/galleries/index.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container">
    <div id="gallery-app"></div>
</div>
@endsection

@section('scripts')
<script src="{{ mix('js/app.js') }}"></script>
<script>
    // After user logs in through the web interface
async function getApiToken() {
    try {
        const response = await axios.get('/api/token');
        localStorage.setItem('api_token', response.data.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
    } catch (error) {
        console.error('Failed to get API token:', error);
    }
}

// Call this function when your app initializes
getApiToken();
</script>
@endsection
