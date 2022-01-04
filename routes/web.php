<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirebaseController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\KJKController;
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

Route::get('home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('testing/create', [KJKController::class, 'createRequest'])->name('createRequest');

Route::get('userHome', function () {
    return view('users.userHome');
})->name('userHome');

Route::post('testing/delete', [FirebaseController::class, 'deleteRequest'])->name('deleteRequest');
Route::post('testing/store', [FirebaseController::class, 'storeRequest'])->name('storeRequest');
Route::post('testing/update', [FirebaseController::class, 'updateRequest'])->name('updateRequest');

Route::post('testing/deleteUser', [App\Http\Controllers\FirebaseAuthController::class, 'deleteUser'])->name('deleteUser');
Route::get('testing/editUser', [App\Http\Controllers\FirebaseAuthController::class, 'editUser'])->name('editUser');
Route::post('testing/updateUser', [App\Http\Controllers\FirebaseAuthController::class, 'updateUser'])->name('updateUser');

Route::post('register/createUser', [App\Http\Controllers\FirebaseAuthController::class, 'createUser'])->name('createUser');
Route::get('test', [App\Http\Controllers\FirebaseAuthController::class, 'test']);
Route::post('login/', [App\Http\Controllers\FirebaseAuthController::class, 'login'])->name('firebaseLogin');
Route::get('logout/', [App\Http\Controllers\FirebaseAuthController::class, 'logout'])->name('firebaseLogout');



Route::get('testing', function () {
    return view('test');
})->name('test');

Route::get('firebase', [FirebaseController::class, 'index']);
