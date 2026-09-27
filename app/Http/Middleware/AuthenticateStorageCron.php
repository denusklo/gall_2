<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Machine authentication for the daily storage-maintenance cron. Vercel sends
 * "Authorization: Bearer <CRON_SECRET>". Fails closed when the secret is not configured.
 * No session, cookie or CSRF involvement; the header value is never logged.
 */
class AuthenticateStorageCron
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('storage_maintenance.cron_secret');
        $header = (string) $request->header('Authorization', '');
        // Compare fixed-length digests so the check is constant-time regardless of input length.
        $valid = is_string($secret) && strlen($secret) >= 16
            && hash_equals(hash('sha256', 'Bearer ' . $secret), hash('sha256', $header));
        if (!$valid) {
            return response()->json(['error' => 'Unauthorized'], 401)
                ->header('Cache-Control', 'no-store, max-age=0');
        }
        return $next($request);
    }
}
