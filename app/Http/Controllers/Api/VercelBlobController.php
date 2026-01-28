<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VercelBlobController extends Controller
{
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
            $readWriteToken = config('services.vercel.blob_read_write_token');

            if (empty($readWriteToken)) {
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
            $uploadUrl = "https://blob.vercel-storage.com/{$pathname}";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $readWriteToken,
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
            'size' => 'required|integer|max:52428800', // 50MB max
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        try {
            // Generate a unique pathname for the file
            $originalFilename = pathinfo($request->filename, PATHINFO_FILENAME);
            $extension = pathinfo($request->filename, PATHINFO_EXTENSION);

            // Create a clean filename: slug + random suffix
            $cleanFilename = Str::slug($originalFilename);
            if (empty($cleanFilename)) {
                $cleanFilename = 'file';
            }

            // Use date-based folder structure
            $pathname = date('Y/m/d') . '/' . $cleanFilename . '-' . Str::random(8) . '.' . $extension;

            // Create metadata to pass back in the callback (not part of the token)
            $metadata = [
                'title' => $request->title,
                'description' => $request->description,
                'category_ids' => $request->category_ids ?? [],
                'user_id' => auth()->id(),
            ];

            // Create the token options payload (this gets signed)
            $tokenOptions = [
                'contentType' => $request->content_type, // Set the content type for proper inline display
                'allowedContentTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'maximumSizeInBytes' => 52428800, // 50MB
                'addRandomSuffix' => false, // We already added a random suffix
                'cacheControlMaxAge' => 31536000, // 1 year
                'validUntil' => (time() + 3600) * 1000, // Valid for 1 hour (in milliseconds)
            ];

            // Combine pathname and options
            $payload = array_merge(['pathname' => $pathname], $tokenOptions);

            // Convert payload to JSON then base64
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $payloadBase64 = base64_encode($payloadJson);

            // Get the read-write token
            $readWriteToken = config('services.vercel.blob_read_write_token');

            if (empty($readWriteToken)) {
                throw new \Exception('Vercel Blob read-write token is not configured');
            }

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

            return response()->json([
                'clientToken' => $clientToken,
                'pathname' => $pathname,
                'metadata' => $metadata, // Pass metadata separately for the callback
            ]);
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
            'metadata' => 'required|array',
            'metadata.title' => 'required|string',
        ]);

        try {
            $blob = $request->input('blob');
            $metadata = $request->input('metadata');

            // Extract content type and size from the blob data
            // Vercel returns these in the response
            $contentType = $blob['contentType'] ?? 'application/octet-stream';
            $size = $blob['size'] ?? 0;

            // Create image entry
            $image = Image::create([
                'title' => $metadata['title'],
                'description' => $metadata['description'] ?? null,
                'storage_path' => $blob['pathname'],
                'storage_bucket' => 'vercel-blob',
                'storage_url' => $blob['url'],
                'filename' => basename($blob['pathname']),
                'mime_type' => $contentType,
                'size' => $size,
                'user_id' => $metadata['user_id'] ?? auth()->id(),
                'storage_provider' => Image::STORAGE_VERCEL,
            ]);

            // Attach categories if provided
            if (!empty($metadata['category_ids']) && is_array($metadata['category_ids'])) {
                $image->categories()->attach($metadata['category_ids']);
            }

            // Load the image with relationships
            $image->load(['categories', 'user']);

            Log::info('Vercel blob uploaded successfully', [
                'image_id' => $image->id,
                'url' => $blob['url'],
                'pathname' => $blob['pathname'],
            ]);

            return response()->json([
                'success' => true,
                'image' => $image,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error handling Vercel upload callback: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to save upload information',
                'message' => $e->getMessage()
            ], 500);
        }
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
            'url' => 'required|string',
        ]);

        try {
            $token = config('services.vercel.blob_read_write_token');
            // Use the Vercel Blob API URL, not the storage URL
            // API: https://vercel.com/api/blob
            // Storage: https://*.public.blob.vercel-storage.com
            $apiUrl = config('services.vercel.blob_api_url', 'https://vercel.com/api/blob');

            Log::info('Deleting Vercel blob', ['url' => $request->url]);

            // Vercel Blob API uses POST /delete with { urls: [...] } body
            // Note: 'authorization' header must be lowercase (Vercel SDK behavior)
            $response = Http::withHeaders([
                'authorization' => 'Bearer ' . $token,  // lowercase 'a'
                'x-api-version' => '11',  // required by Vercel Blob API
                'content-type' => 'application/json',  // lowercase 'c'
            ])->post($apiUrl . '/delete', [
                'urls' => [$request->url],
            ]);

            if ($response->successful()) {
                Log::info('Vercel blob deleted successfully', ['url' => $request->url]);
                return response()->json(['message' => 'File deleted successfully']);
            } else {
                Log::error('Failed to delete Vercel blob', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $request->url,
                ]);
                return response()->json([
                    'error' => 'Failed to delete file: ' . ($response->json()['error']['message'] ?? 'Unknown error')
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error deleting Vercel blob: ' . $e->getMessage(), [
                'exception' => $e,
                'url' => $request->url ?? 'unknown',
            ]);
            return response()->json(['error' => 'Failed to delete file'], 500);
        }
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
            $token = config('services.vercel.blob_read_write_token');
            $storeUrl = config('services.vercel.blob_store_url');
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get($storeUrl . '/list', [
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