<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSyncService
{
    protected $firebaseAuth;

    public function __construct()
    {
        $this->firebaseAuth = app('firebase.auth');
    }

    /**
     * Sync Firebase user to MySQL after Firebase login
     *
     * @param string $firebaseUid Firebase user ID
     * @param string $idToken Firebase ID token
     * @param array $firebaseUser Firebase user data
     * @return User
     */
    public function syncFirebaseToMysql(string $firebaseUid, string $idToken, array $firebaseUser = []): User
    {
        // Check if user already exists by firebase_uid
        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (!$user) {
            // Try to find by email if available
            $email = $firebaseUser['email'] ?? null;
            if ($email) {
                $user = User::where('email', $email)->first();
            }
        }

        if ($user) {
            // Update existing user with Firebase data
            $user->firebase_uid = $firebaseUid;
            $user->firebase_id_token = $idToken;
            $user->auth_provider = $user->auth_provider === 'sanctum' ? 'both' : 'firebase';
            $user->save();

            Log::info('Updated existing MySQL user with Firebase data', [
                'user_id' => $user->id,
                'firebase_uid' => $firebaseUid
            ]);

            return $user;
        }

        // Create new MySQL user from Firebase data
        $user = User::create([
            'name' => $firebaseUser['displayName'] ?? $firebaseUser['name'] ?? 'Firebase User',
            'email' => $firebaseUser['email'],
            'password' => Hash::make(uniqid()), // Random password for Firebase users
            'firebase_uid' => $firebaseUid,
            'firebase_id_token' => $idToken,
            'auth_provider' => 'firebase',
            'email_verified_at' => ($firebaseUser['emailVerified'] ?? false) ? now() : null,
        ]);

        Log::info('Created new MySQL user from Firebase', [
            'user_id' => $user->id,
            'firebase_uid' => $firebaseUid
        ]);

        return $user;
    }

    /**
     * Sync MySQL user to Firebase after Laravel login
     *
     * @param User $user MySQL user
     * @param string $password Plain password for Firebase
     * @return array Firebase auth data
     */
    public function syncMysqlToFirebase(User $user, string $password): array
    {
        // If user already has Firebase UID, just sign in
        if ($user->firebase_uid) {
            try {
                $signInResult = $this->firebaseAuth->signInWithEmailAndPassword($user->email, $password);
                $idToken = $signInResult->idToken();
                $refreshToken = $signInResult->refreshToken();

                // Update user with new tokens
                $user->firebase_id_token = $idToken;
                $user->firebase_refresh_token = $refreshToken;
                $user->auth_provider = $user->auth_provider === 'firebase' ? 'both' : 'sanctum';
                $user->save();

                return [
                    'firebase_uid' => $user->firebase_uid,
                    'id_token' => $idToken,
                    'refresh_token' => $refreshToken,
                ];
            } catch (\Exception $e) {
                Log::warning('Firebase sign in failed for existing Firebase user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        // Create new Firebase user from MySQL data
        try {
            $userProperties = [
                'email' => $user->email,
                'emailVerified' => !is_null($user->email_verified_at),
                'password' => $password,
                'displayName' => $user->name,
                'disabled' => false,
            ];

            $firebaseUser = $this->firebaseAuth->createUser($userProperties);

            // Sign in to get tokens
            $signInResult = $this->firebaseAuth->signInWithEmailAndPassword($user->email, $password);
            $idToken = $signInResult->idToken();
            $refreshToken = $signInResult->refreshToken();

            // Update MySQL user with Firebase data
            $user->firebase_uid = $firebaseUser->uid;
            $user->firebase_id_token = $idToken;
            $user->firebase_refresh_token = $refreshToken;
            $user->auth_provider = $user->auth_provider === 'firebase' ? 'both' : 'sanctum';
            $user->save();

            Log::info('Created Firebase user from MySQL', [
                'user_id' => $user->id,
                'firebase_uid' => $firebaseUser->uid
            ]);

            return [
                'firebase_uid' => $firebaseUser->uid,
                'id_token' => $idToken,
                'refresh_token' => $refreshToken,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Firebase user from MySQL', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get Firebase user data by UID
     *
     * @param string $uid Firebase user ID
     * @return array
     */
    public function getFirebaseUser(string $uid): array
    {
        try {
            $firebaseUser = $this->firebaseAuth->getUser($uid);

            return [
                'uid' => $firebaseUser->uid,
                'email' => $firebaseUser->email,
                'displayName' => $firebaseUser->displayName,
                'emailVerified' => $firebaseUser->emailVerified,
                'phoneNumber' => $firebaseUser->phoneNumber,
                'photoUrl' => $firebaseUser->photoUrl,
                'disabled' => $firebaseUser->disabled,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Firebase user', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Verify Firebase ID token
     *
     * @param string $idToken Firebase ID token
     * @return array|false
     */
    public function verifyFirebaseToken(string $idToken)
    {
        try {
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            return [
                'uid' => $uid,
                'token' => $verifiedIdToken,
            ];
        } catch (\Exception $e) {
            Log::warning('Firebase token verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Refresh Firebase ID token using refresh token
     *
     * @param string $refreshToken Firebase refresh token
     * @return string|null New ID token
     */
    public function refreshFirebaseToken(string $refreshToken): ?string
    {
        try {
            // Note: The Firebase Admin SDK doesn't have a direct refresh token method
            // The client should handle token refresh and send the new token
            // This method is a placeholder for future implementation
            Log::info('Firebase token refresh requested', [
                'has_refresh_token' => !empty($refreshToken)
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to refresh Firebase token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Link accounts when same email exists in both systems
     *
     * @param string $email Email address
     * @return bool
     */
    public function linkAccountsByEmail(string $email): bool
    {
        $mysqlUser = User::where('email', $email)->first();

        if (!$mysqlUser || $mysqlUser->firebase_uid) {
            return false; // No MySQL user or already linked
        }

        // This would need to be called with Firebase credentials to complete linking
        // Placeholder for future implementation
        Log::info('Account linking requested', [
            'email' => $email,
            'mysql_user_id' => $mysqlUser->id
        ]);

        return true;
    }
}
