<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AttendanceMachine;
use App\Services\AttendanceMachineService;
use App\Services\AttendanceProcessingService;
use Illuminate\Support\Facades\Log;

class SyncAttendanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync 
                            {--machine-ip= : IP address mesin yang akan disync}
                            {--process : Proses logs setelah sync}
                            {--force : Force sync meskipun ada error}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync attendance data dari mesin Solution X304';

    protected $machineService;
    protected $processingService;

    public function __construct(
        AttendanceMachineService $machineService,
        AttendanceProcessingService $processingService
    ) {
        parent::__construct();
        $this->machineService = $machineService;
        $this->processingService = $processingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $machineIp = $this->option('machine-ip') ?: env('ATTENDANCE_MACHINE_IP', '10.10.10.85');
        $shouldProcess = $this->option('process');
        $force = $this->option('force');

        $this->info("🚀 Memulai sync attendance dari mesin {$machineIp}...");

        try {
            // Find machine
            $machine = AttendanceMachine::where('ip_address', $machineIp)->first();
            
            if (!$machine) {
                $this->error("❌ Mesin dengan IP {$machineIp} tidak ditemukan!");
                return Command::FAILURE;
            }

            $this->info("📡 Mesin ditemukan: {$machine->name}");

            // Test connection first
            $this->line("🔍 Testing koneksi ke mesin...");
            $connectionTest = $this->machineService->testConnection($machine);
            
            if (!$connectionTest['success'] && !$force) {
                $this->error("❌ Koneksi gagal: {$connectionTest['message']}");
                $this->line("💡 Gunakan --force untuk tetap melanjutkan");
                return Command::FAILURE;
            }

            if ($connectionTest['success']) {
                $this->info("✅ Koneksi berhasil!");
            } else {
                $this->warn("⚠️  Koneksi gagal, tapi melanjutkan karena --force");
            }

            // Pull data from machine
            $this->line("📥 Menarik data attendance dari mesin...");
            $pullResult = $this->machineService->pullAttendanceData($machine);

            if (!$pullResult['success']) {
                $this->error("❌ Gagal menarik data: {$pullResult['message']}");
                return Command::FAILURE;
            }

            $this->info("✅ {$pullResult['message']}");
            
            // Log detail jika ada data
            if (isset($pullResult['data']) && count($pullResult['data']) > 0) {
                $this->table(
                    ['PIN', 'DateTime', 'Verified Method', 'Status'],
                    collect($pullResult['data'])->take(5)->map(function ($item) {
                        return [
                            $item['pin'],
                            $item['datetime'],
                            $this->getVerifiedMethodLabel($item['verified']),
                            $item['status']
                        ];
                    })->toArray()
                );
                
                if (count($pullResult['data']) > 5) {
                    $this->line("... dan " . (count($pullResult['data']) - 5) . " data lainnya");
                }
            }

            // Process logs if requested
            if ($shouldProcess) {
                $this->line("⚙️  Memproses attendance logs...");
                $processResult = $this->processingService->processUnprocessedLogs();
                
                if ($processResult['success']) {
                    $this->info("✅ {$processResult['message']}");
                } else {
                    $this->warn("⚠️  Proses partial: {$processResult['message']}");
                }

                // Generate absent records
                $this->line("📝 Generate record absent untuk hari ini...");
                $absentResult = $this->processingService->generateAbsentAttendance(now()->format('Y-m-d'));
                $this->info("✅ {$absentResult['message']}");
            }

            // Summary
            $this->newLine();
            $this->info("🎉 Sync attendance selesai!");
            
            if ($shouldProcess) {
                $this->line("📊 Summary:");
                $summary = $this->processingService->getAttendanceSummary(now()->format('Y-m-d'));
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Users', $summary['total_users']],
                        ['Hadir Tepat Waktu', $summary['present_ontime']],
                        ['Hadir Terlambat', $summary['present_late']],
                        ['Tidak Hadir', $summary['absent']],
                        ['Cuti/Izin', $summary['on_leave'] + $summary['sick_leave'] + $summary['permission']],
                        ['Tingkat Kehadiran', $summary['attendance_rate'] . '%']
                    ]
                );
            }

            Log::info('Attendance sync command completed successfully', [
                'machine_ip' => $machineIp,
                'processed' => $shouldProcess,
                'pull_result' => $pullResult
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("💥 Error: {$e->getMessage()}");
            Log::error('Attendance sync command failed', [
                'machine_ip' => $machineIp,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Get verified method label
     */
    private function getVerifiedMethodLabel($code): string
    {
        switch ((int)$code) {
            case 1:
                return 'Password';
            case 4:
                return 'Card';
            case 15:
                return 'Fingerprint';
            case 11:
                return 'Face';
            default:
                return 'Unknown';
        }
    }
} 