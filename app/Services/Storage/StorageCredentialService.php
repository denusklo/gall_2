<?php

namespace App\Services\Storage;

use App\Models\User;
use App\Models\StorageCredential;
use App\Models\StorageAccount;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class StorageCredentialService
{
    /**
     * Get Supabase credentials for a user: the given owned credential, else the saved default.
     * Without a saved credential the result has credential_id null and no secrets: the legacy
     * per-user settings and the global env are NOT used here (see environmentCredentials()).
     * Saved credentials must have a trusted hosted identity; anything else fails closed (422).
     *
     * @return array{url: ?string, key: ?string, service_key: ?string, bucket: ?string, credential_id: int|null}
     */
    public function getSupabaseCredentials(User $user, ?int $credentialId = null, bool $lockForUpdate = false): array
    {
        $credential = StorageCredential::where('user_id', $user->id)->where('provider', 'supabase')
            ->when($credentialId !== null, fn ($query) => $query->where('id', $credentialId),
                fn ($query) => $query->where('is_default', true))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($credential && $credential->supabase_url && $credential->supabase_key) {
            $this->assertTrusted($credential);
            return [
                'url' => $credential->supabase_url,
                'key' => $credential->supabase_key,
                'service_key' => $credential->supabase_service_key,
                'bucket' => $credential->supabase_bucket ?? 'images',
                'credential_id' => $credential->id,
            ];
        }
        if ($credentialId !== null) {
            throw ValidationException::withMessages(['credential_id' => 'Supabase credential not found or incomplete.']);
        }
        return ['url' => null, 'key' => null, 'service_key' => null, 'bucket' => null, 'credential_id' => null];
    }

    /**
     * Get Vercel Blob credentials for a user: the given owned credential, else the saved default.
     * Same no-fallback and trusted-identity rules as getSupabaseCredentials().
     *
     * @return array{token: ?string, store_url: ?string, api_url: string, credential_id: int|null}
     */
    public function getVercelCredentials(User $user, ?int $credentialId = null, bool $lockForUpdate = false): array
    {
        $credential = StorageCredential::where('user_id', $user->id)->where('provider', 'vercel')
            ->when($credentialId !== null, fn ($query) => $query->where('id', $credentialId),
                fn ($query) => $query->where('is_default', true))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($credential && $credential->vercel_blob_token) {
            $this->assertTrusted($credential);
            return [
                'token' => $credential->vercel_blob_token,
                'store_url' => $credential->vercel_blob_store_url ?? 'https://blob.vercel-storage.com',
                'api_url' => 'https://vercel.com/api/blob',
                'credential_id' => $credential->id,
            ];
        }
        if ($credentialId !== null) {
            throw ValidationException::withMessages(['credential_id' => 'Vercel credential not found or incomplete.']);
        }
        return ['token' => null, 'store_url' => null, 'api_url' => 'https://vercel.com/api/blob', 'credential_id' => null];
    }

    /** Saved credentials must resolve to a trusted hosted account (no request is made). */
    private function assertTrusted(StorageCredential $credential): void
    {
        try {
            app(TrustedStorageOriginPolicy::class)->identity($credential);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['credential_id' => TrustedStorageOriginPolicy::rejectionMessage('untrusted_account')]);
        }
    }

    /**
     * Before any foreground provider request: Supabase endpoints must resolve to public addresses
     * (DNS checked and pinned again on every request). Vercel uses the fixed API host. Fails with
     * 422 (or 503 when DNS is unavailable) and zero outbound requests.
     */
    public function preflight(string $provider, array $creds): void
    {
        if ($provider !== 'supabase') return;
        try {
            $policy = app(TrustedStorageOriginPolicy::class);
            $policy->transportOptions($policy->supabaseOrigin($creds['url'] ?? null));
        } catch (\RuntimeException $e) {
            $code = $e->getMessage();
            abort(in_array($code, ['dns_unavailable', 'secure_transport_unavailable'], true) ? 503 : 422,
                TrustedStorageOriginPolicy::rejectionMessage($code));
        }
    }

    /**
     * Shared default store configured by the environment (services.* config only, never per-user
     * settings). Returns null unless the identity is a trusted hosted account and the keys needed
     * by the durable flow exist. Shape matches the saved-credential arrays with credential_id null.
     */
    public function environmentCredentials(string $provider): ?array
    {
        $model = $this->environmentCredential($provider, 0);
        if (!$model) return null;
        if ($provider === 'supabase') {
            return ['url' => rtrim($model->supabase_url, '/'), 'key' => $model->supabase_key,
                'service_key' => $model->supabase_service_key, 'bucket' => $model->supabase_bucket,
                'credential_id' => null, 'environment' => true];
        }
        return ['token' => $model->vercel_blob_token, 'store_url' => $model->vercel_blob_store_url,
            'api_url' => 'https://vercel.com/api/blob', 'credential_id' => null, 'environment' => true];
    }

    /** Unsaved credential model for the environment store, owned by $userId for identity checks. */
    public function environmentCredential(string $provider, int $userId): ?StorageCredential
    {
        $credential = new StorageCredential();
        if ($provider === 'supabase') {
            $url = config('services.supabase.url');
            $key = config('services.supabase.key');
            $service = config('services.supabase.service_key');
            if (!is_string($url) || $url === '' || !is_string($service) || $service === '') return null;
            $credential->forceFill(['user_id' => $userId, 'provider' => 'supabase', 'supabase_url' => rtrim($url, '/'),
                'supabase_key' => is_string($key) && $key !== '' ? $key : $service, 'supabase_service_key' => $service,
                'supabase_bucket' => config('services.supabase.storage_bucket') ?: 'images']);
        } elseif ($provider === 'vercel') {
            $token = config('services.vercel.blob_read_write_token');
            if (!is_string($token) || $token === '') return null;
            $credential->forceFill(['user_id' => $userId, 'provider' => 'vercel', 'vercel_blob_token' => $token,
                'vercel_blob_store_url' => config('services.vercel.blob_store_url') ?: 'https://blob.vercel-storage.com']);
        } else {
            return null;
        }
        try {
            app(TrustedStorageOriginPolicy::class)->identity($credential);
        } catch (\Throwable $e) {
            return null;
        }
        return $credential;
    }

    /** Provider of the environment store offered to users without saved accounts (Vercel first). */
    public function defaultUploadProvider(): ?string
    {
        foreach (['vercel', 'supabase'] as $provider) {
            if ($this->environmentCredential($provider, 0)) return $provider;
        }
        return null;
    }

    /**
     * Upload target. Explicit IDs must be owned saved credentials (no fallback). Omitted ID uses the
     * saved default; users with no saved accounts at all use the environment store. Never the legacy
     * per-user settings.
     */
    public function resolveForUpload(User $user, string $provider, $credentialId): array
    {
        $creds = $provider === 'supabase'
            ? $this->getSupabaseCredentials($user, $credentialId === null ? null : (int) $credentialId)
            : $this->getVercelCredentials($user, $credentialId === null ? null : (int) $credentialId);
        if (!empty($creds['credential_id'])) {
            $this->preflight($provider, $creds);
            return $creds;
        }
        // Users with saved accounts keep the previous behavior: no silent switch to the shared store.
        abort_if(StorageCredential::where('user_id', $user->id)->exists(), 422, 'A saved storage account is required.');
        $environment = $this->environmentCredentials($provider);
        abort_unless($environment !== null, 422, 'A saved storage account or a configured default storage is required.');
        $this->preflight($provider, $environment);
        return $environment;
    }

    /**
     * Credentials for a recorded image. Saved credential when recorded; otherwise the environment
     * store only if the image's recorded account identity equals the environment identity.
     */
    public function resolveForImage(User $user, Image $image): array
    {
        $provider = $image->storage_provider;
        if ($image->storage_credential_id) {
            $creds = $provider === 'supabase'
                ? $this->getSupabaseCredentials($user, $image->storage_credential_id)
                : $this->getVercelCredentials($user, $image->storage_credential_id);
        } else {
            abort_unless($image->storage_account_id && $this->environmentMatchesAccount((int) $image->user_id, $provider,
                (int) $image->storage_account_id), 422, 'A recorded storage account is required.');
            $creds = $this->environmentCredentials($provider);
        }
        $this->preflight((string) $provider, $creds);
        return $creds;
    }

    /** True when the environment store is the recorded account of this owner/provider. */
    public function environmentMatchesAccount(int $owner, ?string $provider, int $accountId): bool
    {
        $account = StorageAccount::find($accountId);
        $credential = $provider ? $this->environmentCredential($provider, $owner) : null;
        return $account && $credential && (int) $account->user_id === $owner && $account->provider === $provider
            && app(StorageAccountService::class)->matches($credential, $account);
    }

    /**
     * Get default storage credential for a user.
     *
     * @param User $user
     * @return StorageCredential|null
     */
    public function getDefaultCredential(User $user): ?StorageCredential
    {
        return StorageCredential::where('user_id', $user->id)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get all storage credentials for a user.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllCredentials(User $user)
    {
        return StorageCredential::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get default storage provider for a user.
     *
     * @param User $user
     * @return string
     */
    public function getDefaultProvider(User $user): string
    {
        // Try to get from default credential
        $defaultCred = $this->getDefaultCredential($user);

        if ($defaultCred) {
            return $defaultCred->provider;
        }

        // Fall back to old UserSettings system
        $settings = $user->settings;

        if ($settings) {
            return $settings->storage_provider ?? 'supabase';
        }

        return 'supabase';
    }

    /**
     * Remove credential arrays cached by older releases (they could hold keys). Nothing is cached now.
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
