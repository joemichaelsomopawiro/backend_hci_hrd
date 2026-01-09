<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateEmployeesForUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:employees-for-users 
                            {--dry-run : Show what would be created without actually creating}
                            {--user-id= : Create employee for specific user ID}
                            {--skip-test : Skip test users (containing "Test" in name)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create employee records for users that don\'t have employee_id linked';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $skipTest = $this->option('skip-test');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get users without employee_id
        $query = User::whereNull('employee_id')
            ->orWhere('employee_id', 0);

        if ($this->option('user-id')) {
            $query->where('id', $this->option('user-id'));
        }

        if ($skipTest) {
            $query->where('name', 'not like', '%Test%');
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('✓ No users need employee creation!');
            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} users without employee_id:");
        $this->newLine();

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                // Check if employee with same name already exists
                $existingEmployee = Employee::where('nama_lengkap', $user->name)->first();
                
                if ($existingEmployee) {
                    $this->warn("⚠ Skipped: User ID {$user->id} ({$user->name}) - Employee with same name already exists (ID: {$existingEmployee->id})");
                    $skipped++;
                    continue;
                }

                // Generate unique NIK (16 digits)
                $nik = $this->generateUniqueNik();
                
                // Generate NIP from user ID
                $nip = 'EMP' . str_pad($user->id, 6, '0', STR_PAD_LEFT);

                // Prepare employee data
                $employeeData = [
                    'nama_lengkap' => $user->name,
                    'nik' => $nik,
                    'nip' => $nip,
                    'tanggal_lahir' => Carbon::now()->subYears(25)->format('Y-m-d'), // Default: 25 years old
                    'jenis_kelamin' => 'Laki-laki', // Default
                    'alamat' => 'Alamat belum diisi', // Default
                    'status_pernikahan' => 'Belum Menikah', // Default
                    'jabatan_saat_ini' => $user->role ?? 'Employee',
                    'tanggal_mulai_kerja' => Carbon::now()->subMonths(6)->format('Y-m-d'), // Default: 6 months ago
                    'tingkat_pendidikan' => 'S1', // Default
                    'gaji_pokok' => 5000000, // Default: 5 juta
                    'tunjangan' => 0,
                    'bonus' => 0,
                    'created_from' => 'auto_created_from_user',
                ];

                if ($dryRun) {
                    $this->line("Would create employee for User ID {$user->id} ({$user->name}):");
                    $this->line("  - NIK: {$nik}");
                    $this->line("  - NIP: {$nip}");
                    $this->line("  - Jabatan: {$employeeData['jabatan_saat_ini']}");
                    $this->line("  - Tanggal Lahir: {$employeeData['tanggal_lahir']}");
                    $this->line("  - Tanggal Mulai Kerja: {$employeeData['tanggal_mulai_kerja']}");
                    $this->newLine();
                    $created++;
                } else {
                    // Create employee
                    DB::beginTransaction();
                    
                    try {
                        $employee = Employee::create($employeeData);
                        
                        // Link user to employee
                        $user->update([
                            'employee_id' => $employee->id,
                            'role' => $user->role ?? $employee->jabatan_saat_ini
                        ]);

                        DB::commit();

                        $this->info("✓ Created: Employee ID {$employee->id} for User ID {$user->id} ({$user->name})");
                        $created++;

                        Log::info('Auto-created employee for user', [
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'employee_id' => $employee->id,
                            'employee_name' => $employee->nama_lengkap,
                            'jabatan' => $employee->jabatan_saat_ini
                        ]);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }
            } catch (\Exception $e) {
                $this->error("✗ Error creating employee for User ID {$user->id}: {$e->getMessage()}");
                $errors++;

                Log::error('Error creating employee for user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Created: {$created}");
        $this->line("  Skipped: {$skipped}");
        $this->line("  Errors: {$errors}");

        if ($dryRun) {
            $this->newLine();
            $this->info("💡 Run without --dry-run to actually create the employees");
        }

        return Command::SUCCESS;
    }

    /**
     * Generate unique NIK (16 digits)
     */
    private function generateUniqueNik(): string
    {
        do {
            // Format: YYMMDD + random 10 digits
            $datePart = Carbon::now()->format('ymd');
            $randomPart = str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $nik = $datePart . $randomPart;
            
            // Ensure it's exactly 16 digits
            $nik = substr($nik, 0, 16);
        } while (Employee::where('nik', $nik)->exists());

        return $nik;
    }
}

