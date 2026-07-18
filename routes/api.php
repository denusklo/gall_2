<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GalleryStorageController;
use App\Http\Controllers\Api\VercelBlobController;
use App\Http\Controllers\Api\FcmController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\Api\StorageCredentialController;

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
        Route::post('vercel/delete-blob', [VercelBlobController::class, 'deleteBlob']);
        Route::get('vercel/list-blobs', [VercelBlobController::class, 'listBlobs']);

        // Image endpoints (formerly galleries - individual images)
        Route::prefix('images')->group(function() {
            Route::get('/', [ImageController::class, 'index']);
            Route::post('/', [ImageController::class, 'store']);
            Route::get('/stats', [ImageController::class, 'stats']);
            Route::get('/{id}', [ImageController::class, 'show']);
            Route::put('/{id}', [ImageController::class, 'update']);
            Route::delete('/{id}', [ImageController::class, 'destroy']);
            Route::post('/upload', [ImageController::class, 'upload']);
            Route::get('/{id}/signed-url', [ImageController::class, 'refreshSignedUrl']);
        });

        // Gallery endpoints (new - for albums/collections)
        Route::prefix('galleries')->group(function() {
            Route::get('/', [GalleryController::class, 'index']);
            Route::post('/', [GalleryController::class, 'store']);
            Route::get('/{id}', [GalleryController::class, 'show']);
            Route::put('/{id}', [GalleryController::class, 'update']);
            Route::delete('/{id}', [GalleryController::class, 'destroy']);
            Route::post('/{galleryId}/images/{imageId}', [GalleryController::class, 'addImage']);
            Route::delete('/{galleryId}/images/{imageId}', [GalleryController::class, 'removeImage']);
            Route::put('/{galleryId}/cover', [GalleryController::class, 'setCoverImage']);
            Route::put('/{galleryId}/images/reorder', [GalleryController::class, 'updateImageOrder']);
        });

        Route::apiResource('categories', CategoryController::class);

        // Test auth endpoint
        Route::get('/test-auth', function(Request $request) {
            return response()->json([
                'authenticated' => true,
                'user' => $request->user(),
                'firebase_uid' => $request->user()->firebase_uid ?? null
            ]);
        });

        // FCM Notification routes
        Route::prefix('fcm')->group(function() {
            Route::post('/token', [FcmController::class, 'storeToken']);
            Route::delete('/token', [FcmController::class, 'removeToken']);
            Route::get('/tokens', [FcmController::class, 'getTokens']);
            Route::post('/test', [FcmController::class, 'testNotification']);
            Route::post('/test-user', [FcmController::class, 'sendTestToUser']);
        });

        // Notifications routes
        Route::prefix('notifications')->group(function() {
            Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
            Route::put('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
            Route::put('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
        });

        // Storage Settings routes
        Route::prefix('storage-settings')->group(function() {
            Route::get('/', [UserSettingsController::class, 'index']);
            Route::post('/', [UserSettingsController::class, 'store']);
            Route::put('/', [UserSettingsController::class, 'update']);
            Route::delete('/{provider}', [UserSettingsController::class, 'deleteProvider']);

            // Testing endpoints
            Route::post('/test/supabase', [UserSettingsController::class, 'testSupabase']);
            Route::post('/test/vercel', [UserSettingsController::class, 'testVercel']);
        });

        // Storage Credentials routes (multi-credential support)
        Route::prefix('storage-credentials')->group(function() {
            Route::get('/', [StorageCredentialController::class, 'index']);
            Route::post('/', [StorageCredentialController::class, 'store']);
            Route::get('/{id}', [StorageCredentialController::class, 'show']);
            Route::put('/{id}', [StorageCredentialController::class, 'update']);
            Route::delete('/{id}', [StorageCredentialController::class, 'destroy']);
            Route::post('/{id}/default', [StorageCredentialController::class, 'setDefault']);
            Route::post('/{id}/test', [StorageCredentialController::class, 'testCredential']);

            // Testing endpoints (test raw values before saving, e.g. Add modal)
            Route::post('/test/supabase', [StorageCredentialController::class, 'testSupabase']);
            Route::post('/test/vercel', [StorageCredentialController::class, 'testVercel']);
        });
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