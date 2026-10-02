<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\Storage\StorageCredentialService;
use App\Services\Storage\UploadReceiptService;
use App\Services\Storage\TrustedStorageOriginPolicy;
use App\Services\Storage\StorageOperationService;
use Illuminate\Validation\Rule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VercelBlobController extends Controller
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
     * Generate a client upload token for Vercel Blob
     * This creates a signed token that allows the client to upload directly to Vercel
     * Manual implementation following Vercel's client.ts source code
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateClientToken(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string',
            'size' => 'required|integer|min:0|max:52428800', // 50MB max
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => [Rule::exists('categories', 'id')->where('user_id', auth()->id())],
            'credential_id' => 'nullable|integer|min:1',
        ]);
        $creds = $this->credentialService->resolveForUpload($request->user(), 'vercel', $request->input('credential_id'));

        try {
            $readWriteToken = $creds['token'];

            if (empty($readWriteToken)) {
                throw new \Exception('Vercel Blob read-write token is not configured');
            }

            // Commit the durable intent (exact namespaced pathname) before any token exists.
            $op = $this->operations->reserveUpload($request->user(), 'vercel', $creds, 'vercel-blob', $request->filename,
                $request->content_type, (int) $request->size, [
                    'title' => $request->title, 'description' => $request->description,
                    'category_ids' => array_values($request->category_ids ?? []), 'filename' => $request->filename,
                ], (int) config('storage_maintenance.vercel_upload_ttl_seconds', 3600));
            $pathname = $op->path;

            // Create metadata to pass back in the callback (not part of the token)
            $metadata = [
                'title' => $request->title,
                'description' => $request->description,
                'category_ids' => $request->category_ids ?? [],
                'user_id' => auth()->id(),
                'credential_id' => $creds['credential_id'],
                'content_type' => $request->content_type,
                'size' => (int) $request->size,
                'operation_id' => $op->uuid,
            ];

            // Create the token options payload (this gets signed)
            $tokenOptions = [
                'contentType' => $request->content_type, // Set the content type for proper inline display
                'allowedContentTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'maximumSizeInBytes' => 52428800, // 50MB
                'addRandomSuffix' => false, // Exact reserved pathname
                // Signed: the provider rejects replacing an existing blob (installed SDK 1.0.2 default false).
                'allowOverwrite' => false,
                'cacheControlMaxAge' => 31536000, // 1 year
                'validUntil' => $op->authorization_expires_at->getTimestamp() * 1000, // Operation authorization horizon
            ];

            // Combine pathname and options
            $payload = array_merge(['pathname' => $pathname], $tokenOptions);

            // Convert payload to JSON then base64
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $payloadBase64 = base64_encode($payloadJson);

            // Generate HMAC SHA256 signature of the base64 payload
            $signature = hash_hmac('sha256', $payloadBase64, $readWriteToken, false);

            // Extract store ID from the read-write token (format: vercel_blob_rw_STOREID_TOKEN)
            preg_match('/vercel_blob_rw_([A-Za-z0-9]+)_/', $readWriteToken, $matches);
            $storeId = $matches[1] ?? null;

            if (!$storeId) {
                throw new \Exception('Could not extract store ID from Vercel token');
            }

            // Create the client token in Vercel's format: vercel_blob_client_{storeId}_{base64(signature.payload)}
            $tokenData = base64_encode($signature . '.' . $payloadBase64);
            $clientToken = "vercel_blob_client_{$storeId}_{$tokenData}";

            $this->operations->transition($op, ['reserved'], 'authorized');
            $metadata['receipt'] = $this->receipts->issue(
                $request->user(), 'vercel', $creds, $pathname, 'vercel-blob', $metadata, $op->uuid
            );

            return response()->json([
                'clientToken' => $clientToken,
                'pathname' => $pathname,
                'metadata' => $metadata, // Pass metadata separately for the callback
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            // Exception class only: messages and trace arguments can contain the RW token.
            Log::error('Error generating Vercel client token', ['exception_class' => get_class($e)]);
            return response()->json(['error' => 'Failed to generate upload token.'], 500);
        }
    }

    /**
     * Handle the upload completion callback
     * This is called after the client successfully uploads to Vercel Blob
     * The frontend sends the blob data and metadata to save in the database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleUploadCallback(Request $request)
    {
        $request->validate([
            'blob' => 'required|array',
            'blob.url' => 'required|string|url',
            'blob.pathname' => 'required|string',
            'blob.contentType' => 'required|string',
            'blob.size' => 'required|integer|min:0|max:52428800',
            'metadata' => 'required|array',
            'metadata.receipt' => 'required|string',
            'metadata.title' => 'required|string|max:255',
            'metadata.category_ids' => 'present|array',
            'metadata.category_ids.*' => [Rule::exists('categories', 'id')->where('user_id', auth()->id())],
        ]);
        $user = $request->user();
        $receipt = $this->receipts->read($request->input('metadata.receipt'), $user, 'vercel');
        if (empty($receipt['credential_id'])) {
            // Shared default store receipt: completion must resolve the same environment account.
            $creds = $this->credentialService->environmentCredentials('vercel');
            abort_unless($creds !== null, 422, 'A saved Vercel account is required.');
        } else {
            $creds = $this->credentialService->getVercelCredentials($user, $receipt['credential_id']);
        }
        $this->receipts->assertAccount($receipt, $creds);
        $metadata = $receipt['metadata'];
        $this->receipts->matches(count($request->metadata) === count($metadata) + 1, 'metadata');
        foreach ($metadata as $field => $value) {
            $this->receipts->matches($request->input('metadata.' . $field) === $value, 'metadata.' . $field);
        }
        $blob = $request->input('blob');
        $this->receipts->matches($blob['pathname'] === $receipt['path'], 'blob.pathname');
        $this->receipts->matches($blob['contentType'] === $metadata['content_type'], 'blob.contentType');
        $this->receipts->matches((int) $blob['size'] === $metadata['size'], 'blob.size');
        $this->receipts->assertVercelUrl($blob['url'], $receipt['path'], $creds);

        $op = $this->operations->uploadForReceipt($user, $receipt['operation_id'] ?? null);
        // @vercel/blob 1.0.2 head(): authenticated GET to the API with a URL query parameter.
        // Browser completion is a hint; provider metadata decides. No locks held during HTTP.
        try {
            $response = app(TrustedStorageOriginPolicy::class)->client('https://vercel.com')->withHeaders([
                'authorization' => 'Bearer ' . $creds['token'],
                'x-api-version' => '11',
            ])->get('https://vercel.com/api/blob', ['url' => $blob['url']]);
        } catch (ConnectionException | \RuntimeException $e) {
            $this->operations->recordFailure($op, 'verification_unknown');
            abort(502, 'Unable to verify uploaded blob.');
        }
        if (!$response->successful()) {
            $this->operations->recordFailure($op, 'verification_' . $response->status());
            abort(502, 'Unable to verify uploaded blob.');
        }
        $verified = $response->json();
        if (!(is_array($verified)
            && ($verified['url'] ?? null) === $blob['url']
            && ($verified['pathname'] ?? null) === $receipt['path']
            && ($verified['contentType'] ?? null) === $metadata['content_type']
            && ($verified['size'] ?? null) === $metadata['size'])) {
            $this->operations->recordFailure($op, 'metadata_mismatch');
            abort(502, 'Provider metadata does not match upload.');
        }

        $image = $this->receipts->register($user, $receipt, function ($operation) use ($user, $receipt, $metadata, $verified) {
            $image = Image::create([
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'storage_path' => $receipt['path'],
                'storage_bucket' => $receipt['bucket'],
                'storage_url' => $verified['url'],
                'filename' => basename($receipt['path']),
                'mime_type' => $verified['contentType'],
                'size' => $verified['size'],
                'user_id' => $user->id,
                'storage_provider' => Image::STORAGE_VERCEL,
                'storage_credential_id' => $receipt['credential_id'],
            ]);
            $image->categories()->attach($metadata['category_ids']);
            $this->operations->markRegistered($operation, $image,
                ['size' => $verified['size'], 'content_type' => $verified['contentType']]);
            return $image->load(['categories', 'user']);
        });

        return response()->json(['success' => true, 'image' => $image], 201);
    }

    /**
     * Delete a file from Vercel Blob storage
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteBlob(Request $request)
    {
        $request->validate([
            'image_id' => 'nullable|integer|min:1',
            'url' => 'required_without:image_id|string|url',
            'credential_id' => 'nullable|integer|min:1',
        ]);
        $query = Image::where('user_id', auth()->id());
        $images = $request->filled('image_id')
            ? $query->whereKey($request->image_id)->get()
            : $query->where('storage_provider', 'vercel')->where('storage_url', $request->url)->limit(2)->get();
        abort_if($images->isEmpty(), 404);
        abort_unless($images->count() === 1, 422, 'Ambiguous URL; supply image_id.');
        $image = $images->first();
        abort_unless($image->isVercelStorage(), 422, 'Vercel image required.');
        if ($request->exists('url')) {
            $this->receipts->matches($request->url === $image->storage_url, 'url', 'URL does not match image.');
        }
        if ($request->exists('credential_id')) {
            $id = $request->input('credential_id');
            $this->receipts->matches($id === null ? $image->storage_credential_id === null
                : (int) $id === (int) $image->storage_credential_id, 'credential_id', 'Credential does not match image.');
        }
        // Compatibility wrapper: full durable delete (provider delete, absence proof, image row).
        // The old client's following DELETE images/{id} then returns a replayed success.
        return app(ImageController::class)->deleteDurably($image);
    }

    /**
     * List blobs from Vercel storage
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listBlobs(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:512',
        ]);
        // Saved account only: the shared default store holds every user's files and is never listed.
        $creds = $this->credentialService->getVercelCredentials($request->user());
        abort_unless(!empty($creds['credential_id']), 422, 'A saved Vercel account is required.');

        try {
            $query = ['limit' => (int) $request->input('limit', 100)];
            if ($request->filled('cursor')) $query['cursor'] = $request->input('cursor');
            // Installed @vercel/blob list() contract: fixed API host, pinned transport.
            $response = app(TrustedStorageOriginPolicy::class)->client('https://vercel.com')->withHeaders([
                'authorization' => 'Bearer ' . $creds['token'],
                'x-api-version' => '11',
            ])->get('https://vercel.com/api/blob', $query);
        } catch (ConnectionException | \RuntimeException $e) {
            Log::error('Error listing Vercel blobs', ['exception_class' => get_class($e)]);
            return response()->json(['error' => 'Failed to list files'], 502);
        }
        $data = $response->json();
        if (!$response->successful() || !is_array($data['blobs'] ?? null)) {
            return response()->json(['error' => 'Failed to list blobs'], 502);
        }
        // Return only blob metadata fields, never the raw provider body.
        $blobs = array_map(fn ($b) => array_intersect_key(is_array($b) ? $b : [],
            array_flip(['url', 'pathname', 'size', 'uploadedAt', 'contentType'])), $data['blobs']);
        return response()->json(['blobs' => array_values($blobs), 'hasMore' => ($data['hasMore'] ?? false) === true,
            'cursor' => is_string($data['cursor'] ?? null) ? $data['cursor'] : null]);
    }
}
