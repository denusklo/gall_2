<?php

namespace App\Providers;

use App\Services\VercelBlobService;
use Illuminate\Support\ServiceProvider;

class VercelBlobServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(VercelBlobService::class, function ($app) {
            return new VercelBlobService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}