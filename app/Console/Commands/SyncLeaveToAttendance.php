<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveAttendanceIntegrationService;
use Carbon\Carbon;

class SyncLeaveToAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync-leave 
                            {--date= : Tanggal spesifik untuk sinkronisasi (Y-m-d)}
                            {--start-date= : Tanggal mulai untuk rentang sinkronisasi (Y-m-d)}
                            {--end-date= : Tanggal akhir untuk rentang sinkronisasi (Y-m-d)}
                            {--today : Sinkronisasi untuk hari ini saja}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi status cuti yang approved ke tabel attendance dan morning reflection attendance';

    protected $leaveService;

    public function __construct(LeaveAttendanceIntegrationService $leaveService)
    {
        parent::__construct();
        $this->leaveService = $leaveService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Memulai sinkronisasi status cuti ke attendance...');

        try {
            if ($this->option('today')) {
                // Sinkronisasi untuk hari ini
                $totalSynced = $this->leaveService->syncLeaveStatusToAttendance();
                $this->info("✅ Sinkronisasi selesai untuk hari ini. Total records: {$totalSynced}");
                
            } elseif ($this->option('date')) {
                // Sinkronisasi untuk tanggal spesifik
                $date = $this->option('date');
                
                if (!$this->isValidDate($date)) {
                    $this->error('❌ Format tanggal tidak valid. Gunakan format Y-m-d (contoh: 2025-01-29)');
                    return 1;
                }
                
                $totalSynced = $this->leaveService->syncLeaveStatusToAttendance($date);
                $this->info("✅ Sinkronisasi selesai untuk tanggal {$date}. Total records: {$totalSynced}");
                
            } elseif ($this->option('start-date') && $this->option('end-date')) {
                // Sinkronisasi untuk rentang tanggal
                $startDate = $this->option('start-date');
                $endDate = $this->option('end-date');
                
                if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                    $this->error('❌ Format tanggal tidak valid. Gunakan format Y-m-d (contoh: 2025-01-29)');
                    return 1;
                }
                
                if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
                    $this->error('❌ Tanggal mulai tidak boleh lebih besar dari tanggal akhir');
                    return 1;
                }
                
                $this->info("🔄 Sinkronisasi untuk rentang tanggal: {$startDate} sampai {$endDate}");
                
                $totalSynced = $this->leaveService->syncLeaveStatusForDateRange($startDate, $endDate);
                $this->info("✅ Sinkronisasi selesai untuk rentang tanggal. Total records: {$totalSynced}");
                
            } else {
                // Default: sinkronisasi untuk hari ini
                $totalSynced = $this->leaveService->syncLeaveStatusToAttendance();
                $this->info("✅ Sinkronisasi selesai untuk hari ini (default). Total records: {$totalSynced}");
            }

            $this->newLine();
            $this->info('📊 Sinkronisasi berhasil diselesaikan!');
            $this->info('💡 Status cuti yang approved telah diupdate ke tabel attendance dan morning reflection attendance.');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Terjadi kesalahan saat sinkronisasi:');
            $this->error($e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Validasi format tanggal
     */
    private function isValidDate($date)
    {
        try {
            Carbon::createFromFormat('Y-m-d', $date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}