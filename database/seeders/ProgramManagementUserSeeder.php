<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProgramManagementUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create users for Program Management testing
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Manager',
                'employee_id' => 1,
                'phone' => '081234567890'
            ],
            [
                'name' => 'Manager Program',
                'email' => 'manager@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Program Manager',
                'employee_id' => 2,
                'phone' => '081234567891'
            ],
            [
                'name' => 'Producer',
                'email' => 'producer@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Producer',
                'employee_id' => 3,
                'phone' => '081234567892'
            ],
            [
                'name' => 'Tim Kreatif',
                'email' => 'kreatif@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Creative',
                'employee_id' => 4,
                'phone' => '081234567893'
            ],
            [
                'name' => 'Tim Promosi',
                'email' => 'promosi@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Social Media',
                'employee_id' => 5,
                'phone' => '081234567894'
            ],
            [
                'name' => 'Design Grafis',
                'email' => 'design@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Creative',
                'employee_id' => 6,
                'phone' => '081234567895'
            ],
            [
                'name' => 'Tim Produksi',
                'email' => 'produksi@example.com',
                'password' => Hash::make('password123'),
                'role' => 'General Affairs',
                'employee_id' => 7,
                'phone' => '081234567896'
            ],
            [
                'name' => 'Editor',
                'email' => 'editor@example.com',
                'password' => Hash::make('password123'),
                'role' => 'Creative',
                'employee_id' => 8,
                'phone' => '081234567897'
            ]
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Program Management users created successfully!');
    }
}
