<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller {
    protected $supabaseUrl;
    protected $supabaseKey;
    protected $supabaseServiceKey;

    public function __construct() {
        $this->supabaseUrl = config('services.supabase.url');
        $this->supabaseKey = config('services.supabase.key');
        $this->supabaseServiceKey = config('services.supabase.service_key');
    }

    /**
     * Display a listing of galleries.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $query = Gallery::query();

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
        $galleries = $query->with('category')->paginate($perPage);

        return response()->json($galleries);
    }

    /**
     * Store a newly created gallery in storage.
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
        ]);

        try {
            $gallery = Gallery::create([
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'storage_path' => $request->storage_path,
                'storage_bucket' => $request->storage_bucket,
                'storage_url' => $request->storage_url,
                'filename' => $request->filename,
                'mime_type' => $request->mime_type,
                'size' => $request->size,
                'user_id' => auth()->id(), // Assuming user is authenticated
            ]);

            return response()->json($gallery, 201);
        } catch (\Exception $e) {
            Log::error('Error storing gallery: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Failed to store gallery: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified gallery.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {
        $gallery = Gallery::with('category')->findOrFail($id);
        return response()->json($gallery);
    }

    /**
     * Update the specified gallery in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        $gallery = Gallery::findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        try {
            $gallery->update($request->only([
                'title',
                'description',
                'category_id'
            ]));

            return response()->json($gallery);
        } catch (\Exception $e) {
            Log::error('Error updating gallery: ' . $e->getMessage(), [
                'exception' => $e,
                'gallery_id' => $id,
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Failed to update gallery: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified gallery from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        $gallery = Gallery::findOrFail($id);

        try {
            // Delete file from Supabase Storage
            if ($gallery->storage_path && $gallery->storage_bucket) {
                Http::withHeaders([
                    'apikey' => $this->supabaseKey,
                    'Authorization' => 'Bearer ' . $this->supabaseKey,
                ])->delete("{$this->supabaseUrl}/storage/v1/object/{$gallery->storage_bucket}/{$gallery->storage_path}");
            }

            // Delete gallery record
            $gallery->delete();

            return response()->json(['message' => 'Gallery deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting gallery: ' . $e->getMessage(), [
                'exception' => $e,
                'gallery_id' => $id,
            ]);
            return response()->json(['error' => 'Failed to delete gallery: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get statistics about galleries.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats() {
        try {
            // Get total images count
            $totalImages = Gallery::count();

            // Get total storage used in bytes
            $totalStorage = Gallery::sum('size');

            // Get recent uploads (last 30 days)
            $recentUploads = Gallery::where('created_at', '>=', now()->subDays(30))->count();

            // Get file types distribution
            $fileTypes = Gallery::select(
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
                $count = Gallery::whereYear('created_at', $month->year)
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
            Log::error('Error fetching gallery stats: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Failed to fetch gallery stats: ' . $e->getMessage()], 500);
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
            $gallery = new Gallery();
            $gallery->user_id = auth()->id();
            $gallery->title = $request->title;
            $gallery->description = $request->description;
            $gallery->category_id = $request->category_id;
            $gallery->storage_path = $filename;
            $gallery->storage_bucket = 'gallery-uploads';
            $gallery->storage_url = $signedUrl; // Use the signed URL
            $gallery->filename = $file->getClientOriginalName();
            $gallery->mime_type = $file->getMimeType();
            $gallery->size = $file->getSize();
            $gallery->save();

            return response()->json($gallery, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Create a route to generate a signed URL
    public function refreshSignedUrl(Request $request, $galleryId) {
        $gallery = Gallery::findOrFail($galleryId);

        try {
            // Make a request to Supabase to generate a signed URL
            $path = $gallery->storage_path;
            
            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $this->supabaseServiceKey,
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey
            ])->post(
                $this->supabaseUrl . '/storage/v1/object/sign/gallery-uploads/' . $path,
                ['expiresIn' => 604800] // 7 days
            );

            Log::info('Generating signed URL for file', [
                'gallery_id' => $galleryId,
                'filename' => $gallery->filename,
                'storage_path' => $path,
                'status_code' => $signResponse->status(),
                'response_body' => $signResponse->body(),
            ]);
            if (!$signResponse->successful()) {
                throw new \Exception('Failed to generate signed URL: ' . $signResponse->body());
            }

            $data = $signResponse->json();

            // Update the gallery with the new signed URL
            $gallery->storage_url = $data['signedURL'];
            $gallery->save();

            return response()->json(['signedUrl' => $this->supabaseUrl . $data['signedURL']]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to refresh signed URL: ' . $e->getMessage()], 500);
        }
    }
}