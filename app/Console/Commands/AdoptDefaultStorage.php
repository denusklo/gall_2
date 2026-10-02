<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\User;
use App\Services\Storage\StorageAccountService;
use App\Services\Storage\StorageCredentialService;
use App\Services\Storage\StorageInspectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bind an owner's legacy images (no credential, no account) to the shared default store, but only
 * when the stored URL points at that store and an authenticated read-only exact-object check proves
 * the object exists with the recorded size and type. Dry-run by default; never writes to providers.
 */
class AdoptDefaultStorage extends Command
{
    protected $signature = 'storage:adopt-default
        {--owner= : Required positive owner ID}
        {--apply : Set images.storage_account_id for verified matches; default is dry-run}
        {--max-images=50 : Images inspected per run, maximum 200}';

    protected $description = 'Adopt verified legacy images into the shared default store account (dry-run unless --apply)';

    public function handle(StorageCredentialService $credentials, StorageAccountService $accounts,
        StorageInspectionService $inspection): int
    {
        $owner = (string) $this->option('owner');
        $max = (string) $this->option('max-images');
        if (!preg_match('/^[1-9][0-9]{0,17}$/D', $owner) || !User::whereKey((int) $owner)->exists()
            || !preg_match('/^[1-9][0-9]{0,2}$/D', $max) || (int) $max > 200) {
            $this->line(json_encode(['mode' => 'adopt_default', 'error' => 'invalid_scope', 'mutations' => 0]));
            return 1;
        }
        $owner = (int) $owner;
        $apply = (bool) $this->option('apply');
        $candidates = Image::where('user_id', $owner)->whereNull('storage_credential_id')
            ->whereNull('storage_account_id')->orderBy('id')->limit((int) $max)->get();
        $results = [];
        $bound = 0;
        foreach ($candidates as $image) {
            $result = ['image_id' => $image->id, 'provider' => $image->storage_provider];
            $environment = $credentials->environmentCredential((string) $image->storage_provider, $owner);
            if (!$environment) {
                $results[] = $result + ['action' => 'skip', 'reason' => 'no_default_store_for_provider'];
                continue;
            }
            $reason = $this->urlMismatch($image, $environment, $accounts);
            if ($reason) {
                $results[] = $result + ['action' => 'skip', 'reason' => $reason];
                continue;
            }
            try {
                $evidence = $inspection->exactObjectWith($owner, $environment, (string) $image->storage_bucket,
                    (string) $image->storage_path, ['requests' => 5, 'seconds' => 20]);
            } catch (\Throwable $e) {
                $evidence = ['state' => 'unknown', 'reason' => 'inspection_rejected'];
            }
            if ($evidence['state'] !== 'present') {
                $results[] = $result + ['action' => 'skip', 'reason' => $evidence['state'] === 'absent'
                    ? 'object_absent' : 'unknown_' . ($evidence['reason'] ?? 'evidence')];
                continue;
            }
            $metadata = $evidence['metadata'];
            if ((int) $image->size !== $metadata['size'] || $image->mime_type !== $metadata['content_type']) {
                $results[] = $result + ['action' => 'skip', 'reason' => 'metadata_mismatch'];
                continue;
            }
            if (!$apply) {
                $results[] = $result + ['action' => 'would_bind'];
                continue;
            }
            $account = $accounts->forEnvironment($environment);
            $done = DB::transaction(function () use ($image, $account) {
                // Revalidate the exact row under lock; any concurrent change skips it.
                $locked = Image::whereKey($image->id)->lockForUpdate()->first();
                if (!$locked || $locked->trashed() || $locked->storage_credential_id !== null || $locked->storage_account_id !== null
                    || $locked->storage_url !== $image->storage_url || $locked->storage_path !== $image->storage_path
                    || $locked->storage_provider !== $image->storage_provider) {
                    return false;
                }
                // Link column only, like the credential link: no updated_at churn on the image.
                return DB::table('images')->where('id', $image->id)->whereNull('storage_account_id')
                    ->update(['storage_account_id' => $account->id]) === 1;
            });
            if ($done) $bound++;
            $results[] = $result + ['action' => $done ? 'bound' : 'skip', 'reason' => $done ? null : 'changed_concurrently'];
        }
        $this->line(json_encode(['mode' => $apply ? 'adopt_default_apply' : 'adopt_default_dry_run', 'owner_id' => $owner,
            'candidates' => count($candidates), 'bound' => $bound, 'images' => $results,
            'evidence' => 'authenticated exact-object metadata (size, type); checksum not verified',
            'provider_writes' => 0], JSON_PRETTY_PRINT));
        return 0;
    }

    /** Stored URL must point at the default store's exact object; relative/legacy URLs are unproven. */
    private function urlMismatch(Image $image, $environment, StorageAccountService $accounts): ?string
    {
        $url = parse_url((string) $image->storage_url);
        if (!$url || !isset($url['host'])) return 'unknown_host';
        if (($url['scheme'] ?? '') !== 'https' || isset($url['port']) || isset($url['user']) || isset($url['pass'])
            || isset($url['fragment'])) return 'untrusted_url';
        $path = rawurldecode($url['path'] ?? '');
        if ($image->storage_provider === 'vercel') {
            $host = $accounts->identityKey($environment) . '.public.blob.vercel-storage.com';
            if (strtolower($url['host']) !== $host) return 'host_mismatch';
            return $path === '/' . $image->storage_path ? null : 'path_mismatch';
        }
        if (strtolower($url['host']) !== $accounts->identityKey($environment)) return 'host_mismatch';
        $object = $image->storage_bucket . '/' . $image->storage_path;
        return in_array($path, ['/storage/v1/object/sign/' . $object, '/storage/v1/object/public/' . $object], true)
            ? null : 'path_mismatch';
    }
}
