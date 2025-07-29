<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\LeaveQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetAnnualLeaveQuotas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:reset-annual {year? : Tahun untuk reset (default: tahun berikutnya)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset jatah cuti tahunan untuk semua karyawan dengan standar default baru';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetYear = $this->argument('year') ?? (date('Y') + 1);
        
        $this->info("🚀 Memulai reset jatah cuti tahunan untuk tahun {$targetYear}...");
        
        // Standar default baru sesuai permintaan
        $defaultQuotas = [
            'annual_leave_quota' => 12,        // Cuti tahunan tetap 12 hari
            'annual_leave_used' => 0,
            'sick_leave_quota' => 3,           // Cuti sakit: 3 hari (dari 12)
            'sick_leave_used' => 0,
            'emergency_leave_quota' => 1,      // Cuti darurat: 1 hari (dari 2)
            'emergency_leave_used' => 0,
            'maternity_leave_quota' => 80,     // Cuti melahirkan: 80 hari (dari 90)
            'maternity_leave_used' => 0,
            'paternity_leave_quota' => 3,      // Cuti ayah: 3 hari (dari 7)
            'paternity_leave_used' => 0,
            'marriage_leave_quota' => 3,       // Cuti nikah: 3 hari (tetap)
            'marriage_leave_used' => 0,
            'bereavement_leave_quota' => 3,    // Cuti duka: 3 hari (tetap)
            'bereavement_leave_used' => 0,
        ];
        
        $this->info("📋 Standar default baru:");
        $this->table(
            ['Jenis Cuti', 'Jatah'],
            [
                ['Cuti Ayah', '3 Hari'],
                ['Cuti Nikah', '3 Hari'],
                ['Cuti Duka', '3 Hari'],
                ['Cuti Melahirkan', '80 Hari'],
                ['Cuti Sakit', '3 Hari'],
                ['Cuti Darurat', '1 Hari'],
                ['Cuti Tahunan', '12 Hari'],
            ]
        );
        
        try {
            DB::beginTransaction();
            
            $employees = Employee::all();
            $totalEmployees = $employees->count();
            $createdCount = 0;
            $updatedCount = 0;
            
            $this->info("👥 Total karyawan yang akan direset: {$totalEmployees}");
            
            $progressBar = $this->output->createProgressBar($totalEmployees);
            $progressBar->start();
            
            foreach ($employees as $employee) {
                // Cek apakah sudah ada quota untuk tahun target
                $existingQuota = LeaveQuota::where('employee_id', $employee->id)
                                          ->where('year', $targetYear)
                                          ->first();
                
                // Sesuaikan quota berdasarkan jenis kelamin
                $quotaData = $defaultQuotas;
                if ($employee->jenis_kelamin === 'Perempuan') {
                    $quotaData['maternity_leave_quota'] = 80;
                    $quotaData['paternity_leave_quota'] = 0;
                } else {
                    $quotaData['maternity_leave_quota'] = 0;
                    $quotaData['paternity_leave_quota'] = 3;
                }
                
                if ($existingQuota) {
                    // Update quota yang sudah ada
                    $existingQuota->update($quotaData);
                    $updatedCount++;
                } else {
                    // Buat quota baru
                    LeaveQuota::create(array_merge(
                        $quotaData,
                        [
                            'employee_id' => $employee->id,
                            'year' => $targetYear,
                        ]
                    ));
                    $createdCount++;
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
            
            DB::commit();
            
            // Log aktivitas
            Log::info("Reset jatah cuti tahunan berhasil untuk tahun {$targetYear}", [
                'total_employees' => $totalEmployees,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'target_year' => $targetYear,
                'default_quotas' => $defaultQuotas
            ]);
            
            $this->info("✅ Reset jatah cuti tahunan berhasil!");
            $this->info("📊 Ringkasan:");
            $this->info("   - Total karyawan: {$totalEmployees}");
            $this->info("   - Quota baru dibuat: {$createdCount}");
            $this->info("   - Quota diupdate: {$updatedCount}");
            $this->info("   - Tahun target: {$targetYear}");
            
            // Tampilkan contoh data
            $this->newLine();
            $this->info("📋 Contoh data quota yang dibuat:");
            $sampleQuota = LeaveQuota::where('year', $targetYear)->with('employee')->first();
            if ($sampleQuota) {
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Employee', $sampleQuota->employee->nama_lengkap],
                        ['Year', $sampleQuota->year],
                        ['Annual Leave', $sampleQuota->annual_leave_quota . ' hari'],
                        ['Sick Leave', $sampleQuota->sick_leave_quota . ' hari'],
                        ['Emergency Leave', $sampleQuota->emergency_leave_quota . ' hari'],
                        ['Maternity Leave', $sampleQuota->maternity_leave_quota . ' hari'],
                        ['Paternity Leave', $sampleQuota->paternity_leave_quota . ' hari'],
                        ['Marriage Leave', $sampleQuota->marriage_leave_quota . ' hari'],
                        ['Bereavement Leave', $sampleQuota->bereavement_leave_quota . ' hari'],
                    ]
                );
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Gagal reset jatah cuti tahunan", [
                'error' => $e->getMessage(),
                'target_year' => $targetYear
            ]);
            
            $this->error("❌ Gagal reset jatah cuti tahunan: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
} 