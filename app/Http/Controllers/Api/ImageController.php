<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller {
    protected $supabaseUrl;
    protected $supabaseKey;
    protected $supabaseServiceKey;

    public function __construct() {
        $this->supabaseUrl = config('services.supabase.url');
        $this->supabaseKey = config('services.supabase.key');
        $this->supabaseServiceKey = config('services.supabase.service_key');
    }

    /**
     * Display a listing of images.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $query = Image::query();

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
            $query->where('category_id', $request->category_id);
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
        $images = $query->with('category')->paginate($perPage);

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
            'category_id' => 'nullable|exists:categories,id',
            'storage_path' => 'required|string',
            'storage_bucket' => 'required|string',
            'storage_url' => 'required|string',
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'size' => 'required|integer',
            'storage_provider' => 'nullable|string',
        ]);

        try {
            $image = Image::create([
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'storage_path' => $request->storage_path,
                'storage_bucket' => $request->storage_bucket,
                'storage_url' => $request->storage_url,
                'filename' => $request->filename,
                'mime_type' => $request->mime_type,
                'size' => $request->size,
                'storage_provider' => $request->storage_provider ?? 'supabase',
                'user_id' => auth()->id(),
            ]);

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
        $image = Image::with('category')->findOrFail($id);
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
        $image = Image::findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        try {
            $image->update($request->only([
                'title',
                'description',
                'category_id'
            ]));

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
        $image = Image::findOrFail($id);

        try {
            // Delete file from storage based on provider
            if ($image->isSupabaseStorage()) {
                // Delete from Supabase Storage
                if ($image->storage_path && $image->storage_bucket) {
                    Http::withHeaders([
                        'apikey' => $this->supabaseKey,
                        'Authorization' => 'Bearer ' . $this->supabaseKey,
                    ])->delete("{$this->supabaseUrl}/storage/v1/object/{$image->storage_bucket}/{$image->storage_path}");
                }
            } elseif ($image->isVercelStorage()) {
                // Delete from Vercel Blob - handled by VercelBlobController
                // We'll keep the image record deletion here
            }

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
            // Get total images count
            $totalImages = Image::count();

            // Get total storage used in bytes
            $totalStorage = Image::sum('size');

            // Get recent uploads (last 30 days)
            $recentUploads = Image::where('created_at', '>=', now()->subDays(30))->count();

            // Get file types distribution
            $fileTypes = Image::select(
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
                $count = Image::whereYear('created_at', $month->year)
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
            'category_id' => 'nullable|exists:categories,id',
        ]);

        try {
            // Get the file from the request
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Get file contents
            $fileContents = file_get_contents($file->getRealPath());

            // First, upload the file using Laravel's HTTP Client
            $uploadResponse = Http::withHeaders([
                'Content-Type' => $file->getMimeType(),
                'apikey' => $this->supabaseServiceKey,
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey
            ])->withBody(
                $fileContents, $file->getMimeType()
            )->post(
                $this->supabaseUrl . '/storage/v1/object/gallery-uploads/' . $filename
            );

            if (!$uploadResponse->successful()) {
                throw new \Exception('Failed to upload to Supabase: ' . $uploadResponse->body());
            }

            // Now generate a signed URL for the file
            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $this->supabaseServiceKey,
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey
            ])->post(
                $this->supabaseUrl . '/storage/v1/object/sign/gallery-uploads/' . $filename,
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
            $image->category_id = $request->category_id;
            $image->storage_path = $filename;
            $image->storage_bucket = 'gallery-uploads';
            $image->storage_url = $signedUrl;
            $image->storage_provider = 'supabase';
            $image->filename = $file->getClientOriginalName();
            $image->mime_type = $file->getMimeType();
            $image->size = $file->getSize();
            $image->save();

            return response()->json($image, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Create a route to generate a signed URL
    public function refreshSignedUrl(Request $request, $imageId) {
        $image = Image::findOrFail($imageId);

        try {
            // Make a request to Supabase to generate a signed URL
            $path = $image->storage_path;

            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $this->supabaseServiceKey,
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey
            ])->post(
                $this->supabaseUrl . '/storage/v1/object/sign/gallery-uploads/' . $path,
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

            return response()->json(['signedUrl' => $this->supabaseUrl . $data['signedURL']]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to refresh signed URL: ' . $e->getMessage()], 500);
        }
    }
}
