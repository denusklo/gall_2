<?php

namespace App\Http\Middleware;

use App\Services\UserSyncService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Auth;
use Firebase\Auth\Token\Exception\InvalidToken;

class UnifiedAuthMiddleware
{
    protected $firebaseAuth;
    protected $syncService;

    public function __construct(UserSyncService $syncService)
    {
        $this->firebaseAuth = app('firebase.auth');
        $this->syncService = $syncService;
    }

    /**
     * Handle an incoming request.
     * Accepts either Firebase ID token or Sanctum token
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Strategy 1: Check for active Laravel/Sanctum session
        if (Auth::check()) {
            $user = Auth::user();

            // Ensure Firebase session data is present if user has Firebase
            if ($user->firebase_uid && !session()->get('verified_user_id')) {
                if ($user->firebase_id_token) {
                    session()->put('verified_user_id', $user->firebase_uid);
                    session()->put('idTokenString', $user->firebase_id_token);
                    session()->put('displayName', $user->name);
                }
            }

            return $next($request);
        }

        // Strategy 2: Check for Firebase session
        $firebaseUid = session()->get('verified_user_id');
        $idTokenString = session()->get('idTokenString');

        if ($firebaseUid && $idTokenString) {
            try {
                // Verify Firebase token
                $verifiedIdToken = $this->firebaseAuth->verifyIdToken($idTokenString);

                // Sync and login user to Laravel
                $firebaseUserData = $this->syncService->getFirebaseUser($firebaseUid);
                $user = $this->syncService->syncFirebaseToMysql($firebaseUid, $idTokenString, $firebaseUserData);

                // Log in via Laravel Auth
                Auth::login($user);

                return $next($request);
            } catch (InvalidToken $e) {
                // Token is invalid, clear session
                session()->forget('verified_user_id');
                session()->forget('idTokenString');
                session()->forget('displayName');

                return redirect()->route('unified.login.form')
                    ->with('error', 'Session expired. Please log in again.');
            } catch (\Exception $e) {
                // Other errors
                return redirect()->route('unified.login.form')
                    ->with('error', 'Authentication failed: ' . $e->getMessage());
            }
        }

        // Strategy 3: Check for Bearer token (API requests)
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            // Try Sanctum token first
            try {
                $user = Auth::guard('sanctum')->user();

                if ($user) {
                    // Set Auth for subsequent checks
                    Auth::setUser($user);

                    // Ensure Firebase session data is present if user has Firebase
                    if ($user->firebase_uid && !session()->get('verified_user_id')) {
                        if ($user->firebase_id_token) {
                            session()->put('verified_user_id', $user->firebase_uid);
                            session()->put('idTokenString', $user->firebase_id_token);
                            session()->put('displayName', $user->name);
                        }
                    }

                    return $next($request);
                }
            } catch (\Exception $e) {
                // Sanctum auth failed, try Firebase
            }

            // Try Firebase ID token as bearer
            try {
                $verifiedToken = $this->syncService->verifyFirebaseToken($bearerToken);

                if ($verifiedToken) {
                    $firebaseUid = $verifiedToken['uid'];

                    // Sync and login user
                    $firebaseUserData = $this->syncService->getFirebaseUser($firebaseUid);
                    $user = $this->syncService->syncFirebaseToMysql($firebaseUid, $bearerToken, $firebaseUserData);

                    // Set Auth for subsequent checks
                    Auth::setUser($user);

                    return $next($request);
                }
            } catch (\Exception $e) {
                // Firebase auth failed
            }
        }

        // All authentication methods failed
        return redirect()->route('unified.login.form')
            ->with('error', 'Please log in to continue.');
    }

    /**
     * Handle authentication failure
     * Can also return JSON for API requests
     */
    protected function authenticate($request)
    {
        if ($request->expectsJson()) {
            throw new AuthenticationException('Unauthenticated.', [], route('unified.login.form'));
        }

        return redirect()->route('unified.login.form');
    }
}
