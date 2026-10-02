<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;

class FirebaseAuthMiddleware
{

  public function handle(Request $request, Closure $next) {

    $auth = app('firebase.auth');

    if (empty(session()->get('verified_user_id'))) {
      // Clear Laravel session too to avoid redirect loop
      \Illuminate\Support\Facades\Auth::logout();
      session()->invalidate();
      session()->regenerateToken();
      return redirect()->route('login')->with('error', 'User not logged in.');
    }

    $idTokenString = session()->get('idTokenString');

    try {
        $verifiedIdToken = $auth->verifyIdToken($idTokenString);
    } catch (FailedToVerifyToken | RevokedIdToken $e) {
        // Firebase token expired - clear Laravel session too to avoid redirect loop
        \Illuminate\Support\Facades\Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
    } catch (\Throwable $e) {
        // Invalid token - clear Laravel session too
        \Illuminate\Support\Facades\Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
    }

    return $next($request);

  }


}