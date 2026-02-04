<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserSettingsService
{
    /**
     * Test Supabase connection with provided credentials.
     *
     * @param string $url
     * @param string $key
     * @param string|null $serviceKey
     * @return array{success: bool, message: string, details: array}
     */
    public function testSupabaseConnection(string $url, string $key, ?string $serviceKey = null): array
    {
        try {
            // First validate the format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid URL format',
                    'details' => [],
                ];
            }

            if (!str_contains($url, 'supabase.co')) {
                return [
                    'success' => false,
                    'message' => 'URL must be a valid Supabase URL (containing supabase.co)',
                    'details' => [],
                ];
            }

            // Test by listing buckets (requires service role key for admin operations)
            $testKey = $serviceKey ?: $key;

            $response = Http::withHeaders([
                'apikey' => $testKey,
                'Authorization' => 'Bearer ' . $testKey,
            ])->get(rtrim($url, '/') . '/storage/v1/bucket');

            $details = [
                'status_code' => $response->status(),
            ];

            if ($response->successful()) {
                $buckets = $response->json();

                return [
                    'success' => true,
                    'message' => 'Connection successful. Found ' . count($buckets) . ' bucket(s).',
                    'details' => array_merge($details, [
                        'buckets' => array_column($buckets, 'name'),
                        'buckets_count' => count($buckets),
                    ]),
                ];
            }

            // If we get 401/403, credentials might be invalid
            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed. Please check your API keys.',
                    'details' => $details,
                ];
            }

            // Other errors
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? 'Unknown error';

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage,
                'details' => $details,
            ];
        } catch (\Exception $e) {
            Log::error('Supabase connection test failed', [
                'exception' => $e->getMessage(),
                'url' => $url,
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Test Vercel Blob connection with provided token.
     *
     * @param string $token
     * @return array{success: bool, message: string, store_id: string|null, details: array}
     */
    public function testVercelBlobConnection(string $token): array
    {
        try {
            // First validate token format
            $pattern = '/^vercel_blob_rw_([A-Za-z0-9]+)_/';
            if (!preg_match($pattern, $token, $matches)) {
                return [
                    'success' => false,
                    'message' => 'Invalid token format. Expected format: vercel_blob_rw_{storeId}_{secret}',
                    'store_id' => null,
                    'details' => [],
                ];
            }

            $storeId = $matches[1];

            // Test by listing blobs from the store
            $apiUrl = 'https://vercel.com/api/blob';

            $response = Http::withHeaders([
                'authorization' => 'Bearer ' . $token,
                'x-api-version' => '11',
            ])->get($apiUrl . '/list', [
                'limit' => 1,
            ]);

            $details = [
                'status_code' => $response->status(),
                'store_id' => $storeId,
            ];

            if ($response->successful() || $response->status() === 404) {
                // 404 is acceptable - it just means the store is empty
                return [
                    'success' => true,
                    'message' => 'Connection successful. Store ID: ' . $storeId,
                    'store_id' => $storeId,
                    'details' => array_merge($details, [
                        'store_empty' => $response->status() === 404,
                    ]),
                ];
            }

            // Authentication failure
            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed. Please check your read-write token.',
                    'store_id' => $storeId,
                    'details' => $details,
                ];
            }

            // Other errors
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? 'Unknown error';

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage,
                'store_id' => $storeId,
                'details' => $details,
            ];
        } catch (\Exception $e) {
            Log::error('Vercel Blob connection test failed', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'store_id' => null,
                'details' => [],
            ];
        }
    }

    /**
     * Update or create user settings.
     *
     * @param User $user
     * @param array $data
     * @return UserSettings
     */
    public function updateSettings(User $user, array $data): UserSettings
    {
        $settings = $user->settings;

        if (!$settings) {
            $settings = new UserSettings();
            $settings->user_id = $user->id;
        }

        // Only update provided fields
        $fillableFields = [
            'storage_provider',
            'supabase_url',
            'supabase_key',
            'supabase_service_key',
            'supabase_bucket',
            'vercel_blob_token',
            'vercel_blob_store_url',
        ];

        foreach ($fillableFields as $field) {
            if (array_key_exists($field, $data)) {
                $settings->$field = $data[$field];
            }
        }

        // Reset verification status if credentials changed
        $credentialFields = [
            'supabase_url', 'supabase_key', 'supabase_service_key',
            'vercel_blob_token',
        ];

        $credentialsChanged = false;
        foreach ($credentialFields as $field) {
            if (array_key_exists($field, $data)) {
                $oldValue = $settings->getOriginal($field);
                if ($oldValue !== $data[$field]) {
                    $credentialsChanged = true;
                    break;
                }
            }
        }

        if ($credentialsChanged) {
            $settings->credentials_verified = false;
            $settings->last_verified_at = null;
        }

        $settings->save();

        return $settings->refresh();
    }

    /**
     * Delete credentials for a specific provider.
     *
     * @param User $user
     * @param string $provider 'supabase' or 'vercel'
     * @return void
     */
    public function deleteProviderCredentials(User $user, string $provider): void
    {
        $settings = $user->settings;

        if (!$settings) {
            return;
        }

        if ($provider === 'supabase') {
            $settings->supabase_url = null;
            $settings->supabase_key = null;
            $settings->supabase_service_key = null;
            $settings->supabase_bucket = null;
        } elseif ($provider === 'vercel') {
            $settings->vercel_blob_token = null;
            $settings->vercel_blob_store_url = null;
        }

        // Reset verification if credentials were deleted
        $settings->credentials_verified = false;
        $settings->last_verified_at = null;

        $settings->save();
    }

    /**
     * Get user settings or create default.
     *
     * @param User $user
     * @return UserSettings
     */
    public function getOrCreateSettings(User $user): UserSettings
    {
        $settings = $user->settings;

        if (!$settings) {
            $settings = new UserSettings();
            $settings->user_id = $user->id;
            $settings->storage_provider = 'supabase';
            $settings->save();
        }

        return $settings;
    }
}
