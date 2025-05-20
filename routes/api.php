<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GalleryApiController;
use App\Http\Controllers\Api\BlobUploadController;

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

Route::prefix('v1')->group(function () {
    
    Route::get('test', [ApiController::class, 'api'])->name('api.test');
    
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

    // Public routes
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('galleries', GalleryApiController::class);
        Route::post('galleries/upload', [GalleryApiController::class, 'upload']);
        Route::get('galleries/stats', [GalleryApiController::class, 'stats']);
        Route::post('/upload-blob', [BlobUploadController::class, 'getUploadUrl']);
        Route::apiResource('categories', CategoryController::class);
    });
});

Route::prefix('v2')->group(function () {
    // or here to access the v2 of your api
});