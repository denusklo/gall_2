<?php

namespace App\Services\Storage;

use App\Models\StorageAccount;
use App\Models\StorageCredential;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Physical account identity for a saved credential. Works whether environments share a
 * store or use separate ones: identity is provider + project/store, never a key.
 */
class StorageAccountService
{
    private TrustedStorageOriginPolicy $policy;

    public function __construct(TrustedStorageOriginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /** Provider identity key from current credential fields. */
    public function identityKey(StorageCredential $credential): string
    {
        if ($credential->provider === 'supabase') {
            $parts = parse_url((string) $credential->supabase_url);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass'])) {
                throw new RuntimeException('invalid_account_identity');
            }
            return strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }
        if ($credential->provider === 'vercel'
            && preg_match('/^vercel_blob_rw_([A-Za-z0-9]+)_/', (string) $credential->vercel_blob_token, $matches)) {
            return strtolower($matches[1]);
        }
        throw new RuntimeException('invalid_account_identity');
    }

    public function identityHash(string $provider, string $key): string
    {
        return hash('sha256', $provider . "\n" . $key);
    }

    /** Find or create the owner's account row for the credential's current identity and link it. */
    public function forCredential(StorageCredential $credential): StorageAccount
    {
        $key = $this->identityKey($credential);
        $hash = $this->identityHash($credential->provider, $key);
        try {
            $this->policy->identity($credential);
            $trusted = true;
        } catch (\Throwable $e) {
            $trusted = false;
        }
        $attributes = ['user_id' => $credential->user_id, 'provider' => $credential->provider, 'identity_hash' => $hash];
        $account = StorageAccount::where($attributes)->first();
        if (!$account) {
            try {
                $account = StorageAccount::create($attributes + ['identity_key' => $key, 'trusted_hosted' => $trusted]);
            } catch (QueryException $e) {
                // Concurrent creator won the unique key.
                $account = StorageAccount::where($attributes)->firstOrFail();
            }
        }
        if ((int) $credential->getAttribute('storage_account_id') !== $account->id) {
            // Query builder: link only, without touching the credential's updated_at.
            DB::table('storage_credentials')->where('id', $credential->id)->update(['storage_account_id' => $account->id]);
            $credential->setAttribute('storage_account_id', $account->id);
        }
        return $account;
    }

    /** True when the credential still points at the recorded account. */
    public function matches(StorageCredential $credential, StorageAccount $account): bool
    {
        try {
            return $credential->provider === $account->provider
                && (int) $credential->user_id === (int) $account->user_id
                && hash_equals($account->identity_hash, $this->identityHash($credential->provider, $this->identityKey($credential)));
        } catch (RuntimeException $e) {
            return false;
        }
    }
}
