<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CreativeWork;
use App\Models\BudgetRequest;
use App\Models\PromotionWork;
use App\Models\ProduksiWork;
use App\Models\SoundEngineerRecording;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixApprovedCreativeWorks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:approved-creative-works';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing BudgetRequest, PromotionWork, ProduksiWork, and SoundEngineerRecording for approved Creative Works';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking approved Creative Works...');
        
        // Get all approved creative works
        $approvedWorks = CreativeWork::where('status', 'approved')
            ->with(['episode.program.productionTeam'])
            ->get();
        
        $this->info("Found {$approvedWorks->count()} approved Creative Works");
        
        $created = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($approvedWorks as $creativeWork) {
            try {
                DB::beginTransaction();
                
                $this->line("Processing Creative Work ID: {$creativeWork->id} (Episode: {$creativeWork->episode_id})");
                
                // Skip BudgetRequest - table structure needs verification
                // Will handle BudgetRequest separately after verifying table structure
                $this->line("  - Skipping BudgetRequest (table structure check needed)");
                
                // Check if PromotionWork already exists
                $existingPromotionWork = PromotionWork::where('episode_id', $creativeWork->episode_id)->first();
                
                if (!$existingPromotionWork) {
                    $promosiWork = PromotionWork::create([
                        'episode_id' => $creativeWork->episode_id,
                        'created_by' => $creativeWork->reviewed_by ?? $creativeWork->created_by,
                        'work_type' => 'bts_video',
                        'title' => "BTS Video & Talent Photos - Episode {$creativeWork->episode->episode_number}",
                        'description' => "Buat video BTS dan foto talent untuk Episode {$creativeWork->episode->episode_number}",
                        'shooting_date' => $creativeWork->shooting_schedule,
                        'status' => 'planning'
                    ]);
                    
                    $this->info("  ✓ Created PromotionWork ID: {$promosiWork->id}");
                    
                    // Notify Promosi users
                    $promosiUsers = User::where('role', 'Promotion')->get();
                    foreach ($promosiUsers as $promosiUser) {
                        Notification::create([
                            'user_id' => $promosiUser->id,
                            'type' => 'promotion_work_assigned',
                            'title' => 'Pekerjaan Promosi Baru',
                            'message' => "Anda mendapat tugas promosi untuk Episode {$creativeWork->episode->episode_number}.",
                            'data' => [
                                'promotion_work_id' => $promosiWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'shooting_date' => $creativeWork->shooting_schedule?->format('Y-m-d H:i:s')
                            ]
                        ]);
                    }
                } else {
                    $this->line("  - PromotionWork already exists (ID: {$existingPromotionWork->id})");
                }
                
                // Check if ProduksiWork already exists
                $existingProduksiWork = ProduksiWork::where('episode_id', $creativeWork->episode_id)->first();
                
                if (!$existingProduksiWork) {
                    $produksiWork = ProduksiWork::create([
                        'episode_id' => $creativeWork->episode_id,
                        'creative_work_id' => $creativeWork->id,
                        'created_by' => $creativeWork->reviewed_by ?? $creativeWork->created_by,
                        'status' => 'pending'
                    ]);
                    
                    $this->info("  ✓ Created ProduksiWork ID: {$produksiWork->id}");
                    
                    // Notify Produksi users
                    $produksiUsers = User::where('role', 'Production')->get();
                    foreach ($produksiUsers as $produksiUser) {
                        Notification::create([
                            'user_id' => $produksiUser->id,
                            'type' => 'produksi_work_assigned',
                            'title' => 'Pekerjaan Produksi Baru',
                            'message' => "Anda mendapat tugas produksi untuk Episode {$creativeWork->episode->episode_number}.",
                            'data' => [
                                'produksi_work_id' => $produksiWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'shooting_date' => $creativeWork->shooting_schedule?->format('Y-m-d H:i:s')
                            ]
                        ]);
                    }
                } else {
                    $this->line("  - ProduksiWork already exists (ID: {$existingProduksiWork->id})");
                }
                
                // Check if SoundEngineerRecording already exists (only if recording_schedule exists)
                if ($creativeWork->recording_schedule) {
                    $existingRecording = SoundEngineerRecording::where('episode_id', $creativeWork->episode_id)->first();
                    
                    if (!$existingRecording) {
                        $recording = SoundEngineerRecording::create([
                            'episode_id' => $creativeWork->episode_id,
                            'recording_type' => 'vocal',
                            'title' => "Rekaman Vokal - Episode {$creativeWork->episode->episode_number}",
                            'description' => "Rekaman vokal untuk Episode {$creativeWork->episode->episode_number}",
                            'recording_date' => $creativeWork->recording_schedule,
                            'status' => 'planning'
                        ]);
                        
                        $this->info("  ✓ Created SoundEngineerRecording ID: {$recording->id}");
                        
                        // Notify Sound Engineer users
                        $soundEngineerUsers = User::where('role', 'Sound Engineer')->get();
                        foreach ($soundEngineerUsers as $seUser) {
                            Notification::create([
                                'user_id' => $seUser->id,
                                'type' => 'sound_engineer_recording_assigned',
                                'title' => 'Jadwal Rekaman Vokal Baru',
                                'message' => "Anda mendapat jadwal rekaman vokal untuk Episode {$creativeWork->episode->episode_number}.",
                                'data' => [
                                    'recording_id' => $recording->id,
                                    'episode_id' => $creativeWork->episode_id,
                                    'recording_date' => $creativeWork->recording_schedule->format('Y-m-d H:i:s')
                                ]
                            ]);
                        }
                    } else {
                        $this->line("  - SoundEngineerRecording already exists (ID: {$existingRecording->id})");
                    }
                } else {
                    $this->line("  - No recording_schedule, skipping SoundEngineerRecording");
                }
                
                DB::commit();
                $created++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $this->error("  ✗ Error processing Creative Work ID {$creativeWork->id}: " . $e->getMessage());
                Log::error('FixApprovedCreativeWorks - Error', [
                    'creative_work_id' => $creativeWork->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        $this->info("\n=== Summary ===");
        $this->info("Processed: {$approvedWorks->count()}");
        $this->info("Created: {$created}");
        $this->info("Errors: {$errors}");
        
        return 0;
    }
}

