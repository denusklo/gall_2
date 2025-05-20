<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class GalleryStorageController extends Controller
{
    protected $supabaseUrl;
    protected $supabaseKey;
    protected $storageBucket;

    public function __construct()
    {
        $this->supabaseUrl = config('services.supabase.url');
        $this->supabaseKey = config('services.supabase.key');
        $this->storageBucket = config('services.supabase.storage_bucket', 'gallery-uploads');
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
            // Generate a unique file path
            $filename = Str::slug(pathinfo($request->filename, PATHINFO_FILENAME)) . '-' . Str::random(8);
            $extension = pathinfo($request->filename, PATHINFO_EXTENSION);
            $filePath = date('Y/m/d') . '/' . $filename . '.' . $extension;

            // For direct uploads to a public bucket
            $uploadUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->storageBucket}/{$filePath}";
            
            // Generate the public URL for the file
            $fileUrl = "{$this->supabaseUrl}/storage/v1/object/public/{$this->storageBucket}/{$filePath}";

            return response()->json([
                'uploadUrl' => $uploadUrl,
                'method' => 'PUT',  // Use PUT for direct uploads
                'headers' => [
                    'authorization' => 'Bearer ' . $this->supabaseKey,
                    'Content-Type' => $request->content_type,
                    'x-upsert' => 'true'  // This allows overwriting if a file exists
                ],
                'path' => $filePath,
                'bucket' => $this->storageBucket,
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
     * Check if the storage bucket exists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkBucket()
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->get("{$this->supabaseUrl}/storage/v1/bucket/{$this->storageBucket}");

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
            $response = Http::withHeaders([
                'apikey' => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->delete("{$this->supabaseUrl}/storage/v1/object/{$this->storageBucket}/{$request->path}");

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