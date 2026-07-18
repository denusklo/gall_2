<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Category;
use App\Services\Storage\StorageCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller {
    protected StorageCredentialService $credentialService;

    public function __construct(StorageCredentialService $credentialService) {
        $this->credentialService = $credentialService;
    }

    /**
     * Display a listing of images.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $query = Image::where('user_id', auth()->id());

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('filename', 'like', '%' . $request->search . '%');
            });
        }

        // Apply file type filter
        if ($request->has('file_type') && !empty($request->file_type)) {
            $query->where('mime_type', 'like', $request->file_type . '%');
        }

        // Apply category filter
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'newest');
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'name_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'size_asc':
                $query->orderBy('size', 'asc');
                break;
            case 'size_desc':
                $query->orderBy('size', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate the results
        $perPage = $request->get('per_page', 12);
        $images = $query->with(['categories', 'galleries'])->paginate($perPage);

        return response()->json($images);
    }

    /**
     * Store a newly created image in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'storage_path' => 'required|string',
            'storage_bucket' => 'required|string',
            'storage_url' => 'required|string',
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'size' => 'required|integer',
            'storage_provider' => 'nullable|string',
            'credential_id' => 'nullable|integer',
        ]);

        try {
            $image = Image::create([
                'title' => $request->title,
                'description' => $request->description,
                'storage_path' => $request->storage_path,
                'storage_bucket' => $request->storage_bucket,
                'storage_url' => $request->storage_url,
                'filename' => $request->filename,
                'mime_type' => $request->mime_type,
                'size' => $request->size,
                'storage_provider' => $request->storage_provider ?? 'supabase',
                'storage_credential_id' => $request->input('credential_id'),
                'user_id' => auth()->id(),
            ]);

            // Attach categories if provided
            if ($request->has('category_ids') && is_array($request->category_ids)) {
                $image->categories()->attach($request->category_ids);
            }

            // Load categories relationship for response
            $image->load('categories');

            return response()->json($image, 201);
        } catch (\Exception $e) {
            Log::error('Error storing image: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Failed to store image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified image.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {
        $image = Image::with(['categories', 'galleries'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        return response()->json($image);
    }

    /**
     * Update the specified image in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        $image = Image::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        try {
            $image->update($request->only([
                'title',
                'description'
            ]));

            // Sync categories if provided
            if ($request->has('category_ids')) {
                if (is_array($request->category_ids)) {
                    $image->categories()->sync($request->category_ids);
                } else {
                    $image->categories()->sync([]);
                }
            }

            // Load relationships for response
            $image->load(['categories', 'galleries']);

            return response()->json($image);
        } catch (\Exception $e) {
            Log::error('Error updating image: ' . $e->getMessage(), [
                'exception' => $e,
                'image_id' => $id,
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Failed to update image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified image from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        $image = Image::where('user_id', auth()->id())->findOrFail($id);

        try {
            // Delete file from storage based on provider
            if ($image->isSupabaseStorage()) {
                // Delete from Supabase Storage using user's credentials
                if ($image->storage_path && $image->storage_bucket) {
                    $creds = $this->supabaseCredsForImage($image);

                    Http::withHeaders([
                        'apikey' => $creds['key'],
                        'Authorization' => 'Bearer ' . $creds['key'],
                    ])->delete("{$creds['url']}/storage/v1/object/{$image->storage_bucket}/{$image->storage_path}");
                }
            }
            // Note: Vercel deletion is handled by the frontend via /apiv/_1/vercel/delete-blob
            // before calling this endpoint, so we don't delete from Vercel here.

            // Delete image record
            $image->delete();

            return response()->json(['message' => 'Image deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting image: ' . $e->getMessage(), [
                'exception' => $e,
                'image_id' => $id,
            ]);
            return response()->json(['error' => 'Failed to delete image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get statistics about images.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats() {
        try {
            $userId = auth()->id();

            // Get total images count
            $totalImages = Image::where('user_id', $userId)->count();

            // Get total storage used in bytes
            $totalStorage = Image::where('user_id', $userId)->sum('size');

            // Get recent uploads (last 30 days)
            $recentUploads = Image::where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(30))->count();

            // Get file types distribution
            $fileTypes = Image::where('user_id', $userId)
                ->select(
                    DB::raw("SUBSTRING_INDEX(mime_type, '/', 1) as type"),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('type')
                ->get()
                ->map(function ($item) {
                    return [
                        'type' => $item->type,
                        'count' => $item->count,
                    ];
                });

            // Get timeline of uploads (last 12 months)
            $timeline = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $count = Image::where('user_id', $userId)
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count();

                $timeline[] = [
                    'month' => $month->format('M Y'),
                    'count' => $count,
                ];
            }

            return response()->json([
                'totalImages' => $totalImages,
                'totalStorage' => $totalStorage,
                'recentUploads' => $recentUploads,
                'fileTypes' => $fileTypes,
                'timeline' => $timeline,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching image stats: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Failed to fetch image stats: ' . $e->getMessage()], 500);
        }
    }

    public function upload(Request $request) {
        // Validate the request
        $request->validate([
            'file' => 'required|file',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'credential_id' => 'nullable|integer',
        ]);

        try {
            // Get the file from the request
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Get user's Supabase credentials (chosen credential, or default)
            $creds = $this->credentialService->getSupabaseCredentials(auth()->user(), $request->input('credential_id'));

            // Get file contents
            $fileContents = file_get_contents($file->getRealPath());

            // First, upload the file using Laravel's HTTP Client
            $uploadResponse = Http::withHeaders([
                'Content-Type' => $file->getMimeType(),
                'apikey' => $creds['service_key'],
                'Authorization' => 'Bearer ' . $creds['service_key']
            ])->withBody(
                $fileContents, $file->getMimeType()
            )->post(
                $creds['url'] . '/storage/v1/object/' . $creds['bucket'] . '/' . $filename
            );

            if (!$uploadResponse->successful()) {
                throw new \Exception('Failed to upload to Supabase: ' . $uploadResponse->body());
            }

            // Now generate a signed URL for the file
            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $creds['service_key'],
                'Authorization' => 'Bearer ' . $creds['service_key']
            ])->post(
                $creds['url'] . '/storage/v1/object/sign/' . $creds['bucket'] . '/' . $filename,
                ['expiresIn' => 604800] // 7 days
            );

            if (!$signResponse->successful()) {
                throw new \Exception('Failed to generate signed URL: ' . $signResponse->body());
            }

            $signData = $signResponse->json();
            $signedUrl = $signData['signedURL'];

            // Save to database
            $image = new Image();
            $image->user_id = auth()->id();
            $image->title = $request->title;
            $image->description = $request->description;
            $image->storage_path = $filename;
            $image->storage_bucket = $creds['bucket'];
            $image->storage_url = $signedUrl;
            $image->storage_provider = 'supabase';
            $image->storage_credential_id = $request->input('credential_id');
            $image->filename = $file->getClientOriginalName();
            $image->mime_type = $file->getMimeType();
            $image->size = $file->getSize();
            $image->save();

            // Attach categories if provided
            if ($request->has('category_ids') && is_array($request->category_ids)) {
                $image->categories()->attach($request->category_ids);
            }

            // Load categories relationship for response
            $image->load('categories');

            return response()->json($image, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve the Supabase credentials an image was uploaded with,
     * falling back to the user's default if that credential is gone.
     */
    private function supabaseCredsForImage(Image $image): array
    {
        try {
            return $this->credentialService->getSupabaseCredentials(auth()->user(), $image->storage_credential_id);
        } catch (\Exception $e) {
            return $this->credentialService->getSupabaseCredentials(auth()->user());
        }
    }

    // Create a route to generate a signed URL
    public function refreshSignedUrl(Request $request, $imageId) {
        $image = Image::findOrFail($imageId);

        try {
            // Use the credential this image was uploaded with (falls back to default)
            $creds = $this->supabaseCredsForImage($image);

            // Make a request to Supabase to generate a signed URL
            $path = $image->storage_path;
            $bucket = $image->storage_bucket ?? $creds['bucket'];

            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $creds['service_key'],
                'Authorization' => 'Bearer ' . $creds['service_key']
            ])->post(
                $creds['url'] . '/storage/v1/object/sign/' . $bucket . '/' . $path,
                ['expiresIn' => 604800] // 7 days
            );

            Log::info('Generating signed URL for file', [
                'image_id' => $imageId,
                'filename' => $image->filename,
                'storage_path' => $path,
                'status_code' => $signResponse->status(),
                'response_body' => $signResponse->body(),
            ]);
            if (!$signResponse->successful()) {
                throw new \Exception('Failed to generate signed URL: ' . $signResponse->body());
            }

            $data = $signResponse->json();

            // Update the image with the new signed URL
            $image->storage_url = $data['signedURL'];
            $image->save();

            return response()->json(['signedUrl' => $creds['url'] . $data['signedURL']]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to refresh signed URL: ' . $e->getMessage()], 500);
        }
    }
}
