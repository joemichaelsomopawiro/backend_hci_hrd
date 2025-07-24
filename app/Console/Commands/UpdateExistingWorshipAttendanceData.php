<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ManualAttendanceService;
use Illuminate\Support\Facades\Log;

class UpdateExistingWorshipAttendanceData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worship:update-existing-data {--dry-run : Tampilkan data yang akan diupdate tanpa melakukan update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update data existing worship attendance untuk set attendance_method dan attendance_source';

    protected $manualAttendanceService;

    /**
     * Execute the console command.
     */
    public function handle(ManualAttendanceService $manualAttendanceService)
    {
        $this->manualAttendanceService = $manualAttendanceService;
        
        $this->info('Memulai update data existing worship attendance...');
        
        try {
            if ($this->option('dry-run')) {
                $this->dryRun();
            } else {
                $this->performUpdate();
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Error in UpdateExistingWorshipAttendanceData command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        return 0;
    }

    /**
     * Dry run - tampilkan data yang akan diupdate
     */
    private function dryRun()
    {
        $this->info('DRY RUN - Data yang akan diupdate:');
        
        $records = \App\Models\MorningReflectionAttendance::whereNull('attendance_method')
            ->orWhereNull('attendance_source')
            ->with('employee')
            ->get();

        if ($records->isEmpty()) {
            $this->info('Tidak ada data yang perlu diupdate.');
            return;
        }

        $this->table(
            ['ID', 'Employee', 'Date', 'Status', 'Current Method', 'Current Source'],
            $records->map(function ($record) {
                return [
                    $record->id,
                    $record->employee->nama_lengkap ?? 'Unknown',
                    $record->date->format('Y-m-d'),
                    $record->status,
                    $record->attendance_method ?? 'NULL',
                    $record->attendance_source ?? 'NULL'
                ];
            })
        );

        $this->info("Total {$records->count()} record akan diupdate.");
    }

    /**
     * Lakukan update data
     */
    private function performUpdate()
    {
        $this->info('Melakukan update data...');
        
        $updatedCount = $this->manualAttendanceService->updateExistingData();
        
        $this->info("Berhasil mengupdate {$updatedCount} record.");
        
        // Tampilkan ringkasan
        $this->info('Ringkasan data setelah update:');
        
        $summary = \App\Models\MorningReflectionAttendance::selectRaw('
            attendance_method,
            attendance_source,
            COUNT(*) as total
        ')
        ->groupBy('attendance_method', 'attendance_source')
        ->get();

        $this->table(
            ['Method', 'Source', 'Total'],
            $summary->map(function ($item) {
                return [
                    $item->attendance_method ?? 'NULL',
                    $item->attendance_source ?? 'NULL',
                    $item->total
                ];
            })
        );
    }
}
