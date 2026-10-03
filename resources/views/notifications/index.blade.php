@extends('layouts.app')

@section('body-class', 'notification-page')

@section('content')
@include('partials.notifications-styles')
<style>
    #notificationHistory { max-width: 900px; margin: 0 auto; }
    #notificationHistory .history-toolbar,
    #notificationHistory .history-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; }
    #notificationHistory .history-toolbar { padding: 1rem; border-bottom: 1px solid rgba(128,128,128,.2); }
    #notificationHistory .history-pagination { padding: 1rem; border-top: 1px solid rgba(128,128,128,.2); }
    #notificationHistory .btn { min-height: 44px; min-width: 44px; }
    #notificationHistory [aria-disabled="true"] { opacity: .65; }
    #notificationHistory button:focus-visible, #notificationHistory a:focus-visible { outline: 2px solid #3490dc; outline-offset: 3px; }
    #notificationHistory .btn-link, #notificationHistory a { color: #17629b; }
    #notificationHistory .btn-outline-primary { color: #17629b; border-color: #17629b; }
    #notificationHistory .btn-outline-primary.active,
    #notificationHistory .btn-outline-primary:hover { background: #17629b; color: #fff; }
    #notificationHistory .unread { border-left: 3px solid #3490dc; }
    @media (prefers-color-scheme: dark) {
        #notificationHistory { color: #edf2f7; }
        #notificationHistory .card { background: #202b38; border-color: #929eae; }
        #notificationHistory .text-muted { color: #bec9d6 !important; }
        #notificationHistory .notification-item.unread { background: #293c50; }
        #notificationHistory .notification-item:hover,
        #notificationHistory .notification-item:focus,
        #notificationHistory .notification-item:active { background: #35465a; color: #edf2f7; }
        #notificationHistory .btn-link, #notificationHistory a { color: #8dc9f5; }
        #notificationHistory .btn-outline-primary { color: #8dc9f5; border-color: #8dc9f5; }
        #notificationHistory .btn-outline-primary.active,
        #notificationHistory .btn-outline-primary:hover { background: #8dc9f5; color: #15202c; }
        #notificationHistory button:focus-visible, #notificationHistory a:focus-visible { outline-color: #8dc9f5; }
    }
    #notificationHistory .notification-item:not(.unread) { border-left: 3px solid transparent; }
    @media (max-width: 380px) {
        #notificationHistory .history-toolbar, #notificationHistory .history-pagination { padding: .75rem; }
        #notificationHistory .history-pagination { justify-content: flex-start; }
    }
</style>
<div class="container"><div class="row justify-content-center"><div class="col-md-8">
<section id="notificationHistory" aria-labelledby="historyTitle">
    <div class="card">
        <header class="card-header">
            <h1 id="historyTitle" class="h5">{{ __('Notifications') }}</h1>
            <nav aria-label="{{ __('Notification pages') }}">
                <a href="{{ route('notifications.index') }}" aria-current="page">{{ __('History') }}</a>
                <a href="{{ route('settings.notifications') }}">{{ __('Notification settings') }}</a>
            </nav>
        </header>
        <div class="history-toolbar">
            <div class="btn-group" role="group" aria-label="{{ __('Filter notifications') }}">
                <button id="historyFilter-all" type="button" class="btn btn-outline-primary active" aria-pressed="true">{{ __('All') }}</button>
                <button id="historyFilter-unread" type="button" class="btn btn-outline-primary" aria-pressed="false">{{ __('Unread') }}</button>
            </div>
            <button id="historyMarkAll" type="button" class="btn btn-outline-primary">{{ __('Mark all as read') }}</button>
        </div>
        <div id="historyList" aria-busy="true">
            <p class="text-muted p-3 mb-0" role="status">{{ __('Loading notifications…') }}</p>
        </div>
        <nav class="history-pagination" aria-label="{{ __('Notification pages') }}">
            <button id="history-previous" type="button" class="btn btn-outline-primary" disabled>{{ __('Previous') }}</button>
            <span id="historyPage" class="small text-muted" role="status"></span>
            <button id="history-next" type="button" class="btn btn-outline-primary" disabled>{{ __('Next') }}</button>
        </nav>
    </div>
    <noscript><p class="mt-3">{{ __('Enable JavaScript to load your notifications.') }}</p></noscript>
</section>
</div></div></div>
@endsection
