<?php

namespace App\Services\Storage;

use App\Models\Category;
use App\Models\Image;
use App\Models\StorageAccount;
use App\Models\StorageCredential;
use App\Models\StorageOperation;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Durable storage journal. Every upload/delete intent is committed here before any
 * remote effect; recovery acts only on operations recorded in this database.
 */
class StorageOperationService
{
    private const PENDING_EXCLUDED = ['registered', 'authorization_failed', 'upload_rejected', 'expired_absent',
        'finalized', 'tombstoned', 'needs_review'];

    private StorageAccountService $accounts;

    public function __construct(StorageAccountService $accounts)
    {
        $this->accounts = $accounts;
    }

    public function namespace(): string
    {
        $namespace = config('storage_maintenance.namespace');
        if (!is_string($namespace) || !preg_match('/^[a-z0-9-]{1,32}$/D', $namespace)) {
            throw new RuntimeException('invalid_storage_namespace');
        }
        return $namespace;
    }

    /** app/{namespace}/{owner}/{operation}/{slug}.{ext}; fits images.storage_path (191). */
    public function reservedPath(int $owner, string $uuid, string $filename): string
    {
        $name = Str::limit(Str::slug(pathinfo($filename, PATHINFO_FILENAME)), 80, '') ?: 'file';
        $extension = Str::limit(Str::slug(pathinfo($filename, PATHINFO_EXTENSION)), 10, '');
        return 'app/' . $this->namespace() . '/' . $owner . '/' . $uuid . '/' . $name . ($extension !== '' ? '.' . $extension : '');
    }

    public function objectHash(StorageAccount $account, string $bucket, string $path): string
    {
        return hash('sha256', $account->identity_hash . "\n" . $bucket . "\n" . $path);
    }

    public static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function ownedCredential(User $user, $id, bool $lock = false): StorageCredential
    {
        $credential = StorageCredential::where('user_id', $user->id)->whereKey($id)
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($credential !== null, 422, 'A saved storage account is required.');
        return $credential;
    }

    // ------------------------------------------------------------------ journal primitives

    /** Commit an upload intent before any capability, token or remote write exists. */
    public function reserveUpload(User $user, string $provider, array $creds, string $bucket, string $filename,
        string $mime, int $size, array $metadata, int $ttlSeconds): StorageOperation
    {
        abort_unless(!empty($creds['credential_id']), 422, 'A saved storage account is required.');
        return DB::transaction(function () use ($user, $provider, $creds, $bucket, $filename, $mime, $size, $metadata, $ttlSeconds) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $pending = fn ($query) => $query->whereNotIn('state', self::PENDING_EXCLUDED);
            abort_if(StorageOperation::where('user_id', $user->id)->where($pending)->count()
                >= (int) config('storage_maintenance.max_pending_per_owner', 100), 429, 'Too many pending storage operations.');
            abort_if(StorageOperation::where($pending)->count()
                >= (int) config('storage_maintenance.max_pending_global', 1000), 429, 'Storage operation backlog is full.');
            $credential = $this->ownedCredential($user, $creds['credential_id'], true);
            abort_unless($credential->provider === $provider, 422, 'Storage provider does not match the account.');
            try {
                $account = $this->accounts->forCredential($credential);
            } catch (RuntimeException $e) {
                abort(422, 'The storage account identity is invalid.');
            }
            $uuid = (string) Str::uuid();
            $path = $this->reservedPath($user->id, $uuid, $filename);
            $hash = $this->objectHash($account, $bucket, $path);
            return StorageOperation::create([
                'uuid' => $uuid, 'user_id' => $user->id, 'storage_account_id' => $account->id,
                'storage_credential_id' => $credential->id, 'kind' => StorageOperation::UPLOAD, 'state' => 'reserved',
                'namespace' => $this->namespace(), 'provider' => $provider, 'bucket' => $bucket, 'path' => $path,
                'object_hash' => $hash, 'upload_object_hash' => $hash, 'metadata' => $metadata,
                'expected_size' => $size, 'expected_mime' => $mime,
                'authorization_expires_at' => now()->addSeconds($ttlSeconds),
            ]);
        });
    }

    /** Conditional state change. Returns false when another writer changed the operation. */
    public function transition(StorageOperation $op, array $from, string $to, array $extra = []): bool
    {
        $updated = DB::table('storage_operations')->where('id', $op->id)->whereIn('state', $from)
            ->update($extra + ['state' => $to, 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
        $op->refresh();
        return $updated === 1;
    }

    /** Lease with random token and monotonically increasing fencing generation. */
    public function claimLease(StorageOperation $op): ?array
    {
        $token = (string) Str::uuid();
        $now = now();
        $claimed = DB::table('storage_operations')->where('id', $op->id)
            ->where(fn ($query) => $query->whereNull('lease_token')->orWhere('lease_expires_at', '<', $now))
            ->update(['lease_token' => $token, 'lease_generation' => DB::raw('lease_generation + 1'),
                'lease_expires_at' => $now->copy()->addSeconds((int) config('storage_maintenance.lease_seconds', 60)),
                'updated_at' => $now]);
        if ($claimed !== 1) return null;
        $op->refresh();
        return ['token' => $token, 'generation' => $op->lease_generation];
    }

    /** Update only while still holding the exact lease generation. */
    public function fenced(StorageOperation $op, array $lease, array $updates): bool
    {
        $done = DB::table('storage_operations')->where('id', $op->id)
            ->where('lease_token', $lease['token'])->where('lease_generation', $lease['generation'])
            ->update($updates + ['revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
        $op->refresh();
        return $done === 1;
    }

    public function release(StorageOperation $op, array $lease, array $updates = []): bool
    {
        return $this->fenced($op, $lease, $updates + ['lease_token' => null, 'lease_expires_at' => null]);
    }

    // ------------------------------------------------------------------ upload registration

    /** Lock inside the registration transaction; duplicates and terminal states are 409. */
    public function lockForRegistration(User $user, string $uuid): StorageOperation
    {
        $op = StorageOperation::where('uuid', $uuid)->where('user_id', $user->id)->lockForUpdate()->first();
        abort_unless($op && $op->kind === StorageOperation::UPLOAD, 403, 'Invalid upload receipt.');
        abort_if($op->state === 'registered', 409, 'This upload has already been registered.');
        abort_if($op->isTerminal() || in_array($op->state, ['needs_review', 'upload_rejected'], true), 409,
            'This upload operation can no longer be registered.');
        return $op;
    }

    /** Pre-check before verification HTTP, so replays cost no provider requests. */
    public function uploadForReceipt(User $user, $uuid): StorageOperation
    {
        abort_unless(is_string($uuid) && Str::isUuid($uuid), 410, 'This upload receipt predates durable uploads. Start the upload again.');
        $op = StorageOperation::where('uuid', $uuid)->where('user_id', $user->id)->first();
        abort_unless($op && $op->kind === StorageOperation::UPLOAD, 403, 'Invalid upload receipt.');
        abort_if($op->state === 'registered', 409, 'This upload has already been registered.');
        abort_if($op->isTerminal() || in_array($op->state, ['needs_review', 'upload_rejected'], true), 409,
            'This upload operation can no longer be registered.');
        return $op;
    }

    /** Called inside the registration transaction after the image row exists. */
    public function markRegistered(StorageOperation $op, Image $image, array $observed): void
    {
        $image->forceFill(['storage_account_id' => $op->storage_account_id, 'upload_operation_id' => $op->id])->save();
        DB::table('storage_operations')->where('id', $op->id)->update([
            'state' => 'registered', 'image_id' => $image->id, 'completed_at' => now(), 'last_observed_at' => now(),
            'observed_size' => $observed['size'] ?? null, 'observed_mime' => $observed['content_type'] ?? null,
            'provider_version' => $observed['version'] ?? null, 'checksum_state' => 'unverified',
            'last_error_code' => null, 'lease_token' => null, 'lease_expires_at' => null,
            'revision' => DB::raw('revision + 1'), 'updated_at' => now(),
        ]);
    }

    public function recordFailure(StorageOperation $op, string $code): void
    {
        DB::table('storage_operations')->where('id', $op->id)->update(['last_error_code' => $code,
            'attempt_count' => DB::raw('attempt_count + 1'), 'last_observed_at' => now(),
            'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
        $op->refresh();
    }

    /** Owner lock, operation lock, category revalidation, image + categories + journal in one transaction. */
    public function registerFromOperation(User $user, StorageOperation $op, array $imageAttributes, array $observed): Image
    {
        return DB::transaction(function () use ($user, $op, $imageAttributes, $observed) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = $this->lockForRegistration($user, $op->uuid);
            $categoryIds = array_values(array_unique(array_map('intval', $locked->metadata['category_ids'] ?? [])));
            $owned = Category::where('user_id', $user->id)->whereIn('id', $categoryIds)->count();
            if ($owned !== count($categoryIds)) throw new RuntimeException('category_changed');
            $image = Image::create($imageAttributes + ['user_id' => $user->id, 'storage_provider' => $locked->provider,
                'storage_credential_id' => $locked->storage_credential_id, 'storage_bucket' => $locked->bucket,
                'storage_path' => $locked->path]);
            $image->categories()->attach($categoryIds);
            $this->markRegistered($locked, $image, $observed);
            return $image->load('categories');
        });
    }

    // ------------------------------------------------------------------ provider calls

    private function supabaseHeaders(array $creds): array
    {
        return ['apikey' => $creds['service_key'], 'Authorization' => 'Bearer ' . $creds['service_key']];
    }

    /** Server-held bytes, reserved path, x-upsert:false. */
    public function supabaseServerUpload(StorageOperation $op, array $creds, string $bytes, string $mime): string
    {
        try {
            $response = Http::withHeaders($this->supabaseHeaders($creds) + ['x-upsert' => 'false'])
                ->withBody($bytes, $mime)
                ->post(rtrim($creds['url'], '/') . '/storage/v1/object/' . rawurlencode($op->bucket) . '/' . self::encodePath($op->path));
        } catch (ConnectionException $e) {
            return 'outcome_unknown';
        }
        if ($response->successful()) return 'uploaded';
        if (in_array($response->status(), [400, 409], true)) return 'needs_review';
        return $response->status() >= 500 || $response->status() === 429 ? 'outcome_unknown' : 'upload_rejected';
    }

    /** POST /object/upload/sign with x-upsert:false; returns the exact-path signed PUT URL. */
    public function supabaseSignedUploadUrl(StorageOperation $op, array $creds): ?string
    {
        $base = rtrim($creds['url'], '/');
        $encoded = '/object/upload/sign/' . rawurlencode($op->bucket) . '/' . self::encodePath($op->path);
        try {
            $response = Http::withHeaders($this->supabaseHeaders($creds) + ['x-upsert' => 'false'])
                ->withoutRedirecting()->timeout(15)->withBody('{}', 'application/json')->post($base . '/storage/v1' . $encoded);
        } catch (ConnectionException $e) {
            return null;
        }
        $value = $response->successful() ? $response->json('url') : null;
        if (!is_string($value) || preg_match('/[\x00-\x1f\x7f\\\\#]/', $value)) return null;
        $url = parse_url($value);
        if (!$url || isset($url['scheme']) || isset($url['host']) || isset($url['user'])) return null;
        $path = '/' . ltrim($url['path'] ?? '', '/');
        if (strpos($path, '/storage/v1/') === 0) $path = substr($path, strlen('/storage/v1'));
        parse_str($url['query'] ?? '', $query);
        if (rawurldecode($path) !== '/object/upload/sign/' . $op->bucket . '/' . $op->path
            || !is_string($query['token'] ?? null) || $query['token'] === '') return null;
        return $base . '/storage/v1' . $encoded . '?token=' . rawurlencode($query['token']);
    }

    /** Authenticated metadata; 'present' | 'missing' | 'mismatch' | 'unknown'. */
    public function supabaseObjectInfo(StorageOperation $op, array $creds): array
    {
        try {
            $response = Http::withHeaders($this->supabaseHeaders($creds))->withoutRedirecting()->timeout(15)
                ->get(rtrim($creds['url'], '/') . '/storage/v1/object/info/authenticated/' . rawurlencode($op->bucket)
                    . '/' . self::encodePath($op->path));
        } catch (ConnectionException $e) {
            return ['state' => 'unknown'];
        }
        $data = $response->json();
        // Informational only (keeps the upload pending); never deletion authority.
        if (StorageInspectionService::supabaseNotFound($response->status(), $data)) {
            return ['state' => 'missing'];
        }
        if (!$response->successful() || !is_array($data)) return ['state' => 'unknown'];
        $observed = ['size' => $data['size'] ?? null, 'content_type' => $data['content_type'] ?? null,
            'version' => is_string($data['version'] ?? null) ? substr($data['version'], 0, 128) : null];
        $matches = ($data['name'] ?? null) === $op->path && ($data['bucket_id'] ?? null) === $op->bucket
            && $observed['size'] === $op->expected_size && $observed['content_type'] === $op->expected_mime;
        return ['state' => $matches ? 'present' : 'mismatch', 'observed' => $observed];
    }

    // ------------------------------------------------------------------ deletion

    /** Live references to the same physical object other than this image, or pending uploads to it. */
    private function sharedReference(Image $image, StorageAccount $account, string $objectHash): bool
    {
        $others = Image::where('storage_provider', $image->storage_provider)->where('storage_bucket', $image->storage_bucket)
            ->where('storage_path', $image->storage_path)->whereKeyNot($image->id)->get(['id', 'storage_credential_id']);
        foreach ($others as $other) {
            $credential = $other->storage_credential_id ? StorageCredential::find($other->storage_credential_id) : null;
            // Unbound or unidentifiable live references may point at this object; do not guess.
            if (!$credential) return true;
            try {
                $key = $this->accounts->identityKey($credential);
            } catch (RuntimeException $e) {
                return true;
            }
            if (hash_equals($account->identity_hash, $this->accounts->identityHash($credential->provider, $key))) return true;
        }
        return StorageOperation::where('kind', StorageOperation::UPLOAD)->where('object_hash', $objectHash)
            ->whereNotIn('state', self::PENDING_EXCLUDED)->where(fn ($q) => $q->whereNull('image_id')->orWhere('image_id', '!=', $image->id))
            ->exists();
    }

    /** Horizon until which an outstanding capability could recreate the deleted path. */
    private function tombstoneUntil(Image $image)
    {
        $upload = $image->upload_operation_id ? StorageOperation::find($image->upload_operation_id) : null;
        $horizon = now()->addSeconds((int) config('storage_maintenance.legacy_authorization_horizon_seconds', 3600));
        if ($upload && $upload->authorization_expires_at && $upload->authorization_expires_at->greaterThan($horizon)) {
            $horizon = $upload->authorization_expires_at;
        }
        return $horizon->copy()->addSeconds((int) config('storage_maintenance.upload_grace_seconds', 900));
    }

    /**
     * Full durable delete. Returns [httpStatus, body]. The image row is soft-deleted only after
     * provider proof; UNKNOWN keeps the row and the durable intent for retry/recovery.
     */
    public function deleteImage(User $user, Image $image, array $creds): array
    {
        $credential = $this->ownedCredential($user, $image->storage_credential_id);
        try {
            $account = $this->accounts->forCredential($credential);
        } catch (RuntimeException $e) {
            return [422, ['error' => 'The storage account identity is invalid.']];
        }
        if ($image->storage_account_id && (int) $image->storage_account_id !== $account->id) {
            return [409, ['error' => 'The saved account no longer points at this image\'s store.', 'retryable' => false]];
        }
        $objectHash = $this->objectHash($account, $image->storage_bucket, $image->storage_path);
        $op = StorageOperation::where('delete_image_id', $image->id)->first();
        if (!$op) {
            if ($this->sharedReference($image, $account, $objectHash)) {
                return [409, ['error' => 'This storage object is shared or still being uploaded; review is required.', 'retryable' => false]];
            }
            $op = DB::transaction(function () use ($user, $image, $account, $credential, $objectHash) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = StorageOperation::where('delete_image_id', $image->id)->lockForUpdate()->first();
                return $existing ?: StorageOperation::create([
                    'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'storage_account_id' => $account->id,
                    'storage_credential_id' => $credential->id, 'image_id' => $image->id, 'delete_image_id' => $image->id,
                    'kind' => StorageOperation::DELETE, 'state' => 'requested', 'namespace' => $this->namespace(),
                    'provider' => $image->storage_provider, 'bucket' => $image->storage_bucket, 'path' => $image->storage_path,
                    'object_hash' => $objectHash, 'metadata' => ['storage_url_host' => parse_url((string) $image->storage_url, PHP_URL_HOST)],
                    'tombstone_until' => $this->tombstoneUntil($image),
                ]);
            });
        }
        if (in_array($op->state, ['tombstoned', 'finalized'], true)) {
            return [200, ['message' => 'Image deleted successfully', 'replayed' => true] + $op->publicFields()];
        }
        if ($op->state === 'needs_review') {
            return [409, ['error' => 'This deletion needs review.'] + $op->publicFields()];
        }
        $lease = $this->claimLease($op);
        if (!$lease) return [409, ['error' => 'This deletion is already in progress.'] + $op->publicFields()];
        if ($op->state !== 'absence_confirmed') {
            $this->fenced($op, $lease, ['state' => 'deleting', 'remote_started_at' => now(),
                'attempt_count' => DB::raw('attempt_count + 1')]);
            $outcome = $this->providerDelete($op, $image, $creds);
            if ($outcome !== 'absent') {
                $this->release($op, $lease, ['state' => $outcome, 'last_error_code' => $outcome, 'last_observed_at' => now()]);
                return [502, ['error' => 'Storage deletion failed.'] + $op->publicFields()];
            }
            // Durable before row finalization so a DB failure next is recoverable without HTTP.
            $this->fenced($op, $lease, ['state' => 'absence_confirmed', 'last_observed_at' => now(), 'last_error_code' => null]);
        }
        return $this->finalizeDelete($op, $lease)
            ? [200, ['message' => 'Image deleted successfully'] + $op->publicFields()]
            : [502, ['error' => 'Storage deletion is pending finalization.'] + $op->publicFields()];
    }

    /** 'absent' only with provider proof. */
    public function providerDelete(StorageOperation $op, Image $image, array $creds): string
    {
        try {
            if ($op->provider === 'supabase') {
                $response = Http::withHeaders($this->supabaseHeaders($creds))
                    ->delete(rtrim($creds['url'], '/') . '/storage/v1/object/' . rawurlencode($op->bucket), ['prefixes' => [$op->path]]);
                if (in_array($response->status(), [401, 403], true)) return 'access_denied';
                $deleted = $response->json();
                return $response->successful() && is_array($deleted) && count($deleted) === 1
                    && ($deleted[0]['name'] ?? null) === $op->path ? 'absent' : 'outcome_unknown';
            }
            $url = (string) $image->storage_url;
            app(UploadReceiptService::class)->assertVercelUrl($url, $op->path, $creds);
            $headers = ['authorization' => 'Bearer ' . $creds['token'], 'x-api-version' => '11'];
            $response = Http::withHeaders($headers + ['content-type' => 'application/json'])->withoutRedirecting()
                ->timeout(15)->post('https://vercel.com/api/blob/delete', ['urls' => [$url]]);
            if (in_array($response->status(), [401, 403], true)) return 'access_denied';
            if (!$response->successful()) return 'outcome_unknown';
            // Vercel delete returns no per-object proof: require list access, then typed not_found.
            $list = Http::withHeaders($headers)->withoutRedirecting()->timeout(15)->get('https://vercel.com/api/blob', ['limit' => 1]);
            if (!$list->successful() || !is_array($list->json('blobs'))) return 'outcome_unknown';
            $head = Http::withHeaders($headers)->withoutRedirecting()->timeout(15)->get('https://vercel.com/api/blob', ['url' => $url]);
            return $head->status() === 404 && $head->json('error.code') === 'not_found' ? 'absent' : 'outcome_unknown';
        } catch (ConnectionException $e) {
            return 'outcome_unknown';
        } catch (\Illuminate\Validation\ValidationException $e) {
            return 'needs_review';
        }
    }

    /** Fenced: soft-delete the image and tombstone the intent in one transaction. */
    public function finalizeDelete(StorageOperation $op, array $lease): bool
    {
        try {
            $done = DB::transaction(function () use ($op, $lease) {
                $locked = StorageOperation::whereKey($op->id)->where('lease_token', $lease['token'])
                    ->where('lease_generation', $lease['generation'])->lockForUpdate()->first();
                if (!$locked || $locked->state !== 'absence_confirmed') return false;
                $image = Image::withTrashed()->find($locked->delete_image_id);
                if ($image && !$image->trashed()) $image->delete();
                DB::table('storage_operations')->where('id', $op->id)->update(['state' => 'tombstoned',
                    'completed_at' => now(), 'lease_token' => null, 'lease_expires_at' => null,
                    'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
                return true;
            });
        } catch (\Throwable $e) {
            $done = false;
        }
        $op->refresh();
        if (!$done && $op->lease_token === $lease['token']) {
            // Keep absence_confirmed; recovery finalizes without provider calls.
            $this->release($op, $lease, ['last_error_code' => 'finalize_failed']);
        }
        return $done;
    }

    // ------------------------------------------------------------------ recovery

    /**
     * Recover one journaled operation. Dry-run observes only. Apply may register a proven
     * upload, finalize a proven delete, or repeat the recorded delete intent; never anything else.
     */
    public function recover(StorageOperation $op, bool $apply, StorageInspectionService $inspection, int $requests, int $seconds): array
    {
        $result = ['operation_id' => $op->uuid, 'kind' => $op->kind, 'state_before' => $op->state, 'requests' => 0];
        $lease = null;
        if ($apply) {
            $lease = $this->claimLease($op);
            if (!$lease) return $result + ['action' => 'skipped_leased'];
        }
        try {
            $credential = $op->storage_credential_id ? StorageCredential::find($op->storage_credential_id) : null;
            $account = StorageAccount::find($op->storage_account_id);
            if (!$credential || !$account || !$this->accounts->matches($credential, $account)) {
                return $result + ['action' => 'blocked_account'];
            }
            $user = User::findOrFail($op->user_id);
            $observe = function () use ($inspection, $op, $credential, $requests, $seconds, &$result) {
                $evidence = $inspection->exactObject($op->user_id, $credential->id, $op->bucket, $op->path,
                    ['requests' => max(1, $requests), 'seconds' => max(1, $seconds)]);
                $result['requests'] += $evidence['requests'];
                return $evidence;
            };
            if ($op->kind === StorageOperation::UPLOAD) {
                if (in_array($op->state, ['reserved', 'authorizing'], true)) {
                    // No capability was delivered to any client; nothing can be uploaded.
                    if ($apply) $this->fenced($op, $lease, ['state' => 'authorization_failed', 'last_error_code' => 'never_authorized']);
                    return $result + ['action' => 'authorization_failed'];
                }
                if ($op->state === 'uploaded') {
                    // Server upload was accepted by the provider for this exact reserved path.
                    return $result + ['action' => $apply ? $this->registerRecovered($user, $op, $credential,
                        ['size' => $op->observed_size, 'content_type' => $op->observed_mime]) : 'would_register'];
                }
                $evidence = $observe();
                if ($evidence['state'] === 'present') {
                    $metadata = $evidence['metadata'];
                    if ($metadata['size'] !== $op->expected_size || $metadata['content_type'] !== $op->expected_mime) {
                        if ($apply) $this->fenced($op, $lease, ['state' => 'needs_review', 'last_error_code' => 'metadata_mismatch']);
                        return $result + ['action' => 'needs_review'];
                    }
                    return $result + ['action' => $apply ? $this->registerRecovered($user, $op, $credential, $metadata) : 'would_register'];
                }
                if ($evidence['state'] === 'absent' && $op->authorization_expires_at
                    && now()->greaterThan($op->authorization_expires_at->copy()->addSeconds((int) config('storage_maintenance.upload_grace_seconds', 900)))) {
                    if ($apply) $this->fenced($op, $lease, ['state' => 'expired_absent', 'last_observed_at' => now()]);
                    return $result + ['action' => 'expired_absent'];
                }
                return $result + ['action' => $evidence['state'] === 'absent' ? 'pending_authorization_active' : 'unknown',
                    'reason' => $evidence['reason'] ?? null];
            }
            // Delete intents.
            if ($op->state === 'absence_confirmed') {
                return $result + ['action' => $apply ? ($this->finalizeDelete($op, $lease) ? 'finalized' : 'finalize_failed') : 'would_finalize'];
            }
            if ($op->state === 'tombstoned') {
                if ($op->tombstone_until && now()->lessThan($op->tombstone_until)) return $result + ['action' => 'tombstone_active'];
                $evidence = $observe();
                if ($evidence['state'] === 'absent') {
                    if ($apply) $this->fenced($op, $lease, ['state' => 'finalized', 'last_observed_at' => now()]);
                    return $result + ['action' => 'finalized'];
                }
                if ($evidence['state'] === 'present') {
                    if ($apply) $this->fenced($op, $lease, ['state' => 'needs_review', 'last_error_code' => 'recreated_after_delete']);
                    return $result + ['action' => 'needs_review'];
                }
                return $result + ['action' => 'unknown', 'reason' => $evidence['reason'] ?? null];
            }
            if ($op->state === 'needs_review') return $result + ['action' => 'needs_review'];
            $evidence = $observe();
            if ($evidence['state'] === 'absent') {
                if (!$apply) return $result + ['action' => 'would_finalize'];
                $this->fenced($op, $lease, ['state' => 'absence_confirmed', 'last_observed_at' => now()]);
                return $result + ['action' => $this->finalizeDelete($op, $lease) ? 'finalized' : 'finalize_failed'];
            }
            if ($evidence['state'] === 'present' && $apply) {
                // Repeat only the recorded delete intent for this exact object.
                $image = Image::withTrashed()->find($op->delete_image_id);
                $creds = $op->provider === 'supabase'
                    ? ['url' => $credential->supabase_url, 'service_key' => $credential->supabase_service_key]
                    : ['token' => $credential->vercel_blob_token];
                if (!$image) return $result + ['action' => 'needs_review'];
                $outcome = $this->providerDelete($op, $image, $creds);
                $result['requests'] += $op->provider === 'supabase' ? 1 : 3;
                $this->fenced($op, $lease, ['state' => $outcome === 'absent' ? 'absence_confirmed' : $outcome,
                    'attempt_count' => DB::raw('attempt_count + 1'), 'last_observed_at' => now()]);
                if ($outcome === 'absent') return $result + ['action' => $this->finalizeDelete($op, $lease) ? 'finalized' : 'finalize_failed'];
                return $result + ['action' => 'delete_retried_' . $outcome];
            }
            return $result + ['action' => $evidence['state'] === 'present' ? 'would_retry_delete' : 'unknown',
                'reason' => $evidence['reason'] ?? null];
        } finally {
            if ($lease) {
                $op->refresh();
                if ($op->lease_token === $lease['token']) $this->release($op, $lease);
            }
        }
    }

    private function registerRecovered(User $user, StorageOperation $op, StorageCredential $credential, array $observed): string
    {
        $metadata = $op->metadata ?? [];
        $url = $op->provider === 'supabase'
            ? rtrim($credential->supabase_url, '/') . '/storage/v1/object/public/' . rawurlencode($op->bucket) . '/' . self::encodePath($op->path)
            : 'https://' . $this->accounts->identityKey($credential) . '.public.blob.vercel-storage.com/' . self::encodePath($op->path);
        try {
            $this->registerFromOperation($user, $op, [
                'title' => (string) ($metadata['title'] ?? $metadata['filename'] ?? 'Recovered upload'),
                'description' => $metadata['description'] ?? null, 'storage_url' => $url,
                'filename' => (string) ($metadata['filename'] ?? basename($op->path)),
                'mime_type' => (string) $observed['content_type'], 'size' => (int) $observed['size'],
            ], $observed);
            return 'registered';
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'category_changed') {
                DB::table('storage_operations')->where('id', $op->id)->update(['state' => 'needs_review',
                    'last_error_code' => 'category_changed', 'updated_at' => now()]);
                return 'needs_review';
            }
            throw $e;
        }
    }
}
