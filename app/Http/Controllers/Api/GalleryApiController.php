<?php

namespace App\Http\Controllers\Api;

use App\Models\Gallery;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GalleryApiController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 12); // Default 12 items per page
        $search = $request->query('search');
        $fileType = $request->query('file_type');
        $sortBy = $request->query('sort_by', 'newest'); // Default sort by newest
        $categoryId = $request->query('category_id');
        
        $query = Gallery::where('user_id', Auth::id());
        
        // Apply category filter
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        
        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('filename', 'like', "%{$search}%");
            });
        }
        
        // Apply file type filter
        if ($fileType) {
            $query->where('mime_type', $fileType);
        }
        
        // Apply sorting
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'size_asc':
                $query->orderBy('size', 'asc');
                break;
            case 'size_desc':
                $query->orderBy('size', 'desc');
                break;
            default: // 'newest'
                $query->orderBy('created_at', 'desc');
                break;
        }
        
        // Load category relationship
        $query->with('category');
        
        $galleries = $query->paginate($perPage);
        
        return response()->json($galleries);
    }

    // app/Http/Controllers/API/GalleryApiController.php - Update the store and update methods

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'blob_url' => 'required|url',
            'blob_id' => 'required|string',
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'size' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify category belongs to user if provided
        if ($request->category_id) {
            $categoryCount = Category::where('id', $request->category_id)
                ->where('user_id', Auth::id())
                ->count();
                
            if ($categoryCount === 0) {
                return response()->json([
                    'errors' => ['category_id' => ['The selected category is invalid.']]
                ], 422);
            }
        }

        $gallery = Gallery::create([
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'blob_url' => $request->blob_url,
            'blob_id' => $request->blob_id,
            'filename' => $request->filename,
            'mime_type' => $request->mime_type,
            'size' => $request->size,
        ]);

        return response()->json($gallery, 201);
    }

    public function update(Request $request, $id)
    {
        $gallery = Gallery::where('user_id', Auth::id())->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify category belongs to user if provided
        if ($request->has('category_id') && $request->category_id) {
            $categoryCount = Category::where('id', $request->category_id)
                ->where('user_id', Auth::id())
                ->count();
                
            if ($categoryCount === 0) {
                return response()->json([
                    'errors' => ['category_id' => ['The selected category is invalid.']]
                ], 422);
            }
        }

        $gallery->update($request->only(['title', 'description', 'category_id']));
        
        return response()->json($gallery);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        $gallery = Gallery::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($gallery);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $gallery = Gallery::where('user_id', Auth::id())->findOrFail($id);

        // Get the blob ID before deletion
        $blobId = $gallery->blob_id;

        // Delete from database first
        $gallery->delete();

        // Now delete from Vercel Blob
        $blobService = app(VercelBlobService::class);
        $blobService->deleteBlob($blobId);

        return response()->json(null, 204);
    }

    public function upload(Request $request) {
        // This is just a placeholder - the actual file upload will be handled on the frontend
        // with Vercel Blob and we'll just store the metadata here
        return response()->json(['message' => 'Upload endpoint is ready, but direct upload is handled by Vercel Blob on the frontend']);
    }

    // app/Http/Controllers/API/GalleryApiController.php - Add a new stats method
    public function stats() {
        $userId = Auth::id();
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();

        // Get total images
        $totalImages = Gallery::where('user_id', $userId)->count();

        // Get total storage used
        $totalStorage = Gallery::where('user_id', $userId)->sum('size');

        // Get uploads this month
        $recentUploads = Gallery::where('user_id', $userId)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Get file type breakdown
        $fileTypes = Gallery::where('user_id', $userId)
            ->select('mime_type', \DB::raw('count(*) as count'))
            ->groupBy('mime_type')
            ->orderBy('count', 'desc')
            ->get();

        // Get upload timeline (last 6 months)
        $timeline = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();

            $count = Gallery::where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $timeline[] = [
                'month' => $month->format('M Y'),
                'count' => $count
            ];
        }

        return response()->json([
            'totalImages' => $totalImages,
            'totalStorage' => $totalStorage,
            'recentUploads' => $recentUploads,
            'fileTypes' => $fileTypes,
            'timeline' => $timeline
        ]);
    }
}
