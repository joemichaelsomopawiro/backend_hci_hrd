<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee;

class JoeConDummyUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Creating Joe Con dummy users...\n";

        // Cleanup incorrect users if they exist
        $deleted = User::where('email', 'LIKE', '%@joe.con')->delete();
        if ($deleted > 0) {
            echo "  ✓ Cleaned up $deleted incorrect users with @joe.con\n";
        }

        // 1. Create employees for Joe Con users
        $this->createEmployees();

        // 2. Create users linked to employees
        $this->createUsers();

        echo "✅ Joe Con dummy users seeded successfully!\n";
    }

    private function createEmployees()
    {
        echo "Creating employees...\n";

        $employees = [
            [
                'nama_lengkap' => 'Editor Promotion Joe',
                'nik' => '1234567890123050',
                'nip' => 'EPJOE01',
                'tanggal_lahir' => '1995-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Joe Con No. 1',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Editor Promotion',
                'tanggal_mulai_kerja' => '2024-01-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7000000,
                'tunjangan' => 1400000,
                'bonus' => 700000,
                'nomor_bpjs_kesehatan' => 'BPJSJOE01',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSKJOE01',
                'npwp' => 'NPWPJOE01'
            ],
            [
                'nama_lengkap' => 'Graphic Design Joe',
                'nik' => '1234567890123051',
                'nip' => 'GDJOE01',
                'tanggal_lahir' => '1996-02-02',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Joe Con No. 2',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Graphic Design',
                'tanggal_mulai_kerja' => '2024-02-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 6500000,
                'tunjangan' => 1300000,
                'bonus' => 650000,
                'nomor_bpjs_kesehatan' => 'BPJSJOE02',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSKJOE02',
                'npwp' => 'NPWPJOE02'
            ],
            [
                'nama_lengkap' => 'Quality Control Joe',
                'nik' => '1234567890123052',
                'nip' => 'QCJOE01',
                'tanggal_lahir' => '1994-03-03',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Joe Con No. 3',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Quality Control',
                'tanggal_mulai_kerja' => '2023-03-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7500000,
                'tunjangan' => 1500000,
                'bonus' => 750000,
                'nomor_bpjs_kesehatan' => 'BPJSJOE03',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSKJOE03',
                'npwp' => 'NPWPJOE03'
            ],
            [
                'nama_lengkap' => 'Promotion Joe',
                'nik' => '1234567890123053',
                'nip' => 'PROMJOE01',
                'tanggal_lahir' => '1993-04-04',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Joe Con No. 4',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Promotion',
                'tanggal_mulai_kerja' => '2023-04-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7000000,
                'tunjangan' => 1400000,
                'bonus' => 700000,
                'nomor_bpjs_kesehatan' => 'BPJSJOE04',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSKJOE04',
                'npwp' => 'NPWPJOE04'
            ]
        ];

        foreach ($employees as $employeeData) {
            Employee::updateOrCreate(
                ['nik' => $employeeData['nik']],
                $employeeData
            );
            echo "  ✓ Created employee: {$employeeData['nama_lengkap']} ({$employeeData['jabatan_saat_ini']})\n";
        }
    }

    private function createUsers()
    {
        echo "Creating users...\n";

        $users = [
            [
                'name' => 'Editor Promotion Joe',
                'email' => 'editor.promotion@joe.com',
                'password' => Hash::make('password'),
                'role' => 'Editor Promotion',
                'phone' => '08999990001',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Editor Promotion Joe')->first()->id
            ],
            [
                'name' => 'Graphic Design Joe',
                'email' => 'graphic.design@joe.com',
                'password' => Hash::make('password'),
                'role' => 'Graphic Design',
                'phone' => '08999990002',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Graphic Design Joe')->first()->id
            ],
            [
                'name' => 'Quality Control Joe',
                'email' => 'quality.control@joe.com',
                'password' => Hash::make('password'),
                'role' => 'Quality Control',
                'phone' => '08999990003',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Quality Control Joe')->first()->id
            ],
            [
                'name' => 'Promotion Joe',
                'email' => 'promotion@joe.com',
                'password' => Hash::make('password'),
                'role' => 'Promotion',
                'phone' => '08999990004',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Promotion Joe')->first()->id
            ]
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            echo "  ✓ Created user: {$userData['email']} ({$userData['role']})\n";
        }
    }
}
