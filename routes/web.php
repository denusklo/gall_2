<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Firebase\FirebaseController;
use App\Http\Controllers\Firebase\FirebaseAuthController;
use App\Http\Controllers\Firebase\FirebaseUserController;
use App\Http\Controllers\Firebase\FirebaseAdminController;
use App\Http\Controllers\Auth\UnifiedAuthController;
use App\Http\Controllers\Api\AuthController;
use Kreait\Laravel\Firebase\Facades\FirebaseAuth;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', [App\Http\Controllers\AuthDispatchController::class, 'dispatchHome'])->name('home');

Route::middleware(['auth'])->group(function () {
    // Images route - displays individual images (what used to be called "galleries")
    Route::get('/images', [App\Http\Controllers\GalleryController::class, 'index'])->name('images.index');

    // Storage settings route
    Route::get('/settings/storage', [App\Http\Controllers\SettingsController::class, 'storage'])->name('settings.storage');

    // Galleries route - displays albums/collections
    Route::get('/galleries/albums', [App\Http\Controllers\GalleryController::class, 'albums'])->name('galleries.albums');

    // Gallery detail page - shows images in a specific gallery
    Route::get('/galleries/{id}', [App\Http\Controllers\GalleryController::class, 'show'])->name('galleries.show');

    // Redirect main galleries route to albums
    Route::get('/galleries', function() {
        return redirect()->route('galleries.albums');
    })->name('galleries.index');
});

Route::middleware(\App\Http\Middleware\UnifiedAuthMiddleware::class)->get('/apiv/_1/token', [AuthController::class, 'getToken']);

Route::middleware(['guest'])->prefix('mysql')->as('mysql.')->group(function () {
    Route::get('/register', [UserController::class, 'register'])->name('register');
    Route::get('/login', [UserController::class, 'showLoginForm'])->name('login.form');
    Route::post('/register', [UserController::class, 'create'])->name('register.store');
    Route::post('/login', [UserController::class, 'login'])->name('login');
});

Route::middleware(['guest'])->prefix('firebase')->as('firebase.')->group(function () {
    Route::get('/register', [FirebaseAuthController::class, 'register'])->name('create');
    Route::post('/register', [FirebaseAuthController::class, 'store'])->name('register');
    Route::get('/login', [FirebaseAuthController::class, 'showLoginForm'])->name('login.form');
    Route::post('/login', [FirebaseAuthController::class, 'login'])->name('login');
});

Route::prefix('firebase')->as('firebase.')->group(function () {
    Route::get('/logout', [FirebaseAuthController::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Unified Authentication Routes (Default)
|--------------------------------------------------------------------------
|
| These routes provide a single login system that works with both Firebase
| and Laravel Sanctum. Users can log in with either system and will be
| automatically synced to both sides.
|
| This is the DEFAULT login/register system.
|
*/
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [UnifiedAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.post');
    Route::get('/register', [UnifiedAuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [UnifiedAuthController::class, 'register'])->name('register.post');
});

Route::get('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');
Route::get('/auth/status', [UnifiedAuthController::class, 'status'])->name('auth.status');

// FCM Config endpoint for service worker
Route::get('/fcm-config.js', function() {
    $config = [
        'apiKey' => config('services.firebase.api_key'),
        'authDomain' => config('services.firebase.auth_domain'),
        'databaseURL' => config('services.firebase.database_url'),
        'projectId' => config('services.firebase.project_id'),
        'storageBucket' => config('services.firebase.storage_bucket'),
        'messagingSenderId' => config('services.firebase.messaging_sender_id'),
        'appId' => config('services.firebase.app_id'),
        'measurementId' => config('services.firebase.measurement_id'),
        'vapidKey' => config('services.firebase.vapid_key')
    ];

    $content = "// Firebase Configuration for Service Worker\n";
    $content .= "// This file is dynamically generated by Laravel\n\n";
    $content .= "self.FIREBASE_CONFIG = " . json_encode($config, JSON_PRETTY_PRINT) . ";\n\n";
    $content .= "console.log('[FCM Config] Firebase config loaded in service worker');\n";

    return response($content)
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'public, max-age=3600');
})->name('fcm.config');

// FCM Service Worker Keep-Alive Endpoint
// Called by service worker to stay alive in Firefox (which aggressively kills idle SWs)
Route::get('/fcm-sw-keep-alive', function() {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'message' => 'Service worker keep-alive ping received'
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
      ->header('Content-Type', 'application/json');
})->name('fcm.sw.keepalive');

Route::middleware( ['firebase.auth'] )->group(function() {

    Route::get('users', [FirebaseUserController::class, 'index'])
        ->middleware('firebase.admin')
        ->name('users');

    Route::match(['get', 'post'], 'user/edit', [FirebaseUserController::class, 'edit'])->name('user.edit');
    Route::put('user/update', [FirebaseUserController::class, 'update'])->name('user.update');
    Route::any('user/delete', [FirebaseUserController::class, 'delete'])->name('user.delete');
    Route::get('firebase', [FirebaseController::class, 'index']);

});


Route::middleware(['firebase.auth', 'firebase.admin'])->prefix('admin')->group(function() {
    Route::get('/users', [FirebaseAdminController::class, 'manageUsers'])->name('admin.users');
    Route::post('/users/{uid}/make-admin', [FirebaseAdminController::class, 'makeAdmin'])->name('admin.make-admin');
    Route::post('/users/{uid}/remove-admin', [FirebaseAdminController::class, 'removeAdmin'])->name('admin.remove-admin');
});