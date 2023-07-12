<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirebaseController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\KJKController;
use Kreait\Laravel\Firebase\Facades\FirebaseAuth;
use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;


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

Route::get('testing/create', [KJKController::class, 'createRequest'])->name('createRequest');

Route::get('userHome', function () {


    // $authenticator =  new FirebaseAuthController;
    // $authenticator->authentication();

    $controller = new FirebaseController();
    $data = $controller->indexByUid();

    return view('users.userHome', compact('data'));
})->name('userHome');

Route::post('testing/delete', [FirebaseController::class, 'deleteRequest'])->name('deleteRequest');
Route::post('testing/store', [FirebaseController::class, 'storeRequest'])->name('storeRequest');
Route::post('testing/update', [FirebaseController::class, 'updateRequest'])->name('updateRequest');

Route::post('testing/deleteUser', [App\Http\Controllers\FirebaseAuthController::class, 'deleteUser'])->name('deleteUser');
Route::get('testing/editUser', [App\Http\Controllers\FirebaseAuthController::class, 'editUser'])->name('editUser');
Route::post('testing/updateUser', [App\Http\Controllers\FirebaseAuthController::class, 'updateUser'])->name('updateUser');

// Route::post('register/createUser', [App\Http\Controllers\FirebaseAuthController::class, 'createUser'])->name('createUser');

Route::get('test', [App\Http\Controllers\FirebaseAuthController::class, 'test']);

Route::post('register', [UserController::class, 'create'])->name('user.create');
Route::post('login', [UserController::class, 'login'])->name('user.login');
// Route::post('register/', function (Request $request) {
//     $data = $request->validate([
//         'name' => 'required|string|max:255',
//         'email' => 'required|string|email|max:255|unique:users',
//         'password' => 'required|string|min:8|confirmed',
//     ]);

//     return app(RegisterController::class)->create($data);
// })->name('createUser');


// Route::post('login/', [App\Http\Controllers\FirebaseAuthController::class, 'login'])->name('firebaseLogin');
// Route::get('logout/', [App\Http\Controllers\FirebaseAuthController::class, 'logout'])->name('firebaseLogout');


Route::get('testing', function () {
    return view('test');
})->name('test');

Route::get('firebase', [FirebaseController::class, 'index']);
