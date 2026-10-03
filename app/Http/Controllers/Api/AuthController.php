<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('token-name')->plainTextToken;
            return response()->json(['access_token' => $token], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function user()
    {
        return response()->json(Auth::user());
    }

    public function getToken(Request $request)
    {
        $user = $request->user();
        $currentFirebaseUid = session('verified_user_id');

        // Get the cached token from session
        $cachedToken = session('api_token');
        $cachedTokenUid = session('api_token_firebase_uid');

        // Check that the cached token still exists in the database, belongs to this user and
        // has not expired. findToken() understands the "id|plain" format (it hashes only the
        // plain part); hashing the whole string never matches the stored hash.
        $cachedRecord = null;
        $ownedRecord = null;
        if ($cachedToken) {
            $record = PersonalAccessToken::findToken($cachedToken);
            $expiration = config('sanctum.expiration');
            if ($record
                && (int) $record->tokenable_id === (int) $user->getKey()
                && $record->tokenable_type === $user->getMorphClass()) {
                $ownedRecord = $record;
                if ((!$expiration || $record->created_at->gt(now()->subMinutes($expiration)))
                    && (!$record->expires_at || !$record->expires_at->isPast())) {
                    $cachedRecord = $record;
                }
            }
        }
        $tokenExists = $cachedRecord !== null;

        // Regenerate token if:
        // 1. No cached token exists
        // 2. Cached token was deleted from database (e.g., by another device's login)
        // 3. Firebase user has changed (current Firebase UID != cached token's Firebase UID)
        // 4. User doesn't match the expected Firebase UID
        $shouldRegenerate = !$cachedToken ||
                            !$tokenExists ||
                            $cachedTokenUid !== $currentFirebaseUid ||
                            ($currentFirebaseUid && $user->firebase_uid !== $currentFirebaseUid);

        if ($shouldRegenerate) {
            // Delete the CURRENT session's old token (if exists), not all tokens
            // This allows multiple devices to have their own tokens
            if ($ownedRecord) {
                $ownedRecord->delete();
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            // Cache the new token and associated Firebase UID in session
            session(['api_token' => $token, 'api_token_firebase_uid' => $currentFirebaseUid]);
        } else {
            // Return the cached token (it's still valid)
            $token = $cachedToken;
        }

        // Push authorization outlives bearer expiry. The identity is minted only here,
        // inside a persisted web login, never from the middleware's bearer-only setUser().
        $pushAvailable = false;
        $uid = $user->firebase_uid;
        if (is_string($uid) && $uid !== '' && $this->hasMatchingWebLogin($request, $user)) {
            $binding = $request->session()->get('push_binding');
            if (!is_array($binding) || ($binding['uid'] ?? null) !== $uid) {
                $binding = ['uid' => $uid, 'id' => bin2hex(random_bytes(32))];
                $request->session()->put('push_binding', $binding);
            }
            try {
                $record = PersonalAccessToken::findToken($token);
                if (!$record || !$user->tokens()->whereKey($record->getKey())->exists()) {
                    throw new \RuntimeException('Session bearer unavailable');
                }
                $push = app(\App\Services\FcmTokenService::class);
                $push->bindBearer($uid, $binding['id'], (int) $record->getKey());
                $pushAvailable = true;
                if ($ownedRecord && $ownedRecord->getKey() !== $record->getKey()) {
                    $push->forgetBearer($uid, (int) $ownedRecord->getKey());
                }
            } catch (\Throwable $e) {
                // Ordinary API use remains available during a push outage.
                \Illuminate\Support\Facades\Log::warning('Push bearer binding unavailable', [
                    'exception_class' => get_class($e),
                ]);
                $pushAvailable = false;
            }
        }

        return response()->json(['token' => $token, 'push_available' => $pushAvailable]);
    }

    private function hasMatchingWebLogin(Request $request, $user): bool
    {
        try {
            $guard = Auth::guard('web');
            if (!$guard instanceof \Illuminate\Auth\SessionGuard || !$request->hasSession()
                || $guard->getSession() !== $request->session()) {
                return false;
            }
            // check()/user() can reflect Auth::setUser() without any web login.
            $id = $request->session()->get($guard->getName());
            if ((!is_string($id) && !is_int($id))
                || (string) $id !== (string) $user->getAuthIdentifier()) {
                return false;
            }
            $persisted = $guard->getProvider()->retrieveById($id);
            return $persisted !== null
                && (string) $persisted->getAuthIdentifier() === (string) $user->getAuthIdentifier()
                && $persisted->getMorphClass() === $user->getMorphClass()
                && $persisted->firebase_uid === $user->firebase_uid;
        } catch (\Throwable $e) {
            // A failed push eligibility check must not break ordinary API issuance.
            \Illuminate\Support\Facades\Log::warning('Push web login check unavailable', [
                'exception_class' => get_class($e),
            ]);
            return false;
        }
    }

}
