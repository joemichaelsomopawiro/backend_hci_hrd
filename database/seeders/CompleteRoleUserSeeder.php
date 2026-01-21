<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CompleteRoleUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Define all desired roles from user request/screenshots
        $targetRoles = [
            'Creative',
            'Distribution Manager',
            'Editor',
            'Employee',
            'Finance',
            'GA',
            'General Affairs',
            'Graphic Design',
            'HR',
            'Hopeline Care',
            'Music Arranger',
            'Office Assistant',
            'President Director',
            'Producer',
            'Production',
            'Program Manager',
            'Promotion',
            'Social Media',
            'Sound Engineer',
            'VP President',
            'backend',
            'senior developer',
            'Broadcasting',
            'Art & Set Properti',
            'Editor Promotion',
            'Quality Control'
        ];

        // 2. Get Valid Enums from DB
        $userRoleEnum = $this->getEnumValues('users', 'role');
        $employeeJabatanEnum = $this->getEnumValues('employees', 'jabatan_saat_ini');
        $employeeDeptEnum = ['hr', 'production', 'distribution', 'executive'];

        $this->command->info("Found " . count($userRoleEnum) . " valid User Roles.");
        $this->command->info("Found " . count($employeeJabatanEnum) . " valid Employee Jabatan.");

        foreach ($targetRoles as $roleName) {
            $this->createUserForRole($roleName, $userRoleEnum, $employeeJabatanEnum);
        }
    }

    private function createUserForRole($roleName, $validUserRoles, $validJabatan)
    {
        // Email generation
        $emailPrefix = strtolower(str_replace([' ', '&'], '', $roleName));
        // handle "Art & Set" -> artset
        $email = $emailPrefix . '@joe.com';

        if (User::where('email', $email)->exists()) {
            $this->command->warn("User {$email} already exists. Skipping.");
            return;
        }

        // 1. Determine User Role
        // If roleName is in validUserRoles (case insensitive check), use it. Else 'Employee'.
        $userRole = $this->findInEnum($roleName, $validUserRoles) ?? 'Employee';

        // 2. Determine Employee Jabatan
        // If roleName is in validJabatan, use it. Else 'Employee'.
        $jabatan = $this->findInEnum($roleName, $validJabatan) ?? 'Employee';

        // Handle special cases for truncated names or variations if needed
        // e.g. "Art & Set Properti" might need careful matching if DB has different string

        // 3. Determine Department
        $department = $this->mapDepartment($roleName);

        try {
            $employee = Employee::create([
                'nama_lengkap' => $roleName . ' User',
                'nik' => 'EMP-' . strtoupper(Str::random(6)),
                'nip' => 'NIP-' . strtoupper(Str::random(6)),
                'NumCard' => (string) rand(1000000000, 9999999999),
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Generated Address for ' . $roleName,
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => $jabatan,
                'department' => $department,
                'tanggal_mulai_kerja' => Carbon::now(),
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 5000000,
                'tunjangan' => 1000000,
                'bonus' => 0,
                'created_from' => 'CompleteRoleUserSeeder',
                'nomor_bpjs_kesehatan' => (string) rand(10000000000, 99999999999),
                'nomor_bpjs_ketenagakerjaan' => (string) rand(10000000000, 99999999999),
                'npwp' => (string) rand(100000000000000, 999999999999999),
                'nomor_kontrak' => 'CONT-' . Str::random(5),
                'tanggal_kontrak_berakhir' => Carbon::now()->addYear(),
            ]);

            User::create([
                'name' => $roleName . ' User',
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => $userRole,
                'employee_id' => $employee->id,
                'phone' => '08' . rand(1000000000, 9999999999),
            ]);

            $this->command->info("Created: {$roleName} -> {$email} | UserRole: {$userRole} | Jabatan: {$jabatan}");

        } catch (\Exception $e) {
            $msg = "Failed {$roleName}: " . $e->getMessage() . "\n";
            file_put_contents('seeder_errors.txt', $msg, FILE_APPEND);
            $this->command->error($msg);
        }
    }

    private function getEnumValues($table, $column)
    {
        $type = DB::select("SHOW COLUMNS FROM {$table} LIKE '{$column}'")[0]->Type;
        preg_match('/^enum\((.*)\)$/', $type, $matches);
        $enum = [];
        foreach (explode(',', $matches[1]) as $value) {
            $v = trim($value, "'");
            $enum[] = $v;
        }
        return $enum;
    }

    private function findInEnum($needle, $haystack)
    {
        foreach ($haystack as $val) {
            if (strcasecmp($needle, $val) == 0) {
                return $val;
            }
        }
        return null;
    }

    private function mapDepartment($role)
    {
        $role = strtolower($role);
        if (in_array($role, ['hr', 'general affairs', 'finance', 'office assistant', 'ga'])) {
            return 'hr';
        }
        if (in_array($role, ['distribution manager', 'promotion', 'social media', 'broadcasting', 'graphic design', 'hopeline care'])) {
            return 'distribution';
        }
        if (in_array($role, ['vp president', 'president director'])) {
            return 'executive';
        }
        // Default others to production
        return 'production';
    }
}
