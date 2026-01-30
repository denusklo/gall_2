<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VolunteerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $uid = session()->get('verified_user_id');

        if (!$uid) {
            return redirect()->route('login')
                ->with('error', 'Please login to access this page.');
        }

        try {
            $auth = app('firebase.auth');
            $user = $auth->getUser($uid);
            $customClaims = $user->customClaims ?? [];

            // Check if user is admin or volunteer
            $isVolunteer = isset($customClaims['volunteer']) && $customClaims['volunteer'] === true;
            $isAdmin = isset($customClaims['admin']) && $customClaims['admin'] === true;

            if (!$isVolunteer && !$isAdmin) {
                return redirect()->route('requests.my')
                    ->with('error', 'You do not have volunteer privileges to complete requests.');
            }

        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'Authentication error. Please login again.');
        }

        return $next($request);
    }
}