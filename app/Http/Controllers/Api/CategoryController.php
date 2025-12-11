<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('user_id', Auth::id())
            ->orderBy('name', 'asc')
            ->withCount('images')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
        ]);

        // Load the images count for consistency with index()
        $category->loadCount('images');

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::where('user_id', Auth::id())
            ->withCount('images')
            ->findOrFail($id);

        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::where('user_id', Auth::id())->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Load the images count for consistency with index()
        $category->loadCount('images');

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::where('user_id', Auth::id())->findOrFail($id);

        // Check if there are images in this category
        if ($category->images()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with images. Remove or reassign images first.'
            ], 422);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}