<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\StorageCredentialService;
use App\Services\Storage\UploadReceiptService;
use App\Services\Storage\StorageOperationService;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class GalleryStorageController extends Controller
{
    protected StorageCredentialService $credentialService;

    protected UploadReceiptService $receipts;

    protected StorageOperationService $operations;

    public function __construct(StorageCredentialService $credentialService, UploadReceiptService $receipts,
        StorageOperationService $operations)
    {
        $this->credentialService = $credentialService;
        $this->receipts = $receipts;
        $this->operations = $operations;
    }

    /**
     * Generate a URL for direct upload to Supabase Storage
     * This works with public buckets, no authentication required
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateUploadUrl(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string|in:image/jpeg,image/png,image/gif,image/webp',
            'size' => 'required|integer|min:0|max:52428800', // 50MB max
            'credential_id' => 'nullable|integer|min:1',
        ]);
        $creds = $this->credentialService->resolveForUpload($request->user(), 'supabase', $request->input('credential_id'));
        abort_unless(is_string($creds['service_key']) && trim($creds['service_key']) !== '',
            422, 'A Supabase service key is required to issue upload URLs.');

        $metadata = ['filename' => $request->filename, 'content_type' => $request->content_type, 'size' => (int) $request->size];
        // Commit the exact account/bucket/path intent before requesting any capability.
        $op = $this->operations->reserveUpload($request->user(), 'supabase', $creds, $creds['bucket'], $request->filename,
            $request->content_type, (int) $request->size, $metadata,
            (int) config('storage_maintenance.supabase_upload_ttl_seconds', 7200));
        $lease = $this->operations->claimLease($op);
        abort_unless($lease !== null, 409, 'This upload is already in progress.');
        $this->operations->fenced($op, $lease, ['state' => 'authorizing', 'remote_started_at' => now()]);
        // Exact-path signed upload with signed upsert=false; never the broad account key.
        $uploadUrl = $this->operations->supabaseSignedUploadUrl($op, $creds);
        if ($uploadUrl === null) {
            $this->operations->release($op, $lease, ['state' => 'authorization_failed', 'last_error_code' => 'sign_upload_failed']);
            return response()->json(['error' => 'Failed to generate upload URL.'] + $op->publicFields(), 502);
        }
        $this->operations->release($op, $lease, ['state' => 'authorized']);
        $base = rtrim($creds['url'], '/');

        return response()->json([
            'uploadUrl' => $uploadUrl,
            'method' => 'PUT',
            'headers' => ['Content-Type' => $request->content_type],
            'path' => $op->path,
            'bucket' => $op->bucket,
            'fileUrl' => "{$base}/storage/v1/object/public/{$op->bucket}/{$op->path}",
            'credential_id' => $creds['credential_id'],
            'receipt' => $this->receipts->issue($request->user(), 'supabase', $creds, $op->path, $op->bucket, $metadata, $op->uuid),
            'operation_id' => $op->uuid,
        ]);
    }

    /**
     * Check if storage bucket exists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkBucket()
    {
        // Saved account only (no legacy settings or shared-store fallback), trusted endpoint only.
        $creds = $this->credentialService->getSupabaseCredentials(auth()->user());
        abort_unless(!empty($creds['credential_id']), 422, 'A saved Supabase account is required.');
        $this->credentialService->preflight('supabase', $creds);
        $bucket = $creds['bucket'];

        try {
            $policy = app(\App\Services\Storage\TrustedStorageOriginPolicy::class);
            $origin = $policy->supabaseOrigin($creds['url']);
            $response = $policy->client($origin)->withHeaders([
                'apikey' => $creds['key'],
                'Authorization' => 'Bearer ' . $creds['key'],
            ])->get($origin . '/storage/v1/bucket/' . rawurlencode($bucket));
        } catch (\Exception $e) {
            Log::error('Error checking bucket', ['exception_class' => get_class($e)]);
            return response()->json(['error' => 'Failed to check bucket.'], 502);
        }
        $data = $response->json();
        if ($response->successful() && is_array($data)) {
            // Only bucket identity fields, never the raw provider body.
            return response()->json(['exists' => true,
                'bucket' => array_intersect_key($data, array_flip(['id', 'name', 'public']))]);
        }
        return response()->json(['exists' => false, 'error' => 'Bucket not found or not accessible.']);
    }

    /**
     * Delete a file from Supabase Storage
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFile(Request $request)
    {
        $request->validate([
            'image_id' => 'nullable|integer|min:1',
            'path' => 'required_without:image_id|string',
            'credential_id' => 'nullable|integer|min:1',
        ]);
        $query = Image::where('user_id', auth()->id());
        $images = $request->filled('image_id')
            ? $query->whereKey($request->image_id)->get()
            : $query->where('storage_provider', 'supabase')->where('storage_path', $request->path)->limit(2)->get();
        abort_if($images->isEmpty(), 404);
        abort_unless($images->count() === 1, 422, 'Ambiguous path; supply image_id.');
        $image = $images->first();
        abort_unless($image->isSupabaseStorage(), 422, 'Supabase image required.');
        if ($request->exists('path')) {
            $this->receipts->matches($request->path === $image->storage_path, 'path', 'Path does not match image.');
        }
        if ($request->exists('credential_id')) {
            $id = $request->input('credential_id');
            $this->receipts->matches($id === null ? $image->storage_credential_id === null
                : (int) $id === (int) $image->storage_credential_id, 'credential_id', 'Credential does not match image.');
        }
        abort_unless(($image->storage_credential_id || $image->storage_account_id) && $image->storage_bucket && $image->storage_path,
            422, 'A recorded Supabase account, bucket and path are required.');
        // Full durable delete: provider proof, then the image row, in one journaled intent.
        return app(ImageController::class)->deleteDurably($image);
    }
}
