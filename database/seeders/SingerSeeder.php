<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Singer;

class SingerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $singers = [
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '081234567890',
                'bio' => 'Professional singer with 10 years experience',
                'genre' => 'Pop',
                'vocal_range' => 'Tenor',
                'specialties' => ['Pop', 'Rock', 'Ballad'],
                'status' => 'active'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'phone' => '081234567891',
                'bio' => 'Jazz and R&B singer',
                'genre' => 'Jazz',
                'vocal_range' => 'Alto',
                'specialties' => ['Jazz', 'R&B', 'Soul'],
                'status' => 'active'
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike.johnson@example.com',
                'phone' => '081234567892',
                'bio' => 'Rock and alternative singer',
                'genre' => 'Rock',
                'vocal_range' => 'Baritone',
                'specialties' => ['Rock', 'Alternative', 'Metal'],
                'status' => 'active'
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@example.com',
                'phone' => '081234567893',
                'bio' => 'Classical and opera singer',
                'genre' => 'Classical',
                'vocal_range' => 'Soprano',
                'specialties' => ['Classical', 'Opera', 'Sacred'],
                'status' => 'active'
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@example.com',
                'phone' => '081234567894',
                'bio' => 'Country and folk singer',
                'genre' => 'Country',
                'vocal_range' => 'Tenor',
                'specialties' => ['Country', 'Folk', 'Bluegrass'],
                'status' => 'active'
            ]
        ];

        foreach ($singers as $singer) {
            Singer::create($singer);
        }
    }
}
