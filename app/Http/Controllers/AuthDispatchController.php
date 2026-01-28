<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Firebase\FirebaseAuthController;

class AuthDispatchController extends Controller
{
    public function dispatchHome(Request $request)
    {
        // Check Firebase auth first
        $firebaseAuth = new FirebaseAuthController();
        if ($firebaseAuth->authentication()) {
            // Redirect to Firebase user home
            return app()->call([app()->make(RequestController::class), 'index']);
        }
        
        // Check Laravel auth
        if (Auth::check()) {
            // Redirect to Laravel user home
            return app()->call([app()->make(HomeController::class), 'index']);
        }
        
        // No authentication found, redirect to login
        return redirect()->route('login');
    }
}