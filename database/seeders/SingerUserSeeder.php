<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class SingerUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $singerUsers = [
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '081234567900',
                'password' => bcrypt('password'),
                'role' => 'Singer'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'phone' => '081234567901',
                'password' => bcrypt('password'),
                'role' => 'Singer'
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike.johnson@example.com',
                'phone' => '081234567902',
                'password' => bcrypt('password'),
                'role' => 'Singer'
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@example.com',
                'phone' => '081234567903',
                'password' => bcrypt('password'),
                'role' => 'Singer'
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@example.com',
                'phone' => '081234567904',
                'password' => bcrypt('password'),
                'role' => 'Singer'
            ]
        ];

        foreach ($singerUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
