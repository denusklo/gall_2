<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BlobConfigCheckController extends Controller
{
    /**
     * Check if Vercel Blob is configured correctly
     */
    public function check()
    {
        $token = env('GALLERY_BLOB_READ_WRITE_TOKEN');
        
        $result = [
            'token_exists' => !empty($token),
            'token_format' => !empty($token) && strpos($token, 'vercel_blob_rw_') === 0 ? 'valid' : 'invalid',
            'env_loaded' => count($_ENV) > 0, // Check if .env is being loaded at all
            'php_version' => PHP_VERSION,
        ];
        
        // If token exists, test a simple API call
        if (!empty($token)) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])->get('https://blob.vercel-storage.com/usage');
                
                $result['api_test'] = [
                    'status' => $response->status(),
                    'success' => $response->successful(),
                    'message' => $response->successful() ? 'API connection successful' : 'API connection failed',
                ];
            } catch (\Exception $e) {
                $result['api_test'] = [
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Exception: ' . $e->getMessage(),
                ];
            }
        }
        
        return response()->json($result);
    }
}