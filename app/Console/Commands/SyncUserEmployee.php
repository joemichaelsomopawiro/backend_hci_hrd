<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncUserEmployee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:user-employee 
                            {--fix : Fix users with null employee_id}
                            {--list : List users without employee_id}
                            {--user-id= : Sync specific user by ID}
                            {--employee-id= : Sync specific employee by ID}
                            {--by-name : Match by name (default)}
                            {--by-email : Match by email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync users with employees table. Link user.employee_id to employees.id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('list')) {
            return $this->listUsersWithoutEmployee();
        }

        if ($this->option('user-id')) {
            return $this->syncSpecificUser($this->option('user-id'));
        }

        if ($this->option('employee-id')) {
            return $this->syncSpecificEmployee($this->option('employee-id'));
        }

        if ($this->option('fix')) {
            return $this->fixAllUsers();
        }

        $this->info('Use --help to see available options');
        $this->info('Available options:');
        $this->line('  --list              : List users without employee_id');
        $this->line('  --fix               : Fix all users with null employee_id');
        $this->line('  --user-id=ID        : Sync specific user by ID');
        $this->line('  --employee-id=ID    : Sync specific employee by ID');
        $this->line('  --by-name           : Match by name (default)');
        $this->line('  --by-email          : Match by email');

        return Command::SUCCESS;
    }

    /**
     * List users without employee_id
     */
    private function listUsersWithoutEmployee()
    {
        $users = User::whereNull('employee_id')
            ->orWhere('employee_id', 0)
            ->get(['id', 'name', 'email', 'role', 'employee_id']);

        if ($users->isEmpty()) {
            $this->info('✓ All users have employee_id linked!');
            return Command::SUCCESS;
        }

        $this->warn("Found {$users->count()} users without employee_id:");
        $this->newLine();

        $headers = ['ID', 'Name', 'Email', 'Role', 'Employee ID'];
        $rows = [];

        foreach ($users as $user) {
            // Try to find matching employee
            $employee = Employee::where('nama_lengkap', $user->name)->first();
            $matchStatus = $employee ? "✓ Found (ID: {$employee->id})" : "✗ Not found";

            $rows[] = [
                $user->id,
                $user->name,
                $user->email ?? 'N/A',
                $user->role ?? 'N/A',
                $user->employee_id ?? 'NULL',
            ];
        }

        $this->table($headers, $rows);

        // Show potential matches
        $this->newLine();
        $this->info('Potential matches:');
        foreach ($users as $user) {
            $employee = Employee::where('nama_lengkap', $user->name)->first();
            if ($employee) {
                $this->line("  User ID {$user->id} ({$user->name}) → Employee ID {$employee->id} ({$employee->nama_lengkap})");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Fix all users with null employee_id
     */
    private function fixAllUsers()
    {
        $this->info('Fixing users with null employee_id...');
        $this->newLine();

        $users = User::whereNull('employee_id')
            ->orWhere('employee_id', 0)
            ->get();

        if ($users->isEmpty()) {
            $this->info('✓ No users need fixing!');
            return Command::SUCCESS;
        }

        $fixed = 0;
        $notFound = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $employee = null;

                // Try to match by name first
                if ($this->option('by-name') || !$this->option('by-email')) {
                    $employee = Employee::where('nama_lengkap', $user->name)->first();
                }

                // Try to match by email if not found
                if (!$employee && ($this->option('by-email') || !$this->option('by-name'))) {
                    // Extract name from email (before @)
                    $emailName = explode('@', $user->email ?? '')[0] ?? '';
                    $emailName = str_replace('.', ' ', $emailName);
                    $employee = Employee::where('nama_lengkap', 'like', "%{$emailName}%")->first();
                }

                if ($employee) {
                    $user->update([
                        'employee_id' => $employee->id,
                        'role' => $user->role ?? $employee->jabatan_saat_ini
                    ]);

                    $this->info("✓ Fixed: User ID {$user->id} ({$user->name}) → Employee ID {$employee->id} ({$employee->nama_lengkap})");
                    $fixed++;

                    Log::info('User-Employee sync: Fixed user', [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->nama_lengkap
                    ]);
                } else {
                    $this->warn("✗ Not found: User ID {$user->id} ({$user->name}) - No matching employee");
                    $notFound++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error fixing User ID {$user->id}: {$e->getMessage()}");
                $errors++;

                Log::error('User-Employee sync: Error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Fixed: {$fixed}");
        $this->line("  Not found: {$notFound}");
        $this->line("  Errors: {$errors}");

        return Command::SUCCESS;
    }

    /**
     * Sync specific user by ID
     */
    private function syncSpecificUser($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return Command::FAILURE;
        }

        $this->info("Syncing User ID {$userId} ({$user->name})...");
        $this->newLine();

        $employee = null;

        // Try to match by name
        if ($this->option('by-name') || !$this->option('by-email')) {
            $employee = Employee::where('nama_lengkap', $user->name)->first();
            if ($employee) {
                $this->info("Found employee by name: ID {$employee->id} ({$employee->nama_lengkap})");
            }
        }

        // Try to match by email if not found
        if (!$employee && ($this->option('by-email') || !$this->option('by-name'))) {
            $emailName = explode('@', $user->email ?? '')[0] ?? '';
            $emailName = str_replace('.', ' ', $emailName);
            $employee = Employee::where('nama_lengkap', 'like', "%{$emailName}%")->first();
            if ($employee) {
                $this->info("Found employee by email: ID {$employee->id} ({$employee->nama_lengkap})");
            }
        }

        if (!$employee) {
            $this->error("No matching employee found for user {$user->name}");
            $this->info("Available employees with similar names:");
            $similar = Employee::where('nama_lengkap', 'like', "%{$user->name}%")->get();
            foreach ($similar as $emp) {
                $this->line("  - ID {$emp->id}: {$emp->nama_lengkap} ({$emp->jabatan_saat_ini})");
            }
            return Command::FAILURE;
        }

        // Confirm before updating
        if (!$this->confirm("Link User ID {$user->id} to Employee ID {$employee->id}?")) {
            $this->info('Cancelled.');
            return Command::SUCCESS;
        }

        try {
            $user->update([
                'employee_id' => $employee->id,
                'role' => $user->role ?? $employee->jabatan_saat_ini
            ]);

            $this->info("✓ Successfully linked User ID {$user->id} to Employee ID {$employee->id}");

            Log::info('User-Employee sync: Linked specific user', [
                'user_id' => $user->id,
                'employee_id' => $employee->id
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Sync specific employee by ID
     */
    private function syncSpecificEmployee($employeeId)
    {
        $employee = Employee::find($employeeId);

        if (!$employee) {
            $this->error("Employee with ID {$employeeId} not found!");
            return Command::FAILURE;
        }

        $this->info("Syncing Employee ID {$employeeId} ({$employee->nama_lengkap})...");
        $this->newLine();

        // Find user by name
        $user = User::where('name', $employee->nama_lengkap)
            ->whereNull('employee_id')
            ->first();

        if (!$user) {
            $user = User::where('employee_id', $employeeId)->first();
            if ($user) {
                $this->info("User already linked: User ID {$user->id} ({$user->name})");
                return Command::SUCCESS;
            }

            $this->error("No matching user found for employee {$employee->nama_lengkap}");
            $this->info("Available users with similar names:");
            $similar = User::where('name', 'like', "%{$employee->nama_lengkap}%")->get();
            foreach ($similar as $usr) {
                $this->line("  - ID {$usr->id}: {$usr->name} ({$usr->email}) - Employee ID: " . ($usr->employee_id ?? 'NULL'));
            }
            return Command::FAILURE;
        }

        // Confirm before updating
        if (!$this->confirm("Link User ID {$user->id} ({$user->name}) to Employee ID {$employee->id}?")) {
            $this->info('Cancelled.');
            return Command::SUCCESS;
        }

        try {
            $user->update([
                'employee_id' => $employee->id,
                'role' => $user->role ?? $employee->jabatan_saat_ini
            ]);

            $this->info("✓ Successfully linked User ID {$user->id} to Employee ID {$employee->id}");

            Log::info('User-Employee sync: Linked specific employee', [
                'user_id' => $user->id,
                'employee_id' => $employee->id
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}

