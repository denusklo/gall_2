<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSettings;
use App\Services\Storage\TrustedStorageOriginPolicy;
use Illuminate\Support\Facades\Log;

class UserSettingsService
{
    /**
     * Test Supabase credentials. The URL must be a hosted project (https://<ref>.supabase.co);
     * the request is DNS-pinned to a public address with redirects disabled. Callers validate the
     * endpoint first (422); this re-checks so the service is safe on its own.
     *
     * @return array{success: bool, message: string, details: array}
     */
    public function testSupabaseConnection(string $url, string $key, ?string $serviceKey = null): array
    {
        $policy = app(TrustedStorageOriginPolicy::class);
        try {
            $origin = $policy->supabaseOrigin($url);
            $client = $policy->client($origin);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => TrustedStorageOriginPolicy::rejectionMessage($e->getMessage()), 'details' => []];
        }
        // Listing buckets needs the service role key for admin operations.
        $testKey = $serviceKey ?: $key;
        try {
            $response = $client->withHeaders(['apikey' => $testKey, 'Authorization' => 'Bearer ' . $testKey])
                ->get($origin . '/storage/v1/bucket');
        } catch (\Exception $e) {
            Log::error('Supabase connection test failed', ['exception_class' => get_class($e)]);
            return ['success' => false, 'message' => 'Connection failed. The project could not be reached.', 'details' => []];
        }
        $details = ['status_code' => $response->status()];
        $buckets = $response->json();
        if ($response->successful() && is_array($buckets)) {
            $names = array_values(array_filter(array_column($buckets, 'name'), 'is_string'));
            return [
                'success' => true,
                'message' => 'Connection successful. Found ' . count($names) . ' bucket(s).',
                'details' => array_merge($details, ['buckets' => $names, 'buckets_count' => count($names)]),
            ];
        }
        if (in_array($response->status(), [401, 403], true)) {
            return ['success' => false, 'message' => 'Authentication failed. Please check your API keys.', 'details' => $details];
        }
        // Never reflect provider error bodies.
        return ['success' => false, 'message' => 'Connection failed (HTTP ' . $response->status() . ').', 'details' => $details];
    }

    /**
     * Test a Vercel Blob read-write token against the fixed Blob API (installed SDK list() contract).
     *
     * @return array{success: bool, message: string, store_id: string|null, details: array}
     */
    public function testVercelBlobConnection(string $token): array
    {
        if (!preg_match('/^vercel_blob_rw_([A-Za-z0-9]+)_/', $token, $matches)) {
            return [
                'success' => false,
                'message' => 'Invalid token format. Expected format: vercel_blob_rw_{storeId}_{secret}',
                'store_id' => null,
                'details' => [],
            ];
        }
        $storeId = $matches[1];
        try {
            $response = app(TrustedStorageOriginPolicy::class)->client('https://vercel.com')
                ->withHeaders(['authorization' => 'Bearer ' . $token, 'x-api-version' => '11'])
                ->get('https://vercel.com/api/blob', ['limit' => 1]);
        } catch (\Exception $e) {
            Log::error('Vercel Blob connection test failed', ['exception_class' => get_class($e)]);
            return ['success' => false, 'message' => 'Connection failed. Vercel Blob could not be reached.', 'store_id' => null, 'details' => []];
        }
        $details = ['status_code' => $response->status(), 'store_id' => $storeId];
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Connection successful. Store ID: ' . $storeId,
                'store_id' => $storeId,
                'details' => $details,
            ];
        }
        if (in_array($response->status(), [401, 403], true)) {
            return ['success' => false, 'message' => 'Authentication failed. Please check your read-write token.', 'store_id' => $storeId, 'details' => $details];
        }
        return ['success' => false, 'message' => 'Connection failed (HTTP ' . $response->status() . ').', 'store_id' => $storeId, 'details' => $details];
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
