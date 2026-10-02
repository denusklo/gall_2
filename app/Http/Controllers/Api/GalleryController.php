<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class GalleryController extends Controller {

    /**
     * Display a listing of galleries (albums).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $request->validate(['per_page' => 'nullable|integer|min:1|max:100']);
        $query = Gallery::where('user_id', auth()->id());

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $term = '%' . addcslashes($request->search, '\\%_') . '%';
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
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
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate the results
        $perPage = $request->get('per_page', 12);
        $galleries = $query->with(['coverImage'])
            ->withCount('images')
            ->paginate($perPage);

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
            'cover_image_id' => ['nullable', Rule::exists('images', 'id')->where('user_id', auth()->id())->whereNull('deleted_at')],
        ]);

        try {
            DB::beginTransaction();

            $gallery = Gallery::create([
                'title' => $request->title,
                'description' => $request->description,
                'cover_image_id' => $request->cover_image_id,
                'user_id' => auth()->id(),
            ]);

            // If a cover image was selected, add it to the gallery
            if ($request->cover_image_id) {
                $gallery->images()->attach($request->cover_image_id, [
                    'order' => 0,
                ]);
            }

            DB::commit();

            $gallery->load(['coverImage', 'user']);
            $gallery->loadCount('images');

            return response()->json($gallery, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error storing gallery', ['exception_class' => get_class($e)]);
            return response()->json(['error' => 'Failed to store gallery.'], 500);
        }
    }

    /**
     * Display the specified gallery with its images.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {
        $gallery = Gallery::where('user_id', auth()->id())
            ->with(['coverImage', 'user', 'images' => function ($query) {
            $query->with('categories');
        }])->findOrFail($id);

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
        $gallery = Gallery::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'cover_image_id' => ['nullable', Rule::exists('images', 'id')->where('user_id', auth()->id())->whereNull('deleted_at')],
        ]);

        try {
            $gallery->update($request->only([
                'title',
                'description',
                'cover_image_id'
            ]));

            $gallery->load(['coverImage', 'user']);

            return response()->json($gallery);
        } catch (\Exception $e) {
            Log::error('Error updating gallery', ['exception_class' => get_class($e), 'gallery_id' => $id]);
            return response()->json(['error' => 'Failed to update gallery.'], 500);
        }
    }

    /**
     * Remove the specified gallery from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        $gallery = Gallery::where('user_id', auth()->id())->findOrFail($id);

        try {
            // Delete gallery record (images will be detached due to cascade)
            $gallery->delete();

            return response()->json(['message' => 'Gallery deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting gallery', ['exception_class' => get_class($e), 'gallery_id' => $id]);
            return response()->json(['error' => 'Failed to delete gallery.'], 500);
        }
    }

    /**
     * Add an image to a gallery.
     *
     * @param Request $request
     * @param int $galleryId
     * @param int $imageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addImage(Request $request, $galleryId, $imageId) {
        $gallery = Gallery::where('user_id', auth()->id())->findOrFail($galleryId);
        $image = Image::where('user_id', auth()->id())->findOrFail($imageId);

        try {
            DB::transaction(function () use ($gallery, $imageId) {
                // Lock the gallery row so concurrent adds get distinct order values.
                Gallery::whereKey($gallery->id)->lockForUpdate()->first();
                $maxOrder = $gallery->images()->max('order') ?? -1;
                $gallery->images()->attach($imageId, [
                    'order' => $maxOrder + 1,
                ]);
            });

            return response()->json([
                'message' => 'Image added to gallery successfully',
                'gallery' => $gallery->load(['images', 'coverImage']),
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['error' => 'Image is already in this gallery'], 409);
            }

            Log::error('Error adding image to gallery', ['exception_class' => get_class($e), 'gallery_id' => $galleryId,
                'image_id' => $imageId]);
            return response()->json(['error' => 'Failed to add image to gallery.'], 500);
        } catch (\Exception $e) {
            Log::error('Error adding image to gallery', ['exception_class' => get_class($e), 'gallery_id' => $galleryId,
                'image_id' => $imageId]);
            return response()->json(['error' => 'Failed to add image to gallery.'], 500);
        }
    }

    /**
     * Remove an image from a gallery.
     *
     * @param int $galleryId
     * @param int $imageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeImage($galleryId, $imageId) {
        $gallery = Gallery::where('user_id', auth()->id())->findOrFail($galleryId);
        Image::withTrashed()->where('user_id', auth()->id())->findOrFail($imageId);

        try {
            $gallery->images()->detach($imageId);

            return response()->json([
                'message' => 'Image removed from gallery successfully',
                'gallery' => $gallery->load(['images', 'coverImage']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing image from gallery', ['exception_class' => get_class($e), 'gallery_id' => $galleryId,
                'image_id' => $imageId]);
            return response()->json(['error' => 'Failed to remove image from gallery.'], 500);
        }
    }

    /**
     * Set the cover image for a gallery.
     *
     * @param Request $request
     * @param int $galleryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function setCoverImage(Request $request, $galleryId) {
        $gallery = Gallery::where('user_id', auth()->id())->findOrFail($galleryId);

        $request->validate([
            'image_id' => ['required', Rule::exists('images', 'id')->where('user_id', auth()->id())->whereNull('deleted_at')],
        ]);

        try {
            $gallery->update([
                'cover_image_id' => $request->image_id,
            ]);

            return response()->json([
                'message' => 'Cover image set successfully',
                'gallery' => $gallery->load(['coverImage', 'images']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error setting cover image', ['exception_class' => get_class($e), 'gallery_id' => $galleryId,
                'image_id' => $request->image_id]);
            return response()->json(['error' => 'Failed to set cover image.'], 500);
        }
    }

    /**
     * Update the order of images in a gallery.
     *
     * @param Request $request
     * @param int $galleryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateImageOrder(Request $request, $galleryId) {
        $gallery = Gallery::where('user_id', auth()->id())->findOrFail($galleryId);

        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => [Rule::exists('images', 'id')->where('user_id', auth()->id())->whereNull('deleted_at')],
        ]);

        try {
            DB::beginTransaction();

            // Update the order for each image
            foreach ($request->image_ids as $index => $imageId) {
                $gallery->images()->updateExistingPivot($imageId, [
                    'order' => $index,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Image order updated successfully',
                'gallery' => $gallery->load(['images', 'coverImage']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating image order', ['exception_class' => get_class($e), 'gallery_id' => $galleryId]);
            return response()->json(['error' => 'Failed to update image order.'], 500);
        }
    }
}
