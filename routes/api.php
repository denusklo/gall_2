<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlobController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BlobUploadController;
use App\Http\Controllers\Api\GalleryApiController;

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

    // Public routes
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('galleries/upload', [GalleryApiController::class, 'upload']);
        Route::get('galleries/stats', [GalleryApiController::class, 'stats']);
        Route::apiResource('galleries', GalleryApiController::class);
        Route::post('/upload-blob', [BlobUploadController::class, 'getUploadUrl']);
        Route::apiResource('categories', CategoryController::class);

        Route::prefix('blob')->group(function () {
            Route::post('/generate-token', [BlobController::class, 'generateToken']);
            Route::post('/handle-upload', [BlobController::class, 'handleUpload']);
            Route::post('/upload-completed', [BlobController::class, 'uploadCompleted']);
            Route::post('/upload-url', [BlobController::class, 'getUploadUrl']); // Add this new route
        });
        // Add this to your routes/api.php
        Route::get('/blob/check-config', [App\Http\Controllers\Api\BlobConfigCheckController::class, 'check']);
    });
});

Route::prefix('_2')->group(function () {
    // or here to access the v2 of your api
});