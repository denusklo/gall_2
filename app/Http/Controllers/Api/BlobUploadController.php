<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VercelBlobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlobUploadController extends Controller
{
    protected $blobService;

    public function __construct(VercelBlobService $blobService)
    {
        $this->blobService = $blobService;
    }

    public function getUploadUrl(Request $request)
    {
        // Validate that a file is being requested for upload
        $request->validate([
            'filename' => 'required|string',
            'contentType' => 'required|string',
        ]);

        $result = $this->blobService->generatePresignedUrl(
            $request->filename,
            $request->contentType,
            Auth::id()
        );
        
        if (!$result) {
            return response()->json([
                'message' => 'Failed to get upload URL from Vercel Blob',
            ], 500);
        }
        
        return response()->json($result);
    }
}