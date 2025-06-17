<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class LinkUsersToEmployeesSeeder extends Seeder
{
    public function run()
    {
        $users = User::whereNull('employee_id')->get();
        
        foreach ($users as $user) {
            $employee = Employee::where('nama_lengkap', $user->name)->first();
            
            if ($employee) {
                $user->update(['employee_id' => $employee->id]);
                echo "Linked user '{$user->name}' to employee '{$employee->nama_lengkap}'\n";
            } else {
                echo "No matching employee found for user '{$user->name}'\n";
            }
        }
    }
}