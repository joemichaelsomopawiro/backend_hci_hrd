<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;

class SendDailyWorkflowSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily workflow summary to managers and producers';

    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending daily workflow summary...');
        
        try {
            $this->notificationService->sendDailyWorkflowSummary();
            $this->info('Daily workflow summary sent successfully!');
        } catch (\Exception $e) {
            $this->error('Error sending daily workflow summary: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

