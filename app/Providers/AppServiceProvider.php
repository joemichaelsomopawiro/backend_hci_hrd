<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\MusicArrangement;
use App\Models\Episode;
use App\Observers\MusicArrangementObserver;
use App\Observers\EpisodeObserver;

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
        // Register Observer untuk MusicArrangement
        // Observer ini akan auto-create Creative Work ketika status arrangement berubah menjadi arrangement_approved
        MusicArrangement::observe(MusicArrangementObserver::class);

        // Observer untuk Episode
        // Observer ini akan auto-adjust deadline jika air_date berubah
        Episode::observe(EpisodeObserver::class);
    }
}
