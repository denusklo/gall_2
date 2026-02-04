<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\StorageCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class GalleryStorageController extends Controller
{
    protected StorageCredentialService $credentialService;

    public function __construct(StorageCredentialService $credentialService)
    {
        $this->credentialService = $credentialService;
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
            'content_type' => 'required|string',
            'size' => 'required|integer|max:52428800', // 50MB max
        ]);

        try {
            // Get user's Supabase credentials
            $creds = $this->credentialService->getSupabaseCredentials($request->user());

            // Generate a unique file path
            $filename = Str::slug(pathinfo($request->filename, PATHINFO_FILENAME)) . '-' . Str::random(8);
            $extension = pathinfo($request->filename, PATHINFO_EXTENSION);
            $filePath = date('Y/m/d') . '/' . $filename . '.' . $extension;

            $bucket = $creds['bucket'];

            // For direct uploads to a public bucket
            $uploadUrl = "{$creds['url']}/storage/v1/object/{$bucket}/{$filePath}";

            // Generate public URL for file
            $fileUrl = "{$creds['url']}/storage/v1/object/public/{$bucket}/{$filePath}";

            return response()->json([
                'uploadUrl' => $uploadUrl,
                'method' => 'PUT',  // Use PUT for direct uploads
                'headers' => [
                    'authorization' => 'Bearer ' . $creds['key'],
                    'Content-Type' => $request->content_type,
                    'x-upsert' => 'true'  // This allows overwriting if a file exists
                ],
                'path' => $filePath,
                'bucket' => $bucket,
                'fileUrl' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating upload URL: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Failed to generate upload URL: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if storage bucket exists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkBucket()
    {
        try {
            $creds = $this->credentialService->getSupabaseCredentials(auth()->user());
            $bucket = $creds['bucket'];

            $response = Http::withHeaders([
                'apikey' => $creds['key'],
                'Authorization' => 'Bearer ' . $creds['key'],
            ])->get("{$creds['url']}/storage/v1/bucket/{$bucket}");

            if ($response->successful()) {
                return response()->json([
                    'exists' => true,
                    'bucket' => $response->json()
                ]);
            } else {
                return response()->json([
                    'exists' => false,
                    'error' => $response->json()['message'] ?? 'Bucket not found'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error checking bucket: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Failed to check bucket: ' . $e->getMessage()], 500);
        }
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
            'path' => 'required|string',
        ]);

        try {
            $creds = $this->credentialService->getSupabaseCredentials(auth()->user());
            $bucket = $creds['bucket'];

            $response = Http::withHeaders([
                'apikey' => $creds['key'],
                'Authorization' => 'Bearer ' . $creds['key'],
            ])->delete("{$creds['url']}/storage/v1/object/{$bucket}/{$request->path}");

            if ($response->successful()) {
                return response()->json(['message' => 'File deleted successfully']);
            } else {
                return response()->json([
                    'error' => 'Failed to delete file: ' . ($response->json()['message'] ?? 'Unknown error')
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error deleting file: ' . $e->getMessage(), [
                'exception' => $e,
                'path' => $request->path,
            ]);
            return response()->json(['error' => 'Failed to delete file: ' . $e->getMessage()], 500);
        }
    }
}
