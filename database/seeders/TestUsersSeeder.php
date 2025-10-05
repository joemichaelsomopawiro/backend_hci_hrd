<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users for Program Management
        $users = [
            [
                'name' => 'Manager Program Test',
                'email' => 'manager.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Manager',
                'employee_id' => null,
                'phone' => '081111111111',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Producer Test',
                'email' => 'producer.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Producer',
                'employee_id' => null,
                'phone' => '081111111112',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Kreatif Test',
                'email' => 'kreatif.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Kreatif',
                'employee_id' => null,
                'phone' => '081111111113',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Promosi Test',
                'email' => 'promosi.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Promosi',
                'employee_id' => null,
                'phone' => '081111111114',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Design Grafis Test',
                'email' => 'design.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Design Grafis',
                'employee_id' => null,
                'phone' => '081111111115',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Produksi Test',
                'email' => 'produksi.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Produksi',
                'employee_id' => null,
                'phone' => '081111111116',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Editor Test',
                'email' => 'editor.test@example.com',
                'password' => Hash::make('password'),
                'role' => 'Editor',
                'employee_id' => null,
                'phone' => '081111111117',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Insert users
        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user
            );
        }

        $this->command->info('Test users created successfully!');
        $this->command->info('Users created: ' . count($users));
        
        // Show created users
        $createdUsers = DB::table('users')->whereIn('email', array_column($users, 'email'))->get();
        foreach ($createdUsers as $user) {
            $this->command->info("ID: {$user->id} - Name: {$user->name} - Email: {$user->email} - Role: {$user->role}");
        }
    }
}
