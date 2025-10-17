<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Employee;
use App\Models\Song;
use App\Models\Singer;

class MusicTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Seeding Music Test Data...\n";

        // 1. Create Test Employees (HR/Manager Program registers employees first)
        $this->createTestEmployees();

        // 2. Create Test Users (Users register with employee data)
        $this->createTestUsers();

        // 3. Create Test Songs
        $this->createTestSongs();

        // 4. Create Test Singers
        $this->createTestSingers();

        echo "✅ Music Test Data seeded successfully!\n";
    }

    private function createTestEmployees()
    {
        echo "Creating test employees (HR/Manager Program registers employees first)...\n";

        $employees = [
            [
                'nama_lengkap' => 'Music Arranger Test',
                'nik' => '1234567890123456',
                'nip' => 'EMP001',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Music Street No. 1',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-01-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 5000000,
                'tunjangan' => 1000000,
                'bonus' => 500000,
                'nomor_bpjs_kesehatan' => 'BPJS001',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK001',
                'npwp' => 'NPWP001'
            ],
            [
                'nama_lengkap' => 'Producer Test',
                'nik' => '1234567890123457',
                'nip' => 'EMP002',
                'tanggal_lahir' => '1985-05-15',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Producer Avenue No. 2',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Producer',
                'tanggal_mulai_kerja' => '2023-06-01',
                'tingkat_pendidikan' => 'S2',
                'gaji_pokok' => 8000000,
                'tunjangan' => 2000000,
                'bonus' => 1000000,
                'nomor_bpjs_kesehatan' => 'BPJS002',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK002',
                'npwp' => 'NPWP002'
            ],
            [
                'nama_lengkap' => 'Sound Engineer Test',
                'nik' => '1234567890123458',
                'nip' => 'EMP003',
                'tanggal_lahir' => '1992-03-20',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Sound Street No. 3',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-02-01',
                'tingkat_pendidikan' => 'D3',
                'gaji_pokok' => 4500000,
                'tunjangan' => 800000,
                'bonus' => 300000,
                'nomor_bpjs_kesehatan' => 'BPJS003',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK003',
                'npwp' => 'NPWP003'
            ],
            [
                'nama_lengkap' => 'Creative Test',
                'nik' => '1234567890123459',
                'nip' => 'EMP004',
                'tanggal_lahir' => '1988-07-10',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Creative Lane No. 4',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Creative',
                'tanggal_mulai_kerja' => '2023-09-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 6000000,
                'tunjangan' => 1200000,
                'bonus' => 600000,
                'nomor_bpjs_kesehatan' => 'BPJS004',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK004',
                'npwp' => 'NPWP004'
            ],
            [
                'nama_lengkap' => 'Manager Program Test',
                'nik' => '1234567890123460',
                'nip' => 'EMP005',
                'tanggal_lahir' => '1980-12-25',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Manager Boulevard No. 5',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Program Manager',
                'tanggal_mulai_kerja' => '2022-01-01',
                'tingkat_pendidikan' => 'S2',
                'gaji_pokok' => 12000000,
                'tunjangan' => 3000000,
                'bonus' => 2000000,
                'nomor_bpjs_kesehatan' => 'BPJS005',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK005',
                'npwp' => 'NPWP005'
            ],
            // Crew members
            [
                'nama_lengkap' => 'Crew Member 1',
                'nik' => '1234567890123461',
                'nip' => 'EMP006',
                'tanggal_lahir' => '1995-04-15',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Crew Street No. 6',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-03-01',
                'tingkat_pendidikan' => 'SMA',
                'gaji_pokok' => 3500000,
                'tunjangan' => 500000,
                'bonus' => 200000,
                'nomor_bpjs_kesehatan' => 'BPJS006',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK006',
                'npwp' => 'NPWP006'
            ],
            [
                'nama_lengkap' => 'Crew Member 2',
                'nik' => '1234567890123462',
                'nip' => 'EMP007',
                'tanggal_lahir' => '1993-08-22',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Crew Avenue No. 7',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-03-15',
                'tingkat_pendidikan' => 'D3',
                'gaji_pokok' => 3800000,
                'tunjangan' => 600000,
                'bonus' => 250000,
                'nomor_bpjs_kesehatan' => 'BPJS007',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK007',
                'npwp' => 'NPWP007'
            ],
            [
                'nama_lengkap' => 'Crew Member 3',
                'nik' => '1234567890123463',
                'nip' => 'EMP008',
                'tanggal_lahir' => '1991-11-30',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Crew Lane No. 8',
                'status_pernikahan' => 'Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-04-01',
                'tingkat_pendidikan' => 'S1',
                'gaji_pokok' => 4200000,
                'tunjangan' => 700000,
                'bonus' => 300000,
                'nomor_bpjs_kesehatan' => 'BPJS008',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK008',
                'npwp' => 'NPWP008'
            ],
            [
                'nama_lengkap' => 'Crew Member 4',
                'nik' => '1234567890123464',
                'nip' => 'EMP009',
                'tanggal_lahir' => '1994-06-18',
                'jenis_kelamin' => 'Perempuan',
                'alamat' => 'Jl. Crew Boulevard No. 9',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-04-15',
                'tingkat_pendidikan' => 'D3',
                'gaji_pokok' => 4000000,
                'tunjangan' => 650000,
                'bonus' => 280000,
                'nomor_bpjs_kesehatan' => 'BPJS009',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK009',
                'npwp' => 'NPWP009'
            ],
            [
                'nama_lengkap' => 'Crew Member 5',
                'nik' => '1234567890123465',
                'nip' => 'EMP010',
                'tanggal_lahir' => '1996-02-14',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Crew Road No. 10',
                'status_pernikahan' => 'Belum Menikah',
                'jabatan_saat_ini' => 'Employee',
                'tanggal_mulai_kerja' => '2024-05-01',
                'tingkat_pendidikan' => 'SMA',
                'gaji_pokok' => 3600000,
                'tunjangan' => 550000,
                'bonus' => 220000,
                'nomor_bpjs_kesehatan' => 'BPJS010',
                'nomor_bpjs_ketenagakerjaan' => 'BPJSK010',
                'npwp' => 'NPWP010'
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

    private function createTestUsers()
    {
        echo "Creating test users (Users register with employee data)...\n";

        $users = [
            [
                'name' => 'Music Arranger Test',
                'email' => 'musicarranger@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456701',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Music Arranger Test')->first()->id
            ],
            [
                'name' => 'Producer Test',
                'email' => 'producer@example.com',
                'password' => Hash::make('password'),
                'role' => 'Producer',
                'phone' => '08123456702',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Producer Test')->first()->id
            ],
            [
                'name' => 'Sound Engineer Test',
                'email' => 'soundengineer@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456703',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Sound Engineer Test')->first()->id
            ],
            [
                'name' => 'Creative Test',
                'email' => 'creative@example.com',
                'password' => Hash::make('password'),
                'role' => 'Creative',
                'phone' => '08123456704',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Creative Test')->first()->id
            ],
            [
                'name' => 'Manager Program Test',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'Program Manager',
                'phone' => '08123456705',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Manager Program Test')->first()->id
            ],
            // Additional crew members for team assignments (Phase 2)
            [
                'name' => 'Crew Member 1',
                'email' => 'crew1@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456710',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Crew Member 1')->first()->id
            ],
            [
                'name' => 'Crew Member 2',
                'email' => 'crew2@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456711',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Crew Member 2')->first()->id
            ],
            [
                'name' => 'Crew Member 3',
                'email' => 'crew3@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456712',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Crew Member 3')->first()->id
            ],
            [
                'name' => 'Crew Member 4',
                'email' => 'crew4@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456713',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Crew Member 4')->first()->id
            ],
            [
                'name' => 'Crew Member 5',
                'email' => 'crew5@example.com',
                'password' => Hash::make('password'),
                'role' => 'Employee',
                'phone' => '08123456714',
                'email_verified_at' => now(),
                'employee_id' => Employee::where('nama_lengkap', 'Crew Member 5')->first()->id
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            echo "  ✓ Created user: {$userData['email']} ({$userData['role']})\n";
        }
    }

    private function createTestSongs()
    {
        echo "Creating test songs...\n";

        $songs = [
            [
                'title' => 'Amazing Grace',
                'artist' => 'Traditional',
                'genre' => 'Hymn',
                'duration' => '04:30',
                'lyrics' => 'Amazing grace, how sweet the sound...',
                'status' => 'available'
            ],
            [
                'title' => 'How Great Thou Art',
                'artist' => 'Carl Boberg',
                'genre' => 'Worship',
                'duration' => '05:15',
                'lyrics' => 'O Lord my God, when I in awesome wonder...',
                'status' => 'available'
            ],
            [
                'title' => 'Blessed Assurance',
                'artist' => 'Fanny Crosby',
                'genre' => 'Hymn',
                'duration' => '03:45',
                'lyrics' => 'Blessed assurance, Jesus is mine...',
                'status' => 'available'
            ],
            [
                'title' => 'Great Is Thy Faithfulness',
                'artist' => 'Thomas Chisholm',
                'genre' => 'Worship',
                'duration' => '04:20',
                'lyrics' => 'Great is thy faithfulness, O God my Father...',
                'status' => 'available'
            ],
            [
                'title' => 'Holy Holy Holy',
                'artist' => 'Reginald Heber',
                'genre' => 'Hymn',
                'duration' => '04:00',
                'lyrics' => 'Holy, holy, holy! Lord God Almighty...',
                'status' => 'available'
            ],
            [
                'title' => 'It Is Well With My Soul',
                'artist' => 'Horatio Spafford',
                'genre' => 'Hymn',
                'duration' => '04:45',
                'lyrics' => 'When peace like a river attendeth my way...',
                'status' => 'available'
            ],
            [
                'title' => 'What A Friend We Have In Jesus',
                'artist' => 'Joseph Scriven',
                'genre' => 'Hymn',
                'duration' => '03:30',
                'lyrics' => 'What a friend we have in Jesus...',
                'status' => 'available'
            ],
            [
                'title' => 'The Old Rugged Cross',
                'artist' => 'George Bennard',
                'genre' => 'Gospel',
                'duration' => '04:10',
                'lyrics' => 'On a hill far away stood an old rugged cross...',
                'status' => 'available'
            ]
        ];

        foreach ($songs as $songData) {
            Song::updateOrCreate(
                ['title' => $songData['title']],
                $songData
            );
            echo "  ✓ Created song: {$songData['title']}\n";
        }
    }

    private function createTestSingers()
    {
        echo "Creating test singers...\n";

        $singers = [
            [
                'name' => 'David Johnson',
                'email' => 'david.johnson@example.com',
                'phone' => '08123456801',
                'bio' => 'Professional worship leader with 10+ years experience',
                'specialties' => ['Worship', 'Contemporary', 'Gospel'],
                'status' => 'active'
            ],
            [
                'name' => 'Sarah Williams',
                'email' => 'sarah.williams@example.com',
                'phone' => '08123456802',
                'bio' => 'Soprano vocalist specializing in traditional hymns',
                'specialties' => ['Hymn', 'Classical', 'Traditional'],
                'status' => 'active'
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@example.com',
                'phone' => '08123456803',
                'bio' => 'Contemporary Christian music artist',
                'specialties' => ['Contemporary', 'Pop', 'Rock'],
                'status' => 'active'
            ],
            [
                'name' => 'Grace Martinez',
                'email' => 'grace.martinez@example.com',
                'phone' => '08123456804',
                'bio' => 'Gospel and spiritual music specialist',
                'specialties' => ['Gospel', 'Spiritual', 'Soul'],
                'status' => 'active'
            ],
            [
                'name' => 'James Anderson',
                'email' => 'james.anderson@example.com',
                'phone' => '08123456805',
                'bio' => 'Baritone vocalist with extensive church music background',
                'specialties' => ['Hymn', 'Traditional', 'Choral'],
                'status' => 'active'
            ]
        ];

        foreach ($singers as $singerData) {
            Singer::updateOrCreate(
                ['email' => $singerData['email']],
                $singerData
            );
            echo "  ✓ Created singer: {$singerData['name']}\n";
        }
    }
}

