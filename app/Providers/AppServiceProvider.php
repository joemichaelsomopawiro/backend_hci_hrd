<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind MusicWorkflowService
        $this->app->bind(\App\Services\MusicWorkflowService::class, function ($app) {
            return new \App\Services\MusicWorkflowService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
