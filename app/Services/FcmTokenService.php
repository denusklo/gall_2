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
     * @param string $domain Optional domain (origin) where token was registered
     * @return bool
     */
    public function storeToken($uid, $token, $deviceInfo = null, $domain = null)
    {
        try {
            // CRITICAL FIX: Remove this token from ALL other users first
            // This prevents tokens from being registered to multiple users
            $this->removeTokenFromAllOtherUsers($uid, $token);

            // Clean up old tokens without domain for this user
            // Only run this occasionally (every 10th registration) to avoid performance impact
            if (rand(1, 10) === 1) {
                $this->cleanUserOldTokens($uid);
            }

            $reference = $this->database->getReference("users/{$uid}/fcm_tokens/{$this->sanitizeTokenKey($token)}");

            $data = [
                'token' => $token,
                'updated_at' => now()->toISOString()
            ];

            if ($deviceInfo) {
                $data['device_info'] = $deviceInfo;
            }

            if ($domain) {
                $data['domain'] = $domain;
            }

            $reference->set($data);

            Log::info('FCM token stored', [
                'uid' => $uid,
                'domain' => $domain,
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
     * Remove an FCM token from all users EXCEPT the specified user
     * This prevents duplicate token registrations across users
     *
     * @param string $currentUid Current user's Firebase UID (to keep)
     * @param string $token FCM token to remove from others
     * @return void
     */
    protected function removeTokenFromAllOtherUsers($currentUid, $token)
    {
        try {
            // Get all users from the database
            $usersRef = $this->database->getReference('users');
            $snapshot = $usersRef->getSnapshot();

            if (!$snapshot->exists()) {
                return;
            }

            $allUsers = $snapshot->getValue();
            $tokenKey = $this->sanitizeTokenKey($token);

            foreach ($allUsers as $uid => $userData) {
                // Skip the current user
                if ($uid === $currentUid) {
                    continue;
                }

                // Check if this user has FCM tokens
                if (isset($userData['fcm_tokens']) && isset($userData['fcm_tokens'][$tokenKey])) {
                    // Remove the token from this user
                    $this->database->getReference("users/{$uid}/fcm_tokens/{$tokenKey}")->remove();

                    Log::info('FCM token removed from other user', [
                        'from_uid' => $uid,
                        'token' => substr($token, 0, 20) . '...'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove token from other users', [
                'current_uid' => $currentUid,
                'error' => $e->getMessage()
            ]);
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
     * Get FCM tokens for a user filtered by domain
     *
     * @param string $uid User's Firebase UID
     * @param string $domain Domain to filter by (e.g., https://example.com)
     * @return array Array of tokens matching the domain
     */
    public function getUserTokensForDomain($uid, $domain)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens");
            $snapshot = $reference->getSnapshot();

            if (!$snapshot->exists()) {
                return [];
            }

            $tokens = [];
            $allData = $snapshot->getValue();

            foreach ($allData as $key => $data) {
                if (isset($data['token'])) {
                    // Check if domain matches
                    $tokenDomain = $data['domain'] ?? null;
                    if ($tokenDomain === $domain) {
                        $tokens[] = $data['token'];
                    }
                }
            }

            Log::info('FCM tokens filtered by domain', [
                'uid' => $uid,
                'domain' => $domain,
                'token_count' => count($tokens),
                'total_tokens' => count($allData)
            ]);

            return $tokens;
        } catch (\Exception $e) {
            Log::error('Failed to get user FCM tokens for domain', [
                'uid' => $uid,
                'domain' => $domain,
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
     * Clean up old tokens without domain information
     * This removes tokens that were registered before domain tracking was added
     *
     * @param string|null $uid Optional specific user UID to clean up (null = all users)
     * @return array Results with counts of cleaned tokens
     */
    public function cleanUpOldTokens($uid = null)
    {
        try {
            $cleanedCount = 0;
            $usersProcessed = 0;

            if ($uid) {
                // Clean up for specific user
                $result = $this->cleanUserOldTokens($uid);
                $cleanedCount = $result['cleaned'];
                $usersProcessed = 1;
            } else {
                // Clean up for all users
                $usersRef = $this->database->getReference('users');
                $snapshot = $usersRef->getSnapshot();

                if (!$snapshot->exists()) {
                    return ['cleaned' => 0, 'users' => 0];
                }

                $allUsers = $snapshot->getValue();

                foreach ($allUsers as $userId => $userData) {
                    $result = $this->cleanUserOldTokens($userId);
                    if ($result['cleaned'] > 0) {
                        $cleanedCount += $result['cleaned'];
                        $usersProcessed++;
                    }
                }
            }

            Log::info('FCM old token cleanup completed', [
                'users_processed' => $usersProcessed,
                'tokens_cleaned' => $cleanedCount
            ]);

            return [
                'cleaned' => $cleanedCount,
                'users' => $usersProcessed
            ];
        } catch (\Exception $e) {
            Log::error('Failed to clean up old FCM tokens', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return ['cleaned' => 0, 'users' => 0];
        }
    }

    /**
     * Clean up old tokens for a specific user
     * Removes tokens without domain information
     *
     * @param string $uid User's Firebase UID
     * @return array Results with count of cleaned tokens
     */
    protected function cleanUserOldTokens($uid)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens");
            $snapshot = $reference->getSnapshot();

            if (!$snapshot->exists()) {
                return ['cleaned' => 0];
            }

            $tokens = $snapshot->getValue();
            $cleanedCount = 0;

            foreach ($tokens as $tokenKey => $tokenData) {
                // Remove tokens without domain field (old tokens)
                if (!isset($tokenData['domain'])) {
                    $this->database->getReference("users/{$uid}/fcm_tokens/{$tokenKey}")->remove();
                    $cleanedCount++;

                    Log::info('Removed old FCM token (no domain)', [
                        'uid' => $uid,
                        'token' => substr($tokenData['token'] ?? 'unknown', 0, 20) . '...'
                    ]);
                }
            }

            return ['cleaned' => $cleanedCount];
        } catch (\Exception $e) {
            Log::error('Failed to clean up old FCM tokens for user', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return ['cleaned' => 0];
        }
    }

    /**
     * Clean up duplicate tokens for a user
     * Keeps only the most recent token for each domain
     *
     * @param string $uid User's Firebase UID
     * @return array Results with count of cleaned tokens
     */
    public function cleanUpDuplicateTokens($uid)
    {
        try {
            $reference = $this->database->getReference("users/{$uid}/fcm_tokens");
            $snapshot = $reference->getSnapshot();

            if (!$snapshot->exists()) {
                return ['cleaned' => 0];
            }

            $tokens = $snapshot->getValue();
            $cleanedCount = 0;

            // Group tokens by domain
            $tokensByDomain = [];
            foreach ($tokens as $tokenKey => $tokenData) {
                $domain = $tokenData['domain'] ?? 'unknown';
                $updatedAt = $tokenData['updated_at'] ?? '';

                if (!isset($tokensByDomain[$domain])) {
                    $tokensByDomain[$domain] = [];
                }

                $tokensByDomain[$domain][] = [
                    'key' => $tokenKey,
                    'data' => $tokenData,
                    'updated_at' => $updatedAt
                ];
            }

            // For each domain, keep only the most recent token
            foreach ($tokensByDomain as $domain => $domainTokens) {
                if (count($domainTokens) <= 1) {
                    continue; // No duplicates for this domain
                }

                // Sort by updated_at descending (most recent first)
                usort($domainTokens, function($a, $b) {
                    return strtotime($b['updated_at']) - strtotime($a['updated_at']);
                });

                // Keep the first (most recent) token, remove the rest
                $keepToken = array_shift($domainTokens);

                foreach ($domainTokens as $oldToken) {
                    $this->database->getReference("users/{$uid}/fcm_tokens/{$oldToken['key']}")->remove();
                    $cleanedCount++;

                    Log::info('Removed duplicate FCM token', [
                        'uid' => $uid,
                        'domain' => $domain,
                        'token' => substr($oldToken['data']['token'] ?? 'unknown', 0, 20) . '...'
                    ]);
                }
            }

            return ['cleaned' => $cleanedCount];
        } catch (\Exception $e) {
            Log::error('Failed to clean up duplicate FCM tokens', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return ['cleaned' => 0];
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
