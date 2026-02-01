<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Firebase\FirebaseAuthController;

class AuthDispatchController extends Controller
{
    public function dispatchHome(Request $request)
    {
        // Check Laravel auth first - if session expired, go to login
        if (Auth::check()) {
            // Check Firebase auth only if Laravel session is active
            $firebaseAuth = new FirebaseAuthController();
            if ($firebaseAuth->authentication()) {
                // Redirect to Firebase user home
                return app()->call([app()->make(RequestController::class), 'index']);
            }

            // Redirect to requests page (same as Firebase users)
            return app()->call([app()->make(RequestController::class), 'index']);
        }

        // Laravel session expired - redirect to login
        return redirect()->route('login');
    }
}