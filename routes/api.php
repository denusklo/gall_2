<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GalleryStorageController;
use App\Http\Controllers\Api\VercelBlobController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('_1')->group(function () {

    Route::get('test', [ApiController::class, 'api'])->name('api.test');

    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        // Supabase storage routes
        Route::post('storage/generate-upload-url', [GalleryStorageController::class, 'generateUploadUrl']);
        Route::get('storage/check-bucket', [GalleryStorageController::class, 'checkBucket']);
        Route::delete('storage/delete-file', [GalleryStorageController::class, 'deleteFile']);

        // Vercel Blob storage routes
        Route::post('vercel/generate-client-token', [VercelBlobController::class, 'generateClientToken']);
        Route::post('vercel/upload-callback', [VercelBlobController::class, 'handleUploadCallback']);
        Route::delete('vercel/delete-blob', [VercelBlobController::class, 'deleteBlob']);
        Route::get('vercel/list-blobs', [VercelBlobController::class, 'listBlobs']);

        // Gallery endpoints
        Route::get('galleries', [GalleryController::class, 'index']);
        Route::post('galleries', [GalleryController::class, 'store']);
        Route::get('galleries/stats', [GalleryController::class, 'stats']);
        Route::get('galleries/{id}', [GalleryController::class, 'show']);
        Route::put('galleries/{id}', [GalleryController::class, 'update']);
        Route::delete('galleries/{id}', [GalleryController::class, 'destroy']);
        Route::post('galleries/upload', [GalleryController::class, 'upload']);
        Route::get('galleries/{id}/signed-url', [GalleryController::class, 'refreshSignedUrl']);

        Route::apiResource('categories', CategoryController::class);
    });

    Route::get('config/supabase-url', function () {
        $url = Cache::remember('supabase_url', 86400, function () {
            return config('services.supabase.url');
        });

        return response()->json(['url' => $url])
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    });
});

Route::prefix('_2')->group(function () {
    // or here to access the v2 of your api
});