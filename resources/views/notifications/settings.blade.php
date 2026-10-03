@extends('layouts.app')

@section('body-class', 'notification-page')

@section('content')
@include('partials.notifications-styles')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h1 class="h5">{{ __('Notification settings') }}</h1>
                </div>
                <div class="card-body">
                    <nav class="mb-3" aria-label="Notification pages">
                        <a href="{{ route('notifications.index') }}">{{ __('History') }}</a>
                        <a href="{{ route('settings.notifications') }}" aria-current="page">{{ __('Settings') }}</a>
                    </nav>
                    <p>These controls apply only to this browser or installed app on this device. They do not change notifications on your other devices.</p>
                    <p>Your notification history stays available whether device push is enabled or not.</p>
                    @include('partials.device-notifications')
                    <section class="mt-3" aria-labelledby="notificationInstallHeading">
                        <h2 id="notificationInstallHeading" class="h6">{{ __('Using notifications on your phone') }}</h2>
                        <p class="small mb-2">On iPhone or iPad, notifications require iOS or iPadOS 16.4 or later and a Home Screen app. In Safari or Chrome, use the browser's Share menu and choose Add to Home Screen. If Chrome does not offer it, open this site in Safari.</p>
                        <p class="small mb-2">Then open the app from its Home Screen icon, sign in if asked, and enable notifications here. Notifications do not work in an ordinary iPhone or iPad browser tab.</p>
                        <p class="small mb-0">On supported Android browsers, installation is optional. You can enable notifications in a browser tab. If you want to install the app, use Install app when offered or your browser's install menu.</p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
