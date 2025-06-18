<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\User;

class ManagerHierarchySeeder extends Seeder
{
    public function run()
    {
        // Setup HR Manager
        $hrEmployee = Employee::create([
            'nama_lengkap' => 'HR Manager',
            'nik' => '1111111111111111', // NIK unik untuk HR
            'nip' => 'HR001',
            'tanggal_lahir' => '1985-01-01',
            'jenis_kelamin' => 'Laki-laki',
            'alamat' => 'Jakarta',
            'status_pernikahan' => 'Menikah',
            'jabatan_saat_ini' => 'HR Manager',
            'department' => 'Finance',
            'tanggal_mulai_kerja' => '2020-01-01',
            'tingkat_pendidikan' => 'S1',
            'gaji_pokok' => 15000000,
        ]);
        
        User::create([
            'name' => 'HR Manager',
            'email' => 'hr@company.com',
            'phone' => '081234567890',
            'password' => bcrypt('password'),
            'employee_id' => $hrEmployee->id,
            'role' => 'HR'
        ]);

        // Setup Program Manager
        $programManager = Employee::create([
            'nama_lengkap' => 'Program Manager',
            'nik' => '2222222222222222', // NIK unik untuk Program Manager
            'nip' => 'PM001',
            'tanggal_lahir' => '1985-01-01',
            'jenis_kelamin' => 'Perempuan',
            'alamat' => 'Jakarta',
            'status_pernikahan' => 'Menikah',
            'jabatan_saat_ini' => 'Program Manager',
            'department' => 'Producer',
            'tanggal_mulai_kerja' => '2020-01-01',
            'tingkat_pendidikan' => 'S1',
            'gaji_pokok' => 12000000,
        ]);
        
        User::create([
            'name' => 'Program Manager',
            'email' => 'pm@company.com',
            'phone' => '081234567891',
            'password' => bcrypt('password'),
            'employee_id' => $programManager->id,
            'role' => 'Manager'
        ]);

        // Setup Distribution Manager
        $distributionManager = Employee::create([
            'nama_lengkap' => 'Distribution Manager',
            'nik' => '3333333333333333', // NIK unik untuk Distribution Manager
            'nip' => 'DM001',
            'tanggal_lahir' => '1985-01-01',
            'jenis_kelamin' => 'Laki-laki',
            'alamat' => 'Jakarta',
            'status_pernikahan' => 'Menikah',
            'jabatan_saat_ini' => 'Distribution Manager',
            'department' => 'Social Media',
            'tanggal_mulai_kerja' => '2020-01-01',
            'tingkat_pendidikan' => 'S1',
            'gaji_pokok' => 12000000,
        ]);
        
        User::create([
            'name' => 'Distribution Manager',
            'email' => 'dm@company.com',
            'phone' => '081234567892',
            'password' => bcrypt('password'),
            'employee_id' => $distributionManager->id,
            'role' => 'Manager'
        ]);
    }
}