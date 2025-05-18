<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Controllers\FirebaseAdminController;

class FirebaseAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $adminController = new FirebaseAdminController();
        
        if (!$adminController->isCurrentUserAdmin()) {
            return redirect()->route('user.home')->with('error', 'Access denied: Admin privileges required.');
        }
        
        return $next($request);
    }
}