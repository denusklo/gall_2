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

    <!-- Firebase SDK for FCM -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>

    <!-- FCM Configuration -->
    <script>
        window.FIREBASE_CONFIG = {
            apiKey: "{{ config('services.firebase.api_key') }}",
            authDomain: "{{ config('services.firebase.auth_domain') }}",
            databaseURL: "{{ config('services.firebase.database_url') }}",
            projectId: "{{ config('services.firebase.project_id') }}",
            storageBucket: "{{ config('services.firebase.storage_bucket') }}",
            messagingSenderId: "{{ config('services.firebase.messaging_sender_id') }}",
            appId: "{{ config('services.firebase.app_id') }}",
            measurementId: "{{ config('services.firebase.measurement_id') }}",
            vapidKey: "{{ config('services.firebase.vapid_key') }}"
        };

        // Debug: Log Firebase config
        console.log('Firebase Config loaded:', window.FIREBASE_CONFIG);

        // Check if config is valid
        if (!window.FIREBASE_CONFIG.apiKey || window.FIREBASE_CONFIG.apiKey === '') {
            console.error('Firebase API Key is missing! Please check your .env file.');
        }
        if (!window.FIREBASE_CONFIG.vapidKey || window.FIREBASE_CONFIG.vapidKey === '') {
            console.warn('Firebase VAPID Key is missing. FCM may not work properly.');
        }
    </script>
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
                                <a class="nav-link" href="{{ route('requests.my') }}">{{ __('My Requests') }}</a>
                            </li>

                            <!-- All Requests - visible to all authenticated users -->
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('requests.all') }}">{{ __('All Requests') }}</a>
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

                            <!-- Storage Settings Link -->
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('settings.storage') }}">
                                    {{ __('Storage Settings') }}
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
                            @endif
                        @endif
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Notification Bell (for authenticated users) -->
                        @if (session()->has('verified_user_id') || Auth::check())
                            <li class="nav-item dropdown">
                                <a id="notificationBell" class="nav-link position-relative" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-bell" style="font-size: 1.25rem;"></i>
                                    <span id="notificationBadge" class="badge badge-danger badge-pill position-absolute" style="top: 0; right: -5px; display: none;">0</span>
                                </a>
                                <div id="notificationDropdown" class="dropdown-menu dropdown-menu-right" style="min-width: 350px; max-width: 400px;">
                                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                                        <span><strong>Notifications</strong></span>
                                        <button id="markAllAsRead" class="btn btn-sm btn-link p-0">Mark all as read</button>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div id="notificationList" style="max-height: 400px; overflow-y: auto;">
                                        <!-- Notifications will be rendered here -->
                                        <div class="dropdown-item text-center text-muted py-3">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            <p class="mb-0 mt-2">Loading...</p>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endif

                        <!-- Authentication Links -->
                        @guest
                            @if (!session()->has('verified_user_id'))
                                @if(config('app.debug'))
                                    <!-- Debug mode: Dropdowns with alternatives -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" role="button"
                                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            {{ __('Login') }}
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="loginDropdown">
                                            <a class="dropdown-item font-weight-bold" href="{{ route('login') }}">
                                                <i class="fas fa-sign-in-alt mr-2"></i>{{ __('Login') }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <div class="dropdown-header text-muted small">{{ __('Alternatives:') }}</div>
                                            <a class="dropdown-item text-muted small" href="{{ route('mysql.login.form') }}">
                                                <i class="fas fa-database mr-2"></i>{{ __('MySQL Only') }}
                                            </a>
                                            <a class="dropdown-item text-muted small" href="{{ route('firebase.login.form') }}">
                                                <i class="fas fa-fire mr-2"></i>{{ __('Firebase Only') }}
                                            </a>
                                        </div>
                                    </li>
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" role="button"
                                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            {{ __('Register') }}
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="registerDropdown">
                                            <a class="dropdown-item font-weight-bold" href="{{ route('register') }}">
                                                <i class="fas fa-user-plus mr-2"></i>{{ __('Register') }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <div class="dropdown-header text-muted small">{{ __('Alternatives:') }}</div>
                                            <a class="dropdown-item text-muted small" href="{{ route('mysql.register') }}">
                                                <i class="fas fa-database mr-2"></i>{{ __('MySQL Only') }}
                                            </a>
                                            <a class="dropdown-item text-muted small" href="{{ route('firebase.create') }}">
                                                <i class="fas fa-fire mr-2"></i>{{ __('Firebase Only') }}
                                            </a>
                                        </div>
                                    </li>
                                @else
                                    <!-- Production mode: Simple buttons -->
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                    </li>
                                @endif
                            @else
                                <li class="nav-item dropdown">
                                    <a id="firebaseUserDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span>{{ session()->get('displayName') }}</span>
                                    </a>

                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="firebaseUserDropdown">
                                        <a class="dropdown-item" href="{{ route('user.edit') }}">
                                            <i class="fas fa-user-edit mr-2"></i>{{ __('Edit Profile') }}
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="{{ route('logout') }}">
                                            <i class="fas fa-sign-out-alt mr-2"></i>{{ __('Logout') }}
                                        </a>
                                    </div>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                    @if(Auth::user()->hasDualAuth())
                                        <i class="fas fa-sync ml-1 text-success" title="Dual Auth"></i>
                                    @endif
                                </a>

                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    @if(session()->has('verified_user_id'))
                                        <a class="dropdown-item" href="{{ route('user.edit') }}">
                                            <i class="fas fa-user-edit mr-2"></i>{{ __('Edit Profile') }}
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    @endif

                                    @if(Auth::user()->hasDualAuth() || session()->has('verified_user_id'))
                                        <a class="dropdown-item" href="{{ route('logout') }}">
                                            <i class="fas fa-sign-out-alt mr-2"></i>{{ __('Logout') }}
                                        </a>
                                    @else
                                        <a class="dropdown-item" href="#"
                                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="fas fa-sign-out-alt mr-2"></i>{{ __('Logout') }}
                                        </a>
                                    @endif

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

    {{-- FCM Service - Load for all authenticated users --}}
    @if(session()->has('verified_user_id') || Auth::check())
        <script src="{{ mix('js/fcm.js') }}"></script>
        <script src="{{ mix('js/notifications.js') }}"></script>
    @endif

    @yield('scripts')
</body>

</html>
