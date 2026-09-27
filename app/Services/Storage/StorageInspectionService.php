<?php

namespace App\Services\Storage;

use App\Models\Image;
use App\Models\StorageCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only metadata inspection. No imports, mutations, receipt reconstruction or cleanup. */
class StorageInspectionService
{
    private TrustedStorageOriginPolicy $policy;
    private array $account;
    private StorageCredential $credential;
    private int $owner;
    private array $limits;
    private float $started;
    private int $requests = 0;
    private int $pages = 0;
    private array $issues = [];
    private array $access = [];
    private array $accessFailures = [];
    private bool $shared = false;
    private bool $unbound = false;
    private bool $referencesComplete = true;
    private array $unboundIds = [];
    private array $journal = [];
    /** Keep-alive transports for the current scope only, keyed by origin + pinned address. */
    private array $transports = [];
    private array $pinned = [];

    public function __construct(TrustedStorageOriginPolicy $policy)
    {
        $this->policy = $policy;
    }

    public function inspect($owner, $credential, array $limits = [], $imageId = null): array
    {
        // Validate the complete local scope before any transport or default resolution.
        $this->owner = $this->positiveId($owner);
        $credentialId = $this->positiveId($credential);
        $image = $imageId === null ? null : Image::withTrashed()->where('user_id', $this->owner)
            ->find($this->positiveId($imageId));
        if (!User::whereKey($this->owner)->exists() || ($imageId !== null && !$image)) {
            throw new RuntimeException('invalid_owned_scope');
        }
        $saved = StorageCredential::where('user_id', $this->owner)->find($credentialId);
        if (!$saved) throw new RuntimeException('invalid_owned_scope');
        $this->credential = $saved;
        $this->account = $this->policy->identity($this->credential);
        if ($image && $image->storage_provider !== $this->account['provider']) throw new RuntimeException('provider_mismatch');
        if ($this->account['provider'] === 'supabase' && !$this->credential->supabase_service_key) {
            throw new RuntimeException('service_key_required');
        }
        $this->limits = [];
        foreach (['pages' => [10, 30], 'requests' => [30, 100], 'seconds' => [10, 30], 'page_size' => [100, 100]] as $key => [$default, $max]) {
            $value = $this->positiveId($limits[$key] ?? $default);
            if ($value > $max) throw new RuntimeException('invalid_limit');
            $this->limits[$key] = $value;
        }
        $this->started = microtime(true);
        $this->requests = $this->pages = 0;
        $this->issues = $this->access = $this->accessFailures = $this->transports = $this->pinned = [];
        $this->shared = $this->unbound = false;
        $this->referencesComplete = true;
        $this->unboundIds = [];
        $references = $this->references();
        $report = ['mode' => $image ? 'mapping_dry_run' : 'reconcile_read_only', 'owner_id' => $this->owner,
            'credential_id' => $credentialId, 'provider' => $this->account['provider'],
            'account_identity' => $this->account['identity'], 'evidence_level' => 'authenticated_metadata_only',
            'byte_verified' => false, 'limits' => $this->limits, 'findings' => [],
            'limitations' => ['Stateless one-hour upload receipts cannot be enumerated; recent objects may be inflight.',
                'No durable inflight-operation table exists. Grace is classification, never deletion authority.',
                'Listings are not snapshots; partial or changing inventory never proves absence.',
                'Account identity describes current saved credentials, not proof of historical account binding.',
                'Other environments or apps may share this store. Unmatched objects are never cleanup candidates; legacy timestamp-named objects carry no environment marker and need the owning DB row for attribution.']];
        if ($image) {
            $report['proposal'] = $this->mapping($image, $references);
        } else {
            $buckets = $this->account['provider'] === 'supabase' ? [$this->credential->supabase_bucket ?: 'images'] : ['vercel-blob'];
            foreach ($references as $ref) {
                if ($ref->storage_credential_id && $ref->storage_bucket && !in_array($ref->storage_bucket, $buckets, true)) {
                    $buckets[] = $ref->storage_bucket;
                }
            }
            foreach ($buckets as $bucket) {
                if (!$this->validPath($bucket, false)) { $this->issues[] = 'invalid_bucket'; continue; }
                try {
                    $objects = $this->inventory($bucket);
                    foreach ($objects as $path => $metadata) {
                        $matches = $references->filter(fn ($ref) => $ref->storage_bucket === $bucket && $ref->storage_path === $path);
                        $owned = $matches->where('user_id', $this->owner);
                        if ($matches->contains(fn ($ref) => (int) $ref->user_id !== $this->owner)) {
                            // Do not emit other-owner row IDs, paths, timestamps or metadata.
                            $this->shared = true;
                            continue;
                        }
                        if ($matches->isEmpty() && isset($this->journal[$bucket . "\n" . $path])) {
                            // Recorded in this DB's journal; foreign-owner operations reveal nothing.
                            if ($this->journal[$bucket . "\n" . $path] !== $this->owner) { $this->shared = true; continue; }
                            $report['findings'][] = ['kind' => 'journal_operation', 'bucket' => $bucket, 'path' => $path,
                                'image_ids' => [], 'metadata' => $metadata, 'cleanup_eligible' => false];
                            continue;
                        }
                        if ($matches->isEmpty() && ($this->shared || $this->unbound || !$this->referencesComplete)) continue;
                        $kind = $owned->count() > 1 ? 'shared_conflict' : ($owned->isNotEmpty()
                            ? (isset($this->unboundIds[$owned->first()->id]) ? 'unbound' : ($owned->first()->deleted_at ? 'referenced_trashed' : 'referenced_live'))
                            : ($this->recent($metadata['created_at'] ?? null) ? 'recent_inflight_possible' : 'unmatched_candidate'));
                        $finding = ['kind' => $kind, 'bucket' => $bucket, 'path' => $path,
                            'image_ids' => $owned->pluck('id')->all(), 'metadata' => $metadata];
                        if ($owned->isEmpty()) {
                            // Another environment/app may share this store. Never a cleanup candidate.
                            $finding += ['attribution' => $this->attribution($path), 'cleanup_eligible' => false];
                        }
                        $report['findings'][] = $finding;
                    }
                } catch (RuntimeException $e) { $this->issues[] = $e->getMessage(); }
            }
            // A scoped authenticated exact-object check, never list absence, decides each own row.
            foreach ($references->where('user_id', $this->owner) as $ref) {
                if (isset($this->unboundIds[$ref->id])) {
                    $report['findings'][] = ['image_id' => $ref->id, 'kind' => 'unbound', 'reason' => $this->unboundIds[$ref->id]];
                    continue;
                }
                if (!$this->validPath($ref->storage_path) || !$this->validPath($ref->storage_bucket, false)) {
                    $report['findings'][] = ['image_id' => $ref->id, 'kind' => 'unknown', 'reason' => 'invalid_stored_path'];
                    continue;
                }
                $evidence = $this->objectInfo($ref->storage_bucket, $ref->storage_path);
                $kind = $evidence['state'] === 'absent' ? ($ref->deleted_at ? 'expected_trashed_absence' : 'missing_confirmed')
                    : ($evidence['state'] === 'present' ? 'metadata_observed' : 'unknown');
                if ($evidence['state'] === 'present' && !$this->matchesMetadata($ref, $evidence['metadata'])) $kind = 'metadata_mismatch';
                $report['findings'][] = ['image_id' => $ref->id, 'kind' => $kind, 'evidence' => $evidence];
            }
        }
        $report['shared_conflict'] = $this->shared;
        $report['unbound_potential_references'] = $this->unbound;
        $report['references_complete'] = $this->referencesComplete;
        $report['issues'] = array_values(array_unique($this->issues));
        $report['inventory_complete'] = !$image && !$report['issues'];
        $report['snapshot_consistent'] = false;
        $report['requests_used'] = $this->requests;
        $report['pages_used'] = $this->pages;
        $report['elapsed_seconds'] = round(microtime(true) - $this->started, 3);
        $report['mutations'] = 0;
        return $report;
    }

    /**
     * Exact authenticated object evidence for durable-operation recovery. Read-only. Returns
     * state present|absent|unknown with the same absence rules as reconciliation.
     */
    public function exactObject($owner, $credential, string $bucket, string $path, array $limits = []): array
    {
        $this->owner = $this->positiveId($owner);
        $saved = StorageCredential::where('user_id', $this->owner)->find($this->positiveId($credential));
        if (!$saved) throw new RuntimeException('invalid_owned_scope');
        $this->credential = $saved;
        try {
            $this->account = $this->policy->identity($saved);
        } catch (RuntimeException $e) {
            return ['state' => 'unknown', 'reason' => 'untrusted_account', 'requests' => 0];
        }
        if ($this->account['provider'] === 'supabase' && !$saved->supabase_service_key) {
            return ['state' => 'unknown', 'reason' => 'service_key_required', 'requests' => 0];
        }
        $this->limits = ['pages' => 1, 'page_size' => 1];
        foreach (['requests' => [30, 100], 'seconds' => [10, 30]] as $key => [$default, $max]) {
            $value = $this->positiveId($limits[$key] ?? $default);
            if ($value > $max) throw new RuntimeException('invalid_limit');
            $this->limits[$key] = $value;
        }
        $this->started = microtime(true);
        $this->requests = $this->pages = 0;
        $this->issues = $this->access = $this->accessFailures = $this->transports = $this->pinned = [];
        if (!$this->validPath($path) || !$this->validPath($bucket, false)) {
            return ['state' => 'unknown', 'reason' => 'invalid_stored_path', 'requests' => 0];
        }
        return $this->objectInfo($bucket, $path) + ['requests' => $this->requests];
    }

    /**
     * Typed Supabase object-not-found. Pinned storage source (7a0dd891, internal/errors + http/error-handler)
     * renders NoSuchKey as body {statusCode:"404", code:"NoSuchKey", error:"not_found"} with HTTP status
     * userStatusCode=400 unless the route respects statusCode (then 404). Both exact forms are accepted;
     * any other 400 body is not absence. Callers still require proven service-role bucket access.
     */
    public static function supabaseNotFound(int $status, $data): bool
    {
        if (!is_array($data) || ($data['code'] ?? null) !== 'NoSuchKey') return false;
        if ($status === 404) return true;
        return $status === 400 && ($data['statusCode'] ?? null) === '404' && ($data['error'] ?? null) === 'not_found';
    }

    private function attribution(string $path): string
    {
        if (!preg_match('~^app/([a-z0-9-]{1,32})/~', $path, $matches)) return 'legacy_unattributed';
        return $matches[1] === config('storage_maintenance.namespace') ? 'this_namespace_unjournaled' : 'other_environment_namespace';
    }

    private function positiveId($id): int
    {
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)
            || strlen((string) $id) > 18) throw new RuntimeException('explicit_positive_id_required');
        return (int) $id;
    }

    private function references()
    {
        $aliases = [];
        $credentials = StorageCredential::where('provider', $this->account['provider'])->orderBy('id')->limit(10001)->get();
        if ($credentials->count() > 10000) $this->referencesComplete = false;
        foreach ($credentials->take(10000) as $credential) {
            try {
                $identity = $this->policy->identity($credential);
                if ($identity['identity'] === $this->account['identity']) {
                    $aliases[] = $credential->id;
                    if ((int) $credential->user_id !== $this->owner) $this->shared = true;
                }
            } catch (\Throwable $e) { $this->referencesComplete = false; }
        }
        $rows = Image::withTrashed()->orderBy('id')->limit(10001)->get();
        if ($rows->count() > 10000) $this->referencesComplete = false;
        $refs = $rows->take(10000)->filter(function ($row) use ($aliases) {
            $bound = in_array($row->storage_credential_id, $aliases, true);
            $storedHost = parse_url($row->storage_url ?? '', PHP_URL_HOST);
            $sameHost = $storedHost === parse_url($this->account['origin'], PHP_URL_HOST);
            $potentialUnbound = $row->storage_credential_id === null
                && ($row->storage_provider === null || $row->storage_provider === $this->account['provider'] || $sameHost);
            $conflict = $bound ? ($row->storage_provider !== $this->account['provider'] ? 'stored_provider_conflict' : $this->bindingConflict($row)) : null;
            if ($potentialUnbound || $conflict || (!$bound && $sameHost)) {
                $this->unbound = true;
                $this->unboundIds[$row->id] = $conflict ?: 'historical_account_unproven';
                if ((int) $row->user_id !== $this->owner) $this->shared = true;
                return true; // Potential historical/repointed binding, never default-bind.
            }
            return $bound;
        });
        if (!$this->referencesComplete) $this->issues[] = 'reference_scan_incomplete';
        $this->journal = [];
        $key = $this->account['provider'] === 'supabase' ? parse_url($this->account['origin'], PHP_URL_HOST) : $this->account['identity'];
        $operations = \App\Models\StorageOperation::query()
            ->join('storage_accounts', 'storage_accounts.id', '=', 'storage_operations.storage_account_id')
            ->where('storage_accounts.identity_hash', hash('sha256', $this->account['provider'] . "\n" . $key))
            ->whereNotIn('storage_operations.state', ['authorization_failed', 'upload_rejected', 'expired_absent'])
            ->limit(10001)->get(['storage_operations.user_id', 'storage_operations.bucket', 'storage_operations.path']);
        if ($operations->count() > 10000) { $this->referencesComplete = false; $this->issues[] = 'reference_scan_incomplete'; }
        foreach ($operations->take(10000) as $operation) {
            $slot = $operation->bucket . "\n" . $operation->path;
            $this->journal[$slot] = isset($this->journal[$slot]) && $this->journal[$slot] !== (int) $operation->user_id ? -1 : (int) $operation->user_id;
        }
        return $refs;
    }

    private function bindingConflict(Image $image): ?string
    {
        $url = parse_url($image->storage_url ?? '');
        if (!$url || !isset($url['host'])) return 'historical_account_unproven';
        if (($url['scheme'] ?? '') !== 'https' || $url['host'] !== parse_url($this->account['origin'], PHP_URL_HOST)
            || isset($url['port']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) return 'stored_host_conflict';
        $path = rawurldecode($url['path'] ?? '');
        $expected = $this->account['provider'] === 'vercel' ? ['/' . $image->storage_path]
            : ['/storage/v1/object/sign/' . $image->storage_bucket . '/' . $image->storage_path,
                '/storage/v1/object/public/' . $image->storage_bucket . '/' . $image->storage_path];
        return in_array($path, $expected, true) ? null : 'stored_path_conflict';
    }

    private function validPath($path, bool $nested = true): bool
    {
        if (!is_string($path) || $path === '' || strlen($path) > 2048 || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)
            || $path[0] === '/' || (!$nested && strpos($path, '/') !== false)) return false;
        foreach (explode('/', $path) as $part) if (in_array($part, ['', '.', '..'], true)) return false;
        return true;
    }

    private function encoded(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $remaining = $this->limits['seconds'] - (microtime(true) - $this->started);
        if ($this->requests >= $this->limits['requests'] || $remaining < 0.1) throw new RuntimeException('budget_exhausted');
        $supabase = $this->account['provider'] === 'supabase';
        // Supabase POST list is read-only. No other POST, PUT, PATCH or DELETE is allowed.
        if ($method !== 'GET' && !($supabase && $method === 'POST' && strpos($path, '/storage/v1/object/list/') === 0)) {
            throw new RuntimeException('provider_write_forbidden');
        }
        $origin = $supabase ? $this->account['origin'] : 'https://vercel.com';
        // Resolve and validate once per scope so the pin (and the kept-alive connection) stays stable.
        $options = $this->pinned[$origin] ??= $this->policy->transportOptions($origin);
        $remaining = $this->limits['seconds'] - (microtime(true) - $this->started);
        if ($remaining < 0.1) throw new RuntimeException('budget_exhausted');
        $key = $supabase ? $this->credential->supabase_service_key : $this->credential->vercel_blob_token;
        $headers = ['Authorization' => 'Bearer ' . $key];
        if ($supabase) $headers['apikey'] = $key;
        else $headers['x-api-version'] = '11';
        $this->requests++;
        try {
            // Non-streaming selects the installed cURL handler, which honors CURLOPT_RESOLVE.
            // A bounded sink prevents an unbounded buffered response. Keep Laravel's fake/middleware stack.
            $sink = new class(\GuzzleHttp\Psr7\Utils::streamFor('')) implements \Psr\Http\Message\StreamInterface {
                use \GuzzleHttp\Psr7\StreamDecoratorTrait;
                private \Psr\Http\Message\StreamInterface $stream;
                private int $received = 0;
                public function write(string $string): int
                {
                    $this->received += strlen($string);
                    if ($this->received > 1048576) throw new RuntimeException('metadata_response_limit');
                    return $this->stream->write($string);
                }
            };
            // Per-request limits from config: cURL's connect timeout also covers the TLS handshake, which
            // measures ~6s from this host. The soft seconds budget only gates starting new requests,
            // so one slow request may exceed it (documented limitation).
            $requestTimeout = max(1, (int) config('storage_maintenance.inspection_request_timeout', 12));
            $connectTimeout = max(1, min($requestTimeout, (int) config('storage_maintenance.inspection_connect_timeout', 8)));
            $requestStarted = microtime(true);
            // Reused cURL handler: one TLS handshake per scope and pinned host. Laravel's middleware and
            // fake stack still wrap it; every request re-applies pin/TLS/no-redirect/no-proxy/sink/timeouts.
            $response = Http::withHeaders($headers)->setHandler($this->transportFor($origin, $options))
                ->withOptions($options + ['stream' => false, 'sink' => $sink,
                'timeout' => $requestTimeout, 'connect_timeout' => $connectTimeout])
                ->send($method, $origin . $path, [$method === 'GET' ? 'query' : 'json' => $data]);
            $body = $response->toPsrResponse()->getBody();
            // Laravel8 fake sink handling may have consumed the response stream already.
            if ($body->isSeekable()) $body->rewind();
            $raw = '';
            try {
                while (!$body->eof()) {
                    if (microtime(true) - $requestStarted >= $requestTimeout) throw new RuntimeException('budget_exhausted');
                    $chunk = $body->read(min(8192, 1048577 - strlen($raw)));
                    if ($chunk === '' && !$body->eof()) throw new RuntimeException('incomplete_response');
                    $raw .= $chunk;
                    if (strlen($raw) > 1048576) throw new RuntimeException('metadata_response_limit');
                }
            } finally { $body->close(); $sink->close(); }
            $json = json_decode($raw, true);
            $status = $response->status();
            if ($status >= 300 && $status < 400) throw new RuntimeException('redirect_rejected');
            if (in_array($status, [401, 403], true)) throw new RuntimeException('unknown_permission');
            // Supabase storage renders typed errors with HTTP 400 (userStatusCode) and the real status in the
            // body; callers classify 400 bodies explicitly. Anything unrecognized stays unknown.
            if ($status !== 200 && $status !== 404 && !($supabase && $status === 400)) throw new RuntimeException('unknown_provider_status');
            if (!is_array($json)) throw new RuntimeException('malformed_response');
            return [$status, $json];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException($this->transportFailure($e));
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            throw new RuntimeException($this->transportFailure($e));
        } catch (RuntimeException $e) {
            if (in_array($e->getMessage(), ['metadata_response_limit', 'redirect_rejected', 'unknown_permission', 'unknown_provider_status', 'malformed_response', 'budget_exhausted', 'incomplete_response'], true)) throw $e;
            throw new RuntimeException('client_runtime_failure');
        } catch (\Throwable $e) { throw new RuntimeException('client_runtime_failure'); }
    }

    /** Never shared across pinned hosts, accounts, scopes or runs. */
    private function transportFor(string $origin, array $options): callable
    {
        $key = $origin . "\n" . implode(',', $options['curl'][CURLOPT_RESOLVE] ?? []);
        return $this->transports[$key] ??= $this->newTransportHandler();
    }

    protected function newTransportHandler(): callable
    {
        return new \GuzzleHttp\Handler\CurlHandler(['handle_factory' => new \GuzzleHttp\Handler\CurlFactory(1)]);
    }

    private function transportFailure(\Throwable $error): string
    {
        // Inspect numeric transport evidence only; never expose exception messages or request URLs.
        for ($depth = 0; $error && $depth < 5; $depth++, $error = $error->getPrevious()) {
            if ($error instanceof \GuzzleHttp\Exception\ConnectException || $error instanceof \GuzzleHttp\Exception\RequestException) {
                $code = $error->getHandlerContext()['errno'] ?? null;
                return [6 => 'transport_dns_failure', 7 => 'transport_connect_failure',
                    28 => 'transport_timeout', 35 => 'transport_tls_failure', 60 => 'transport_tls_failure'][$code] ?? 'unknown_transport';
            }
        }
        return 'unknown_transport';
    }

    private function vercelRequest(array $query): array
    {
        // Token-wide permission/transport failures are cached for this inspection only.
        // Object-specific not_found and metadata errors are never cached as account failures.
        if (isset($this->accessFailures['vercel-blob'])) throw new RuntimeException($this->accessFailures['vercel-blob']);
        try {
            return $this->request('GET', '/api/blob', $query);
        } catch (RuntimeException $e) {
            if (in_array($e->getMessage(), ['unknown_permission', 'unknown_transport', 'transport_dns_failure',
                'transport_connect_failure', 'transport_timeout', 'transport_tls_failure'], true)) {
                $this->accessFailures['vercel-blob'] = $e->getMessage();
            }
            throw $e;
        }
    }

    private function bucketAccess(string $bucket): bool
    {
        if (isset($this->accessFailures[$bucket])) throw new RuntimeException($this->accessFailures[$bucket]);
        if (array_key_exists($bucket, $this->access)) return $this->access[$bucket];
        try {
            [$status, $data] = $this->request('GET', '/storage/v1/bucket/' . rawurlencode($bucket));
            if ($status !== 200 || ($data['id'] ?? null) !== $bucket || ($data['name'] ?? null) !== $bucket) {
                throw new RuntimeException('unknown_account_access');
            }
            // Claims alone are not authentication. First require this token's accepted bucket response.
            $parts = explode('.', $this->credential->supabase_service_key);
            $claims = count($parts) === 3 ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
            return $this->access[$bucket] = is_array($claims) && ($claims['role'] ?? null) === 'service_role'
                && ($claims['ref'] ?? null) === $this->account['identity'];
        } catch (RuntimeException $e) {
            // Per-inspection only: repeated image checks reuse UNKNOWN, never convert it to absence.
            $this->accessFailures[$bucket] = $e->getMessage();
            throw $e;
        }
    }

    private function inventory(string $bucket): array
    {
        $objects = [];
        $seenPages = [];
        $folders = [''];
        $seenFolders = ['' => true];
        $cursor = null;
        $seenCursors = [];
        do {
            $folder = array_shift($folders);
            $offset = 0;
            do {
                if ($this->pages >= $this->limits['pages']) throw new RuntimeException('page_limit');
                $this->pages++;
                if ($this->account['provider'] === 'supabase') {
                    if (!$this->bucketAccess($bucket)) throw new RuntimeException('unknown_permission_scope');
                    [$status, $data] = $this->request('POST', '/storage/v1/object/list/' . rawurlencode($bucket),
                        ['prefix' => $folder, 'limit' => $this->limits['page_size'], 'offset' => $offset, 'sortBy' => ['column' => 'name', 'order' => 'asc']]);
                    if ($status !== 200 || !array_is_list($data) || count($data) > $this->limits['page_size']) throw new RuntimeException('malformed_inventory');
                    $entries = $data;
                    $more = count($entries) === $this->limits['page_size'];
                } else {
                    $query = ['limit' => $this->limits['page_size']];
                    if ($cursor !== null) $query['cursor'] = $cursor;
                    [$status, $data] = $this->vercelRequest($query);
                    if ($status !== 200 || !is_array($data['blobs'] ?? null) || !array_is_list($data['blobs'])
                        || !is_bool($data['hasMore'] ?? null) || count($data['blobs']) > $this->limits['page_size']) throw new RuntimeException('malformed_inventory');
                    $this->access['vercel-blob'] = true;
                    $entries = $data['blobs'];
                    $more = $data['hasMore'];
                    $cursor = $data['cursor'] ?? null;
                    if ($more && (!is_string($cursor) || $cursor === '' || strlen($cursor) > 4096 || isset($seenCursors[$cursor]))) throw new RuntimeException('repeated_or_invalid_cursor');
                    if ($cursor !== null) $seenCursors[$cursor] = true;
                }
                $pageHash = hash('sha256', json_encode([$folder, $entries]));
                if ($entries && isset($seenPages[$pageHash])) throw new RuntimeException('repeated_page');
                $seenPages[$pageHash] = true;
                foreach ($entries as $entry) {
                    if (!is_array($entry)) throw new RuntimeException('malformed_inventory');
                    $name = $this->account['provider'] === 'supabase' ? ($entry['name'] ?? null) : ($entry['pathname'] ?? null);
                    if (!$this->validPath($name, $this->account['provider'] === 'vercel')) throw new RuntimeException('invalid_remote_path');
                    $path = $folder ? $folder . '/' . $name : $name;
                    if ($this->account['provider'] === 'supabase' && array_key_exists('id', $entry) && $entry['id'] === null) {
                        if (isset($seenFolders[$path])) throw new RuntimeException('repeated_folder');
                        $seenFolders[$path] = true; $folders[] = $path;
                        continue;
                    }
                    $metadata = $this->metadata($entry, $bucket, $path, true);
                    if (isset($objects[$path])) throw new RuntimeException('inventory_changed');
                    $objects[$path] = $metadata;
                }
                $offset += count($entries);
            } while ($more);
        } while ($folders);
        return $objects;
    }

    private function metadata(array $data, string $bucket, string $path, bool $listing = false): array
    {
        if ($this->account['provider'] === 'vercel') {
            $url = is_string($data['url'] ?? null) ? parse_url($data['url']) : false;
            if (($data['pathname'] ?? null) !== $path || !$url || ($url['scheme'] ?? '') !== 'https'
                || ($url['host'] ?? '') !== parse_url($this->account['origin'], PHP_URL_HOST)
                || rawurldecode($url['path'] ?? '') !== '/' . $path || isset($url['query']) || isset($url['fragment'])
                || isset($url['user']) || isset($url['pass']) || isset($url['port'])) throw new RuntimeException('wrong_account_object');
            $size = $data['size'] ?? null;
            $type = $data['contentType'] ?? null;
            $version = null; // Installed head contract has no stable version/checksum field.
            $created = $data['uploadedAt'] ?? null;
        } else {
            if (!$listing && (($data['name'] ?? null) !== $path || ($data['bucket_id'] ?? null) !== $bucket)) throw new RuntimeException('wrong_account_object');
            if (!is_string($data['id'] ?? null) || $data['id'] === '') throw new RuntimeException('malformed_metadata');
            $size = $listing ? ($data['metadata']['size'] ?? null) : ($data['size'] ?? null);
            $type = $listing ? ($data['metadata']['mimetype'] ?? null) : ($data['content_type'] ?? null);
            $version = $data['version'] ?? null;
            $created = $data['created_at'] ?? null;
        }
        if (!is_int($size) || $size < 0 || ($type !== null && (!is_string($type) || !preg_match('~^[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+$~D', $type)))
            || (!$listing && !is_string($type))) throw new RuntimeException('malformed_metadata');
        return ['size' => $size, 'content_type' => $type,
            'version' => is_string($version) && preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $version) ? $version : null,
            'created_at' => is_string($created) && preg_match('/^[0-9TZ:.+ -]{10,40}$/D', $created) ? $created : null];
    }

    private function objectInfo(string $bucket, string $path): array
    {
        try {
            if ($this->account['provider'] === 'supabase') {
                $authoritative = $this->bucketAccess($bucket);
                [$status, $data] = $this->request('GET', '/storage/v1/object/info/authenticated/' . rawurlencode($bucket) . '/' . $this->encoded($path));
                $absent = self::supabaseNotFound($status, $data) && $authoritative;
                if ($status === 400 && !self::supabaseNotFound($status, $data)) throw new RuntimeException('unknown_provider_status');
            } else {
                if (empty($this->access['vercel-blob'])) {
                    [$status, $control] = $this->vercelRequest(['limit' => 1]);
                    if ($status !== 200 || !is_array($control['blobs'] ?? null) || !is_bool($control['hasMore'] ?? null)) throw new RuntimeException('unknown_account_access');
                    foreach ($control['blobs'] as $entry) $this->metadata($entry, $bucket, $entry['pathname'] ?? '', true);
                    $this->access['vercel-blob'] = true;
                }
                [$status, $data] = $this->vercelRequest(['url' => $this->account['origin'] . '/' . $this->encoded($path)]);
                $absent = $status === 404 && ($data['error']['code'] ?? null) === 'not_found';
            }
            if ($absent) return ['state' => 'absent', 'basis' => 'authenticated_exact_object_not_found', 'observed_at' => gmdate('c')];
            if ($status !== 200) {
                $this->issues[] = 'ambiguous_not_found';
                return ['state' => 'unknown', 'reason' => 'ambiguous_not_found'];
            }
            return ['state' => 'present', 'metadata' => $this->metadata($data, $bucket, $path), 'observed_at' => gmdate('c')];
        } catch (RuntimeException $e) {
            $this->issues[] = $e->getMessage();
            return ['state' => 'unknown', 'reason' => $e->getMessage()];
        }
    }

    private function matchesMetadata(Image $image, array $metadata): bool
    {
        return (int) $image->size === $metadata['size'] && $image->mime_type === $metadata['content_type'];
    }

    private function recent($created): bool
    {
        $time = is_string($created) ? strtotime($created) : false;
        return $time === false || $time >= time() - 7200;
    }

    private function mapping(Image $image, $references): array
    {
        $proposal = ['image_id' => $image->id, 'original_row_hash' => hash('sha256', json_encode($image->getRawOriginal())),
            'status' => 'needs_review', 'apply_enabled' => false, 'conflicts' => [], 'evidence' => ['state' => 'unknown']];
        if (!$this->validPath($image->storage_path) || !$this->validPath($image->storage_bucket, false)) {
            $proposal['conflicts'][] = 'invalid_stored_path'; return $proposal;
        }
        $url = parse_url($image->storage_url ?? '');
        if (isset($url['host']) && (($url['scheme'] ?? '') !== 'https' || $url['host'] !== parse_url($this->account['origin'], PHP_URL_HOST)
            || isset($url['user']) || isset($url['pass']) || isset($url['port']))) $proposal['conflicts'][] = 'stored_host_conflict';
        if ($url === false || isset($url['fragment'])) $proposal['conflicts'][] = 'invalid_stored_url';
        $storedPath = rawurldecode($url['path'] ?? '');
        $objectPath = $image->storage_bucket . '/' . $image->storage_path;
        $validStoredPaths = $this->account['provider'] === 'vercel' ? ['/' . $image->storage_path]
            : ['/object/sign/' . $objectPath, '/storage/v1/object/sign/' . $objectPath,
                '/object/public/' . $objectPath, '/storage/v1/object/public/' . $objectPath];
        if (!in_array($storedPath, $validStoredPaths, true)) $proposal['conflicts'][] = 'stored_path_conflict';
        $proposal['stored_host_matches'] = isset($url['host']) && $url['host'] === parse_url($this->account['origin'], PHP_URL_HOST);
        $proposal['bucket'] = $image->storage_bucket;
        $proposal['path'] = $image->storage_path;
        if ($image->storage_credential_id !== null && (int) $image->storage_credential_id !== $this->credential->id) $proposal['conflicts'][] = 'existing_binding_requires_review';
        if ($this->shared || !$this->referencesComplete) $proposal['conflicts'][] = 'shared_or_incomplete_references';
        if ($references->contains(fn ($row) => $row->id !== $image->id && $row->storage_bucket === $image->storage_bucket && $row->storage_path === $image->storage_path)) $proposal['conflicts'][] = 'duplicate_or_unbound_reference';
        // A conflicting stored host is not evidence for querying a different account.
        if ($proposal['conflicts']) return $proposal;
        $proposal['evidence'] = $this->objectInfo($image->storage_bucket, $image->storage_path);
        if ($proposal['evidence']['state'] === 'present' && $this->matchesMetadata($image, $proposal['evidence']['metadata'])) {
            $proposal['status'] = 'candidate_metadata_match_manual_confirmation_required';
        } elseif ($proposal['evidence']['state'] === 'present') $proposal['conflicts'][] = 'metadata_mismatch';
        return $proposal;
    }
}
