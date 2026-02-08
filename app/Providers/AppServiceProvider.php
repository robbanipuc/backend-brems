<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CloudinaryService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register CloudinaryService as singleton
        $this->app->singleton(CloudinaryService::class, function ($app) {
            return new CloudinaryService();
        });
    }

    public function boot(): void
    {
        //
    }
}