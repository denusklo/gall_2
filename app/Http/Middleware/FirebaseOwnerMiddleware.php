<?php

namespace App\Http\Middleware;

use App\Services\FirebaseRoleService;
use Closure;
use Illuminate\Http\Request;

class FirebaseOwnerMiddleware
{
    public function __construct(private FirebaseRoleService $roles)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        // Actor is only ever the web-session-established uid.
        if (!$this->roles->isOwner(session()->get('verified_user_id'))) {
            abort(403, 'Owner privileges required.');
        }

        return $next($request);
    }
}
