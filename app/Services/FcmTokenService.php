<?php

namespace App\Services;

use Kreait\Firebase\Database\Reference;
use Illuminate\Support\Facades\Log;

class FcmTokenService
{
    protected $database;

    public function __construct()
    {
        $this->database = app('firebase.database');
    }

    /**
     * Store FCM token for a user
     *
     * @param string $uid User's Firebase UID
     * @param string $token FCM device token
     * @param string $deviceInfo Optional device information
     * @return bool
     */
    public function storeToken($uid, $token, $deviceInfo = null)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens/{$this->sanitizeTokenKey($token)}");

            $data = [
                'token' => $token,
                'updated_at' => now()->toISOString()
            ];

            if ($deviceInfo) {
                $data['device_info'] = $deviceInfo;
            }

            $reference->set($data);

            Log::info('FCM token stored', [
                'uid' => $uid,
                'token' => substr($token, 0, 20) . '...'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store FCM token', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all FCM tokens for a user
     *
     * @param string $uid User's Firebase UID
     * @return array Array of tokens
     */
    public function getUserTokens($uid)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens");
            $snapshot = $reference->getSnapshot();

            if (!$snapshot->exists()) {
                return [];
            }

            $tokens = [];
            $snapshot->getValue();

            foreach ($snapshot->getValue() as $key => $data) {
                if (isset($data['token'])) {
                    $tokens[] = $data['token'];
                }
            }

            return $tokens;
        } catch (\Exception $e) {
            Log::error('Failed to get user FCM tokens', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get the primary/most recent FCM token for a user
     *
     * @param string $uid User's Firebase UID
     * @return string|null Token or null if not found
     */
    public function getPrimaryToken($uid)
    {
        $tokens = $this->getUserTokens($uid);
        return !empty($tokens) ? $tokens[0] : null;
    }

    /**
     * Remove an FCM token (when user logs out or token is invalid)
     *
     * @param string $uid User's Firebase UID
     * @param string $token FCM token to remove
     * @return bool
     */
    public function removeToken($uid, $token)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens/{$this->sanitizeTokenKey($token)}");
            $reference->remove();

            Log::info('FCM token removed', [
                'uid' => $uid,
                'token' => substr($token, 0, 20) . '...'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to remove FCM token', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clean up all tokens for a user
     *
     * @param string $uid User's Firebase UID
     * @return bool
     */
    public function clearAllTokens($uid)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens");
            $reference->remove();

            Log::info('All FCM tokens cleared', [
                'uid' => $uid
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear FCM tokens', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Sanitize token to create a valid Firebase key
     * Firebase keys cannot contain: . # $ ] [ /
     *
     * @param string $token FCM token
     * @return string Sanitized key
     */
    private function sanitizeTokenKey($token)
    {
        // Replace invalid characters with underscore
        // Use base64 encoding for simplicity and uniqueness
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($token));
    }

    /**
     * Check if user has any FCM tokens
     *
     * @param string $uid User's Firebase UID
     * @return bool
     */
    public function hasTokens($uid)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens");
            $snapshot = $reference->getSnapshot();
            return $snapshot->exists() && !empty($snapshot->getValue());
        } catch (\Exception $e) {
            return false;
        }
    }
}
