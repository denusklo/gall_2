<?php

namespace App\Services\Storage;

use App\Models\Image;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UploadReceiptService
{
    public function issue(User $user, string $provider, array $credentials, string $path, string $bucket, array $metadata,
        ?string $operationUuid = null): string
    {
        return Crypt::encryptString(json_encode([
            // Durable journal operation committed before this receipt was issued.
            'operation_id' => $operationUuid,
            'version' => 1,
            'user_id' => $user->id,
            'provider' => $provider,
            'credential_id' => $credentials['credential_id'],
            'account' => $this->accountFingerprint($credentials),
            'path' => $path,
            'bucket' => $bucket,
            'metadata' => $metadata,
            'expires_at' => now()->addHour()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function read(string $encrypted, User $user, string $provider): array
    {
        try {
            $receipt = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException | \JsonException $e) {
            abort(403, 'Invalid upload receipt.');
        }

        abort_unless(is_array($receipt) && ($receipt['version'] ?? null) === 1, 403, 'Invalid upload receipt.');
        abort_unless(($receipt['user_id'] ?? null) === $user->id, 403, 'Upload receipt belongs to another user.');
        $this->matches(($receipt['provider'] ?? null) === $provider, 'receipt', 'Upload provider does not match.');
        abort_if(($receipt['expires_at'] ?? 0) <= now()->timestamp, 410, 'Upload receipt expired.');

        return $receipt;
    }

    public function assertAccount(array $receipt, array $credentials): void
    {
        $this->matches(
            $receipt['credential_id'] === $credentials['credential_id']
                && hash_equals($receipt['account'], $this->accountFingerprint($credentials)),
            'credential_id',
            'The upload account has changed. Request a new upload receipt.'
        );
    }

    public function matches(bool $matches, string $field, string $message = 'Upload data does not match its receipt.'): void
    {
        if (!$matches) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** Serialize completions for a user, including retries after soft deletion. */
    public function register(User $user, array $receipt, callable $create): Image
    {
        return DB::transaction(function () use ($user, $receipt, $create) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($receipt['credential_id'] !== null) {
                // Current locking read, not an earlier account snapshot. Keep the
                // owner -> credential lock order used by credential deletion.
                $resolver = app(StorageCredentialService::class);
                $fresh = $receipt['provider'] === 'supabase'
                    ? $resolver->getSupabaseCredentials($user, $receipt['credential_id'], true)
                    : $resolver->getVercelCredentials($user, $receipt['credential_id'], true);
                $this->assertAccount($receipt, $fresh);
            }
            $exists = Image::withTrashed()
                ->where('user_id', $user->id)
                ->where('storage_provider', $receipt['provider'])
                ->where('storage_credential_id', $receipt['credential_id'])
                ->where('storage_bucket', $receipt['bucket'])
                ->where('storage_path', $receipt['path'])
                ->exists();
            abort_if($exists, 409, 'This upload has already been registered.');
            $operation = isset($receipt['operation_id'])
                ? app(StorageOperationService::class)->lockForRegistration($user, $receipt['operation_id'])
                : null;

            return $create($operation);
        });
    }

    /** Public Blob URLs are bound to the token's store and the authorized pathname. */
    public function assertVercelUrl(string $url, string $path, array $credentials): void
    {
        preg_match('/^vercel_blob_rw_([A-Za-z0-9]+)_/', $credentials['token'], $matches);
        $parts = parse_url($url);
        $this->matches(
            isset($matches[1]) && is_array($parts)
                && ($parts['scheme'] ?? null) === 'https'
                && ($parts['host'] ?? null) === strtolower($matches[1]) . '.public.blob.vercel-storage.com'
                && !isset($parts['port']) && !isset($parts['user']) && !isset($parts['pass'])
                && !isset($parts['query']) && !isset($parts['fragment'])
                && rawurldecode($parts['path'] ?? '') === '/' . $path,
            'blob.url',
            'Blob URL does not match the authorized store and pathname.'
        );
    }

    private function accountFingerprint(array $credentials): string
    {
        return hash_hmac('sha256', json_encode($credentials, JSON_THROW_ON_ERROR), config('app.key'));
    }
}
