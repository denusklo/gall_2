<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VercelBlobService
{
    protected $apiUrl = 'https://blob.vercel-storage.com';
    protected $token;

    public function __construct()
    {
        $this->token = env('VERCEL_BLOB_READ_WRITE_TOKEN');
    }

    public function generatePresignedUrl($filename, $contentType, $userId = null)
    {
        try {
            $response = Http::withToken($this->token)
                ->post($this->apiUrl, [
                    'filename' => $filename,
                    'contentType' => $contentType,
                    'expiresAt' => now()->addMinutes(10)->timestamp,
                    'clientPayload' => [
                        'userId' => $userId,
                    ]
                ]);
                
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Failed to generate Vercel Blob URL', [
                'error' => $response->json(),
                'status' => $response->status(),
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('Exception when generating Vercel Blob URL', [
                'exception' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    public function deleteBlob($blobId)
    {
        try {
            $response = Http::withToken($this->token)
                ->delete("{$this->apiUrl}/{$blobId}");
                
            if ($response->successful()) {
                return true;
            }
            
            Log::error('Failed to delete Vercel Blob', [
                'blobId' => $blobId,
                'error' => $response->json(),
                'status' => $response->status(),
            ]);
            
            return false;
        } catch (\Exception $e) {
            Log::error('Exception when deleting Vercel Blob', [
                'exception' => $e->getMessage(),
                'blobId' => $blobId,
            ]);
            
            return false;
        }
    }
}