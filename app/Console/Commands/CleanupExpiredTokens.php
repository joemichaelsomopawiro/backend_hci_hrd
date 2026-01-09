<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class CleanupExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired personal access tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired tokens...');

        // Delete expired tokens
        $expiredCount = PersonalAccessToken::where('expires_at', '<', now())
            ->whereNotNull('expires_at')
            ->count();
        
        PersonalAccessToken::where('expires_at', '<', now())
            ->whereNotNull('expires_at')
            ->delete();

        $this->info("Deleted {$expiredCount} expired tokens");

        // Delete inactive tokens (if inactivity timeout is configured)
        $inactivityTimeout = config('sanctum.inactivity_timeout');
        if ($inactivityTimeout) {
            $threshold = now()->subSeconds($inactivityTimeout);
            $inactiveCount = PersonalAccessToken::where('last_used_at', '<', $threshold)
                ->whereNotNull('last_used_at')
                ->count();
            
            PersonalAccessToken::where('last_used_at', '<', $threshold)
                ->whereNotNull('last_used_at')
                ->delete();

            $this->info("Deleted {$inactiveCount} inactive tokens");
        }

        $this->info('Cleanup completed successfully!');
        
        return Command::SUCCESS;
    }
}

