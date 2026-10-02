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
        if ($cachedToken) {
            $record = PersonalAccessToken::findToken($cachedToken);
            $expiration = config('sanctum.expiration');
            if ($record
                && (int) $record->tokenable_id === (int) $user->getKey()
                && $record->tokenable_type === $user->getMorphClass()
                && (!$expiration || $record->created_at->gt(now()->subMinutes($expiration)))) {
                $cachedRecord = $record;
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
            if ($cachedRecord) {
                $cachedRecord->delete();
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            // Cache the new token and associated Firebase UID in session
            session(['api_token' => $token, 'api_token_firebase_uid' => $currentFirebaseUid]);
        } else {
            // Return the cached token (it's still valid)
            $token = $cachedToken;
        }

        return response()->json(['token' => $token]);
    }

}
