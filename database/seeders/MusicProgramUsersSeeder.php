<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MusicProgramUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Sound Engineer Editing',
                'email' => 'soundengineeringediting@example.com',
                'phone' => '081234567890',
                'role' => 'Sound Engineer Editing',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'General Affairs',
                'email' => 'generalaffairs@example.com',
                'phone' => '081234567891',
                'role' => 'General Affairs',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Social Media',
                'email' => 'socialmedia@example.com',
                'phone' => '081234567892',
                'role' => 'Promotion',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $userData) {
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();
            
            if (!$existingUser) {
                User::create($userData);
                echo "✅ Created user: {$userData['email']}\n";
            } else {
                echo "ℹ️  User already exists: {$userData['email']}\n";
            }
        }
    }
}









