<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class BlobController extends Controller
{
    /**
     * Generate a client-side upload URL and token for Vercel Blob
     */
    public function getUploadUrl(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'filename' => 'required|string',
            'contentType' => 'required|string',
        ]);
        
        // You can add authentication checks here
        // if (!auth()->check()) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }
        
        try {
            // Get your server-side token from .env
            $serverToken = env('GALLERY_BLOB_READ_WRITE_TOKEN');
            
            if (!$serverToken) {
                throw new \Exception('Blob token not configured on server');
            }
            
            // Call Vercel Blob API to get a client-side token
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $serverToken,
                'Content-Type' => 'application/json',
            ])->post('https://whtwzqcmwwlmdgiv.public.blob.vercel-storage.com/client-token', [
                'pathname' => '/' . $validated['filename'],
                'contentType' => $validated['contentType'],
                'access' => 'public',
                'addRandomSuffix' => true,
            ]);
            
            if ($response->failed()) {
                Log::error('Failed to get client token from Vercel Blob', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                
                throw new \Exception('Failed to get client token: ' . $response->body());
            }
            
            // Return the client token and blob info to the frontend
            return response()->json($response->json());
            
        } catch (\Exception $e) {
            Log::error('Error generating client token: ' . $e->getMessage());
            
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a token for Vercel Blob upload
     */
    public function generateToken(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'filename' => 'required|string',
            'contentType' => 'required|string',
        ]);
        
        // You can add authentication checks here
        // if (!auth()->check()) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }
        
        // Create a token payload with additional metadata
        // In a real app, you might want to include the user ID or other data
        $tokenPayload = json_encode([
            'userId' => auth()->id() ?? 'guest',
            'filename' => $validated['filename'],
            'timestamp' => now()->timestamp,
        ]);
        
        // Return the token information
        return response()->json([
            'tokenPayload' => $tokenPayload,
            'allowedContentTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'addRandomSuffix' => true,
        ]);
    }
    
    /**
     * Handle the Vercel Blob upload process
     */
    public function handleUpload(Request $request)
    {
        // Import the handleUpload function from Vercel Blob SDK
        // You'll need to install the vercel/blob PHP package
        // composer require vercel/blob
        
        try {
            // Directly pass the request to Vercel's handleUpload function
            // This is a mock implementation since there's no official PHP SDK yet
            
            // For now, we'll just return a successful response with the expected structure
            // The actual upload is handled by the client-side SDK
            
            // In a real implementation, you would use the Vercel API here
            
            return response()->json([
                'success' => true,
                'message' => 'Upload request processed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Blob upload error: ' . $e->getMessage());
            
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Handle the webhook callback from Vercel Blob on upload completion
     */
    public function uploadCompleted(Request $request)
    {
        try {
            $blob = $request->input('blob');
            $tokenPayload = $request->input('tokenPayload');
            
            // Decode the token payload
            $payload = json_decode($tokenPayload, true);
            
            Log::info('Blob upload completed', [
                'blob' => $blob,
                'payload' => $payload,
            ]);
            
            // Here you could update your database based on the completed upload
            // For example, associate the blob URL with a user
            // Or create a new media record
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error processing upload completion: ' . $e->getMessage());
            
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}