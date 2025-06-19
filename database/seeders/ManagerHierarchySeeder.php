<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\User;
use App\Services\RoleHierarchyService;

class ManagerHierarchySeeder extends Seeder
{
    public function run()
    {
        // Create HR Manager (only if doesn't exist)
        $hrManager = Employee::where('nik', '1111111111111111')->first();
        if (!$hrManager) {
            $hrManager = Employee::create([
                'nama_lengkap' => 'HR Manager',
                'nik' => '1111111111111111',
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
        }
        
        // Create HR User (only if doesn't exist)
        $hrUser = User::where('email', 'hr@company.com')->first();
        if (!$hrUser) {
            User::create([
                'name' => 'HR Manager',
                'email' => 'hr@company.com',
                'phone' => '081234567890',
                'password' => bcrypt('password'),
                'employee_id' => $hrManager->id,
                'role' => 'HR'
            ]);
        }

        // Create Program Manager (only if doesn't exist)
        $programManager = Employee::where('nik', '2222222222222222')->first();
        if (!$programManager) {
            $programManager = Employee::create([
                'nama_lengkap' => 'Program Manager',
                'nik' => '2222222222222222',
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
        }
        
        // Create Program Manager User (only if doesn't exist)
        $pmUser = User::where('email', 'pm@company.com')->first();
        if (!$pmUser) {
            User::create([
                'name' => 'Program Manager',
                'email' => 'pm@company.com',
                'phone' => '081234567891',
                'password' => bcrypt('password'),
                'employee_id' => $programManager->id,
                'role' => 'Program Manager'
            ]);
        }

        // Create Distribution Manager (only if doesn't exist)
        $distributionManager = Employee::where('nik', '3333333333333333')->first();
        if (!$distributionManager) {
            $distributionManager = Employee::create([
                'nama_lengkap' => 'Distribution Manager', 
                'nik' => '3333333333333333',
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
        }
        
        // Create Distribution Manager User (only if doesn't exist)
        $dmUser = User::where('email', 'dm@company.com')->first();
        if (!$dmUser) {
            User::create([
                'name' => 'Distribution Manager',
                'email' => 'dm@company.com',
                'phone' => '081234567892',
                'password' => bcrypt('password'),
                'employee_id' => $distributionManager->id,
                'role' => 'Distribution Manager'
            ]);
        }
        
        // Update manager_id for existing employees based on their department
        $this->assignManagersToEmployees($hrManager, $programManager, $distributionManager);
    }
    
    private function assignManagersToEmployees($hrManager, $programManager, $distributionManager)
    {
        $hrDepartments = ['Finance', 'General Affairs', 'Office Assistant'];
        $programDepartments = ['Producer', 'Creative', 'Production', 'Editor'];
        $distributionDepartments = ['Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'];
        
        // Assign HR subordinates
        Employee::whereIn('department', $hrDepartments)
               ->where('id', '!=', $hrManager->id)
               ->update(['manager_id' => $hrManager->id]);
               
        // Assign Program Manager subordinates
        Employee::whereIn('department', $programDepartments)
               ->where('id', '!=', $programManager->id)
               ->update(['manager_id' => $programManager->id]);
               
        // Assign Distribution Manager subordinates
        Employee::whereIn('department', $distributionDepartments)
               ->where('id', '!=', $distributionManager->id)
               ->update(['manager_id' => $distributionManager->id]);
    }
}