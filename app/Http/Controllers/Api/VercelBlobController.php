<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\Storage\StorageCredentialService;
use App\Services\Storage\UploadReceiptService;
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
     * ALTERNATIVE APPROACH: Direct server-side upload to Vercel Blob
     * Instead of client token, we upload from the server
     * This is simpler and more reliable for Laravel backends
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadToVercel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:jpeg,png,gif,webp', // 50MB max
            'title' => 'required|string',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        try {
            $file = $request->file('file');
            $creds = $this->credentialService->getVercelCredentials($request->user());

            if (empty($creds['token'])) {
                throw new \Exception('Vercel Blob read-write token is not configured');
            }

            // Generate pathname
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $cleanFilename = Str::slug($originalFilename);
            if (empty($cleanFilename)) {
                $cleanFilename = 'file';
            }
            $pathname = date('Y/m/d') . '/' . $cleanFilename . '-' . Str::random(8) . '.' . $extension;

            // Upload directly to Vercel Blob using server-side PUT
            $uploadUrl = $creds['store_url'] . "/{$pathname}";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $creds['token'],
                'x-content-type' => $file->getMimeType(),
            ])->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->put($uploadUrl);

            if (!$response->successful()) {
                Log::error('Vercel Blob upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Upload to Vercel failed: ' . $response->body());
            }

            $blob = $response->json();

            // Create image entry
            $image = Image::create([
                'title' => $request->title,
                'description' => $request->description,
                'storage_path' => $blob['pathname'] ?? $pathname,
                'storage_bucket' => 'vercel-blob',
                'storage_url' => $blob['url'],
                'filename' => basename($pathname),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'user_id' => auth()->id(),
                'storage_provider' => Image::STORAGE_VERCEL,
            ]);

            // Attach categories if provided
            if ($request->has('category_ids') && is_array($request->category_ids)) {
                $image->categories()->attach($request->category_ids);
            }

            $image->load(['categories', 'user']);

            return response()->json([
                'success' => true,
                'image' => $image,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error uploading to Vercel: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to upload to Vercel',
                'message' => $e->getMessage()
            ], 500);
        }
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
            Log::error('Error generating Vercel client token: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to generate upload token: ' . $e->getMessage()], 500);
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
            $response = Http::withHeaders([
                'authorization' => 'Bearer ' . $creds['token'],
                'x-api-version' => '11',
            ])->withoutRedirecting()->timeout(15)->get('https://vercel.com/api/blob', ['url' => $blob['url']]);
        } catch (ConnectionException $e) {
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
        try {
            $creds = $this->credentialService->getVercelCredentials($request->user());

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $creds['token'],
            ])->get($creds['store_url'] . '/list', [
                'limit' => $request->get('limit', 100),
                'cursor' => $request->get('cursor'),
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'error' => 'Failed to list blobs'
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error listing Vercel blobs: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Failed to list files'], 500);
        }
    }
}
