<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee;

class CompleteRoleTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Creating complete role test data...\n";

        // 1. Create all employees first
        $this->createAllEmployees();

        // 2. Create all users with employee linking
        $this->createAllUsers();

        echo "✅ Complete role test data seeded successfully!\n";
    }

    private function createAllEmployees()
    {
        echo "Creating all employees...\n";

        $employees = [
            // EXECUTIVE LEVEL
            [
                'nama_lengkap' => 'President Director Test',
                'nik' => '1234567890123001',
                'nip' => 'EXE001',
                'tanggal_lahir' => '1970-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Executive Tower No. 1',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'President Director',
                'tanggal_mulai_kerja' => '2020-01-01',
                'tingkat_pendidikan' => 'S3',
                'gaji_pokok' => 50000000,
                'tunjangan' => 10000000,
                'bonus' => 5000000,
                'nomor_bpjs_kesehatan' => 'BPJS001',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK001',
                'npwp' => 'NPWP001'
            ],
            [
                'nama_lengkap' => 'VP President Test',
                'nik' => '1234567890123002',
                'nip' => 'EXE002',
                'tanggal_lahir' => '1975-05-15',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. VP Avenue No. 2',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'VP President',
                'tanggal_mulai_kerja' => '2021-01-01',
                'tingkat_pendidikan' => 'S2',
                'gaji_pokok' => 35000000,
                'tunjangan' => 7000000,
                'bonus' => 3500000,
                'nomor_bpjs_kesehatan' => 'BPJS002',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK002',
                'npwp' => 'NPWP002'
            ],
            [
                'nama_lengkap' => 'HR Manager Test',
                'nik' => '1234567890123003',
                'nip' => 'HR001',
                'tanggal_lahir' => '1980-03-20',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. HR Street No. 3',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'HR',
                'tanggal_mulai_kerja' => '2022-01-01',
                'tingkat_pendidikan' => 'S2',
                'gaji_pokok' => 15000000,
                'tunjangan' => 3000000,
                'bonus' => 1500000,
                'nomor_bpjs_kesehatan' => 'BPJS003',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK003',
                'npwp' => 'NPWP003'
            ],
            [
                'nama_lengkap' => 'Program Manager Test',
                'nik' => '1234567890123004',
                'nip' => 'PM001',
                'tanggal_lahir' => '1982-07-10',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Program Boulevard No. 4',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Program Manager',
                'tanggal_mulai_kerja' => '2022-06-01',
                'tingkat_pendidikan' => 'S2',
                'gaji_pokok' => 20000000,
                'tunjangan' => 4000000,
                'bonus' => 2000000,
                'nomor_bpjs_kesehatan' => 'BPJS004',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK004',
                'npwp' => 'NPWP004'
            ],
            [
                'nama_lengkap' => 'Distribution Manager Test',
                'nik' => '1234567890123005',
                'nip' => 'DM001',
                'tanggal_lahir' => '1983-11-25',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Distribution Road No. 5',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Distribution Manager',
                'tanggal_mulai_kerja' => '2022-09-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 18000000,
                'tunjangan' => 3600000,
                'bonus' => 1800000,
                'nomor_bpjs_kesehatan' => 'BPJS005',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK005',
                'npwp' => 'NPWP005'
            ],

            // MUSIC PROGRAM ROLES
            [
                'nama_lengkap' => 'Music Arranger Test',
                'nik' => '1234567890123006',
                'nip' => 'MA001',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Music Street No. 6',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Music Arranger',
                'tanggal_mulai_kerja' => '2024-01-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 8000000,
                'tunjangan' => 1600000,
                'bonus' => 800000,
                'nomor_bpjs_kesehatan' => 'BPJS006',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK006',
                'npwp' => 'NPWP006'
            ],
            [
                'nama_lengkap' => 'Producer Test',
                'nik' => '1234567890123007',
                'nip' => 'PROD001',
                'tanggal_lahir' => '1985-05-15',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Producer Avenue No. 7',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Producer',
                'tanggal_mulai_kerja' => '2023-06-01',
                'tingkat_pendidikan' => 'S2',
                'gaji_pokok' => 12000000,
                'tunjangan' => 2400000,
                'bonus' => 1200000,
                'nomor_bpjs_kesehatan' => 'BPJS007',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK007',
                'npwp' => 'NPWP007'
            ],
            [
                'nama_lengkap' => 'Sound Engineer Test',
                'nik' => '1234567890123008',
                'nip' => 'SE001',
                'tanggal_lahir' => '1992-03-20',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Sound Street No. 8',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Sound Engineer',
                'tanggal_mulai_kerja' => '2024-02-01',
                'tingkat_pendidikan' => 'D3',
                'gaji_pokok' => 7000000,
                'tunjangan' => 1400000,
                'bonus' => 700000,
                'nomor_bpjs_kesehatan' => 'BPJS008',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK008',
                'npwp' => 'NPWP008'
            ],
            [
                'nama_lengkap' => 'Creative Test',
                'nik' => '1234567890123009',
                'nip' => 'CRE001',
                'tanggal_lahir' => '1988-07-10',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Creative Lane No. 9',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Creative',
                'tanggal_mulai_kerja' => '2023-09-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 9000000,
                'tunjangan' => 1800000,
                'bonus' => 900000,
                'nomor_bpjs_kesehatan' => 'BPJS009',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK009',
                'npwp' => 'NPWP009'
            ],
            [
                'nama_lengkap' => 'Editor Test',
                'nik' => '1234567890123010',
                'nip' => 'ED001',
                'tanggal_lahir' => '1991-12-15',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Editor Road No. 10',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Editor',
                'tanggal_mulai_kerja' => '2024-03-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7500000,
                'tunjangan' => 1500000,
                'bonus' => 750000,
                'nomor_bpjs_kesehatan' => 'BPJS010',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK010',
                'npwp' => 'NPWP010'
            ],
            [
                'nama_lengkap' => 'Quality Control Test',
                'nik' => '1234567890123011',
                'nip' => 'QC001',
                'tanggal_lahir' => '1987-04-22',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. QC Street No. 11',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Quality Control',
                'tanggal_mulai_kerja' => '2023-11-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 8500000,
                'tunjangan' => 1700000,
                'bonus' => 850000,
                'nomor_bpjs_kesehatan' => 'BPJS011',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK011',
                'npwp' => 'NPWP011'
            ],
            [
                'nama_lengkap' => 'Art Set Design Test',
                'nik' => '1234567890123012',
                'nip' => 'ASD001',
                'tanggal_lahir' => '1989-08-30',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Art Design No. 12',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Art & Set Properti',
                'tanggal_mulai_kerja' => '2024-04-01',
                'tingkat_pendidikan' => 'D3',
                'gaji_pokok' => 6500000,
                'tunjangan' => 1300000,
                'bonus' => 650000,
                'nomor_bpjs_kesehatan' => 'BPJS012',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK012',
                'npwp' => 'NPWP012'
            ],

            // PRODUCTION ROLES
            [
                'nama_lengkap' => 'Production Test',
                'nik' => '1234567890123013',
                'nip' => 'PROD002',
                'tanggal_lahir' => '1986-06-18',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Production Avenue No. 13',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Production',
                'tanggal_mulai_kerja' => '2023-08-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 10000000,
                'tunjangan' => 2000000,
                'bonus' => 1000000,
                'nomor_bpjs_kesehatan' => 'BPJS013',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK013',
                'npwp' => 'NPWP013'
            ],
            [
                'nama_lengkap' => 'Editor Promotion Test',
                'nik' => '1234567890123014',
                'nip' => 'EP001',
                'tanggal_lahir' => '1993-02-14',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Editor Promo No. 14',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Editor Promotion',
                'tanggal_mulai_kerja' => '2024-05-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 8000000,
                'tunjangan' => 1600000,
                'bonus' => 800000,
                'nomor_bpjs_kesehatan' => 'BPJS014',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK014',
                'npwp' => 'NPWP014'
            ],
            [
                'nama_lengkap' => 'Graphic Design Test',
                'nik' => '1234567890123015',
                'nip' => 'GD001',
                'tanggal_lahir' => '1994-09-25',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Graphic Design No. 15',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Graphic Design',
                'tanggal_mulai_kerja' => '2024-06-01',
                'tingkat_pendidikan' => 'D3',
                'gaji_pokok' => 6000000,
                'tunjangan' => 1200000,
                'bonus' => 600000,
                'nomor_bpjs_kesehatan' => 'BPJS015',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK015',
                'npwp' => 'NPWP015'
            ],
            [
                'nama_lengkap' => 'Social Media Test',
                'nik' => '1234567890123016',
                'nip' => 'SM001',
                'tanggal_lahir' => '1995-11-08',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Social Media No. 16',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Social Media',
                'tanggal_mulai_kerja' => '2024-07-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7000000,
                'tunjangan' => 1400000,
                'bonus' => 700000,
                'nomor_bpjs_kesehatan' => 'BPJS016',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK016',
                'npwp' => 'NPWP016'
            ],
            [
                'nama_lengkap' => 'Promotion Test',
                'nik' => '1234567890123017',
                'nip' => 'PROM001',
                'tanggal_lahir' => '1990-12-03',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Promotion Street No. 17',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Promotion',
                'tanggal_mulai_kerja' => '2023-12-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7500000,
                'tunjangan' => 1500000,
                'bonus' => 750000,
                'nomor_bpjs_kesehatan' => 'BPJS017',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK017',
                'npwp' => 'NPWP017'
            ],

            // SUPPORT ROLES
            [
                'nama_lengkap' => 'General Affairs Test',
                'nik' => '1234567890123018',
                'nip' => 'GA001',
                'tanggal_lahir' => '1984-01-20',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. General Affairs No. 18',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'General Affairs',
                'tanggal_mulai_kerja' => '2022-03-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 9000000,
                'tunjangan' => 1800000,
                'bonus' => 900000,
                'nomor_bpjs_kesehatan' => 'BPJS018',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK018',
                'npwp' => 'NPWP018'
            ],
            [
                'nama_lengkap' => 'Office Assistant Test',
                'nik' => '1234567890123019',
                'nip' => 'OA001',
                'tanggal_lahir' => '1996-05-12',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Office Assistant No. 19',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Office Assistant',
                'tanggal_mulai_kerja' => '2024-08-01',
                'tingkat_pendidikan' => 'SMA',
                'gaji_pokok' => 4500000,
                'tunjangan' => 900000,
                'bonus' => 450000,
                'nomor_bpjs_kesehatan' => 'BPJS019',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK019',
                'npwp' => 'NPWP019'
            ],
            [
                'nama_lengkap' => 'Hopeline Care Test',
                'nik' => '1234567890123020',
                'nip' => 'HC001',
                'tanggal_lahir' => '1983-10-07',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Hopeline Care No. 20',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Hopeline Care',
                'tanggal_mulai_kerja' => '2022-11-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 8000000,
                'tunjangan' => 1600000,
                'bonus' => 800000,
                'nomor_bpjs_kesehatan' => 'BPJS020',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK020',
                'npwp' => 'NPWP020'
            ],
            [
                'nama_lengkap' => 'Finance Test',
                'nik' => '1234567890123021',
                'nip' => 'FIN001',
                'tanggal_lahir' => '1981-07-15',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Finance Street No. 21',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Finance',
                'tanggal_mulai_kerja' => '2022-02-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 11000000,
                'tunjangan' => 2200000,
                'bonus' => 1100000,
                'nomor_bpjs_kesehatan' => 'BPJS021',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK021',
                'npwp' => 'NPWP021'
            ],
            [
                'nama_lengkap' => 'GA Test',
                'nik' => '1234567890123022',
                'nip' => 'GA002',
                'tanggal_lahir' => '1987-03-28',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. GA Boulevard No. 22',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'General Affairs',
                'tanggal_mulai_kerja' => '2023-04-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 7000000,
                'tunjangan' => 1400000,
                'bonus' => 700000,
                'nomor_bpjs_kesehatan' => 'BPJS022',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK022',
                'npwp' => 'NPWP022'
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

    private function createAllUsers()
    {
        echo "Creating all users with employee linking...\n";

        $users = [
            // EXECUTIVE LEVEL
            [
                'name' => 'President Director Test',
                'email' => 'president@example.com',
                'password' => Hash::make('password'),
                'role' => 'President Director',
                'phone' => '08123456001',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'President Director Test')->first()->id
            ],
            [
                'name' => 'VP President Test',
                'email' => 'vp@example.com',
                'password' => Hash::make('password'),
                'role' => 'VP President',
                'phone' => '08123456002',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'VP President Test')->first()->id
            ],
            [
                'name' => 'HR Manager Test',
                'email' => 'hr@example.com',
                'password' => Hash::make('password'),
                'role' => 'HR',
                'phone' => '08123456003',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'HR Manager Test')->first()->id
            ],
            [
                'name' => 'Program Manager Test',
                'email' => 'programmanager@example.com',
                'password' => Hash::make('password'),
                'role' => 'Program Manager',
                'phone' => '08123456004',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Program Manager Test')->first()->id
            ],
            [
                'name' => 'Distribution Manager Test',
                'email' => 'distribution@example.com',
                'password' => Hash::make('password'),
                'role' => 'Distribution Manager',
                'phone' => '08123456005',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Distribution Manager Test')->first()->id
            ],

            // MUSIC PROGRAM ROLES
            [
                'name' => 'Music Arranger Test',
                'email' => 'musicarranger@example.com',
                'password' => Hash::make('password'),
                'role' => 'Music Arranger',
                'phone' => '08123456006',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Music Arranger Test')->first()->id
            ],
            [
                'name' => 'Producer Test',
                'email' => 'producer@example.com',
                'password' => Hash::make('password'),
                'role' => 'Producer',
                'phone' => '08123456007',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Producer Test')->first()->id
            ],
            [
                'name' => 'Sound Engineer Test',
                'email' => 'soundengineer@example.com',
                'password' => Hash::make('password'),
                'role' => 'Sound Engineer',
                'phone' => '08123456008',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Sound Engineer Test')->first()->id
            ],
            [
                'name' => 'Creative Test',
                'email' => 'creative@example.com',
                'password' => Hash::make('password'),
                'role' => 'Creative',
                'phone' => '08123456009',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Creative Test')->first()->id
            ],
            [
                'name' => 'Editor Test',
                'email' => 'editor@example.com',
                'password' => Hash::make('password'),
                'role' => 'Editor',
                'phone' => '08123456010',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Editor Test')->first()->id
            ],
            [
                'name' => 'Quality Control Test',
                'email' => 'qualitycontrol@example.com',
                'password' => Hash::make('password'),
                'role' => 'Quality Control',
                'phone' => '08123456011',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Quality Control Test')->first()->id
            ],
            [
                'name' => 'Art Set Design Test',
                'email' => 'artsetdesign@example.com',
                'password' => Hash::make('password'),
                'role' => 'Art & Set Properti',
                'phone' => '08123456012',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Art Set Design Test')->first()->id
            ],

            // PRODUCTION ROLES
            [
                'name' => 'Production Test',
                'email' => 'production@example.com',
                'password' => Hash::make('password'),
                'role' => 'Production',
                'phone' => '08123456013',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Production Test')->first()->id
            ],
            [
                'name' => 'Editor Promotion Test',
                'email' => 'editorpromotion@example.com',
                'password' => Hash::make('password'),
                'role' => 'Editor Promotion',
                'phone' => '08123456014',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Editor Promotion Test')->first()->id
            ],
            [
                'name' => 'Graphic Design Test',
                'email' => 'graphicdesign@example.com',
                'password' => Hash::make('password'),
                'role' => 'Graphic Design',
                'phone' => '08123456015',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Graphic Design Test')->first()->id
            ],
            [
                'name' => 'Social Media Test',
                'email' => 'socialmedia@example.com',
                'password' => Hash::make('password'),
                'role' => 'Social Media',
                'phone' => '08123456016',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Social Media Test')->first()->id
            ],
            [
                'name' => 'Promotion Test',
                'email' => 'promotion@example.com',
                'password' => Hash::make('password'),
                'role' => 'Promotion',
                'phone' => '08123456017',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Promotion Test')->first()->id
            ],

            // SUPPORT ROLES
            [
                'name' => 'General Affairs Test',
                'email' => 'generalaffairs@example.com',
                'password' => Hash::make('password'),
                'role' => 'General Affairs',
                'phone' => '08123456018',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'General Affairs Test')->first()->id
            ],
            [
                'name' => 'Office Assistant Test',
                'email' => 'officeassistant@example.com',
                'password' => Hash::make('password'),
                'role' => 'Office Assistant',
                'phone' => '08123456019',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Office Assistant Test')->first()->id
            ],
            [
                'name' => 'Hopeline Care Test',
                'email' => 'hopelinecare@example.com',
                'password' => Hash::make('password'),
                'role' => 'Hopeline Care',
                'phone' => '08123456020',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Hopeline Care Test')->first()->id
            ],
            [
                'name' => 'Finance Test',
                'email' => 'finance@example.com',
                'password' => Hash::make('password'),
                'role' => 'Finance',
                'phone' => '08123456021',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Finance Test')->first()->id
            ],
            [
                'name' => 'GA Test',
                'email' => 'ga@example.com',
                'password' => Hash::make('password'),
                'role' => 'General Affairs',
                'phone' => '08123456022',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'GA Test')->first()->id
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
