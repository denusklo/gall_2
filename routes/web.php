<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\FirebaseUserController;
use App\Http\Controllers\FirebaseAdminController;
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

Auth::routes();

Route::middleware(['auth'])->group(function () {
    
    Route::get('home', [HomeController::class, 'index'])->name('home');

});

Route::middleware(['guest'])->group(function () {
    Route::get('register', [UserController::class, 'register'])->name('user.create');
    Route::get('login', [UserController::class, 'showLoginForm'])->name('user.login.form');
    Route::post('register', [UserController::class, 'create'])->name('user.store');
    Route::post('login', [UserController::class, 'login'])->name('user.login');
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

Route::middleware( ['firebase.auth'] )->group(function() {    
    
    Route::get('home', [App\Http\Controllers\RequestController::class, 'index'])->name('user.home');
    Route::resource('request', RequestController::class);
    Route::resource('requests', RequestsController::class);

    Route::get('users', [FirebaseUserController::class, 'index'])
        ->middleware('firebase.admin')
        ->name('users');

    Route::get('user/edit', [FirebaseUserController::class, 'edit'])->name('user.edit');
    Route::put('user/update', [FirebaseUserController::class, 'update'])->name('user.update');
    Route::any('user/delete', [FirebaseUserController::class, 'delete'])->name('user.delete');
    Route::get('firebase', [FirebaseController::class, 'index']);

});


Route::middleware(['firebase.auth', 'firebase.admin'])->prefix('admin')->group(function() {
    Route::get('/users', [FirebaseAdminController::class, 'manageUsers'])->name('admin.users');
    Route::post('/users/{uid}/make-admin', [FirebaseAdminController::class, 'makeAdmin'])->name('admin.make-admin');
    Route::post('/users/{uid}/remove-admin', [FirebaseAdminController::class, 'removeAdmin'])->name('admin.remove-admin');
});