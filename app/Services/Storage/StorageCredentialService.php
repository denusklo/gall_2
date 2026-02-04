<?php

namespace App\Services\Storage;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Support\Facades\Cache;

class StorageCredentialService
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Get Supabase credentials for a user.
     * Falls back to global config if user not configured.
     *
     * @param User $user
     * @return array{url: string, key: string, service_key: string, bucket: string}
     */
    public function getSupabaseCredentials(User $user): array
    {
        $cacheKey = "supabase_creds_{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $settings = $user->settings;

            if ($settings && $settings->hasSupabaseConfig()) {
                return [
                    'url' => $settings->supabase_url,
                    'key' => $settings->supabase_key,
                    'service_key' => $settings->supabase_service_key,
                    'bucket' => $settings->supabase_bucket ?? 'images',
                ];
            }

            // Fallback to global config
            return [
                'url' => config('services.supabase.url'),
                'key' => config('services.supabase.key'),
                'service_key' => config('services.supabase.service_key'),
                'bucket' => config('services.supabase.storage_bucket', 'gallery-uploads'),
            ];
        });
    }

    /**
     * Get Vercel Blob credentials for a user.
     * Falls back to global config if user not configured.
     *
     * @param User $user
     * @return array{token: string, store_url: string, api_url: string}
     */
    public function getVercelCredentials(User $user): array
    {
        $cacheKey = "vercel_creds_{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $settings = $user->settings;

            if ($settings && $settings->hasVercelConfig()) {
                return [
                    'token' => $settings->vercel_blob_token,
                    'store_url' => $settings->vercel_blob_store_url ?? 'https://blob.vercel-storage.com',
                    'api_url' => 'https://vercel.com/api/blob',
                ];
            }

            // Fallback to global config
            return [
                'token' => config('services.vercel.blob_read_write_token'),
                'store_url' => config('services.vercel.blob_store_url', 'https://blob.vercel-storage.com'),
                'api_url' => config('services.vercel.blob_api_url', 'https://vercel.com/api/blob'),
            ];
        });
    }

    /**
     * Get the default storage provider for a user.
     *
     * @param User $user
     * @return string
     */
    public function getDefaultProvider(User $user): string
    {
        $settings = $user->settings;

        if ($settings) {
            return $settings->storage_provider ?? 'supabase';
        }

        return 'supabase';
    }

    /**
     * Clear cached credentials for a user.
     *
     * @param User $user
     * @return void
     */
    public function clearCache(User $user): void
    {
        Cache::forget("supabase_creds_{$user->id}");
        Cache::forget("vercel_creds_{$user->id}");
    }

    /**
     * Validate Supabase credential format.
     *
     * @param string $url
     * @param string $key
     * @return bool
     */
    public function validateSupabaseCredentialFormat(string $url, string $key): bool
    {
        // Check URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if URL is a Supabase URL
        if (!str_contains($url, 'supabase.co')) {
            return false;
        }

        // Check key format (Supabase keys are typically JWT-like)
        if (empty($key) || strlen($key) < 20) {
            return false;
        }

        return true;
    }

    /**
     * Validate Vercel Blob token format.
     *
     * @param string $token
     * @return bool
     */
    public function validateVercelTokenFormat(string $token): bool
    {
        // Vercel Blob read-write tokens follow format: vercel_blob_rw_{storeId}_{secret}
        $pattern = '/^vercel_blob_rw_[A-Za-z0-9]+_/';

        return (bool) preg_match($pattern, $token);
    }

    /**
     * Extract store ID from Vercel Blob token.
     *
     * @param string $token
     * @return string|null
     */
    public function extractVercelStoreId(string $token): ?string
    {
        preg_match('/vercel_blob_rw_([A-Za-z0-9]+)_/', $token, $matches);

        return $matches[1] ?? null;
    }
}
