<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class ExpireLeaveRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-expire leave requests that have passed their start date without approval';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        // Cari semua leave request yang:
        // 1. Status masih pending
        // 2. Tanggal mulai cuti sudah lewat (hari ini atau sebelumnya)
        $expiredRequests = LeaveRequest::where('overall_status', 'pending')
            ->where('start_date', '<', $today)
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('Tidak ada permohonan cuti yang perlu di-expire.');
            return 0;
        }

        $count = 0;
        foreach ($expiredRequests as $request) {
            $request->update([
                'overall_status' => 'expired',
                'rejection_reason' => 'Permohonan cuti otomatis expired karena sudah melewati tanggal mulai cuti tanpa persetujuan.'
            ]);
            $count++;
            
            $this->line("Expired: Leave request ID {$request->id} for employee {$request->employee->nama_lengkap} (Start date: {$request->start_date->format('d/m/Y')})");
        }

        $this->info("Total {$count} permohonan cuti berhasil di-expire.");
        return 0;
    }
}