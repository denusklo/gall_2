<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FirebaseAuthMiddleware
{

  public function handle(Request $request, Closure $next) {

    $auth = app('firebase.auth');

    if (session()->get('verified_user_id')) {

        $uid = session()->get('verified_user_id');
        $idTokenString = session()->get('idTokenString');

        try {
            $verifiedIdToken = $auth->verifyIdToken($idTokenString);
        } catch (InvalidToken $e) {
            return redirect()->route('firebase.login.form')->with('error', 'User not logged in.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('firebase.login.form')->with('error', 'User not logged in.');
        }
    }

    return $next($request);

    // if (session()->get('verified_user_id') && session()->get('idTokenString'))  {
    //   return $next($request);
    // } else{
    //   return redirect()->route('firebase.login.form')->with('error', 'User not logged in.');
    // }

  }


}