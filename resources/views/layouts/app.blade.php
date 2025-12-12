<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    {{-- Use mix() helper for both HMR and production builds --}}
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ route('home') }}">
                    {{ __('Gall-2') }}
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse"
                    data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                    aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav mr-auto">
                        @if (session()->has('verified_user_id') || Auth::check())
                            @php
                                $isAdmin = false;

                                // Check for Firebase admin
                                if (session()->has('verified_user_id')) {
                                    try {
                                        $uid = session()->get('verified_user_id');
                                        $auth = app('firebase.auth');
                                        $user = $auth->getUser($uid);
                                        $customClaims = $user->customClaims ?? [];
                                        $isAdmin = isset($customClaims['admin']) && $customClaims['admin'] === true;
                                    } catch (\Exception $e) {
                                        // Silently fail
                                    }
                                }

                                // Check for native MySQL admin
                                if (Auth::check() && !$isAdmin) {
                                    $isAdmin = Auth::user()->is_admin == 1;
                                }
                            @endphp

                            <!-- Link visible to all authenticated users -->
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('request.index') }}">{{ __('My Requests') }}</a>
                            </li>

                            <!-- Images Link - Individual images -->
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('images.index') }}">{{ __('Images') }}</a>
                            </li>

                            <!-- Galleries Link - Albums/Collections (coming soon) -->
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('galleries.index') }}" title="Albums feature - uses same view for now">
                                    {{ __('Galleries') }}
                                </a>
                            </li>

                            @if ($isAdmin)
                                <!-- Admin Only Links -->
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('users') }}">{{ __('Users') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link"
                                        href="{{ route('admin.users') }}">{{ __('Manage Admins') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link"
                                        href="{{ route('requests.index') }}">{{ __('All Requests') }}</a>
                                </li>
                            @endif
                        @endif
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (!session()->has('verified_user_id'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('user.login.form') }}">{{ __('Login') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('user.create') }}">{{ __('Register') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link"
                                        href="{{ route('firebase.login.form') }}">{{ __('Firebase Login') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link"
                                        href="{{ route('firebase.create') }}">{{ __('Firebase Register') }}</a>
                                </li>
                            @else
                                <li class="nav-item">
                                    <a href="{{ route('user.edit') }}" class="nav-link">
                                        <span>{{ session()->get('displayName') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('firebase.logout') }}">{{ __('Logout') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>

    {{-- Bootstrap Bundle (jQuery, Popper.js, Bootstrap, iziToast) --}}
    <script src="{{ mix('js/bootstrap-bundle.js') }}"></script>

    @yield('scripts')
</body>

</html>
