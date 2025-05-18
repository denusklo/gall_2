<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FirebaseAuthController;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        // Check Laravel auth
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect()->route('home');
            }
        }
        
        // Check Firebase auth
        $firebaseAuth = new FirebaseAuthController();
        if ($firebaseAuth->authentication()) {
            return redirect()->route('user.home');
        }

        return $next($request);
    }
}