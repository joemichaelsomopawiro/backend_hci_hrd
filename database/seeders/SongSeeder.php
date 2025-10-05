<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Song;

class SongSeeder extends Seeder
{
    public function run()
    {
        $songs = [
            [
                'title' => 'Amazing Grace',
                'artist' => 'John Newton',
                'genre' => 'Hymn',
                'duration' => '3:45',
                'key_signature' => 'G',
                'bpm' => 90,
                'notes' => 'Classic hymn with beautiful melody',
                'status' => 'available'
            ],
            [
                'title' => 'How Great Thou Art',
                'artist' => 'Carl Boberg',
                'genre' => 'Hymn',
                'duration' => '4:12',
                'key_signature' => 'C',
                'bpm' => 80,
                'notes' => 'Powerful worship song',
                'status' => 'available'
            ],
            [
                'title' => 'What a Beautiful Name',
                'artist' => 'Hillsong',
                'genre' => 'Contemporary',
                'duration' => '4:30',
                'key_signature' => 'D',
                'bpm' => 75,
                'notes' => 'Modern worship favorite',
                'status' => 'available'
            ],
            [
                'title' => 'Oceans',
                'artist' => 'Hillsong United',
                'genre' => 'Contemporary',
                'duration' => '8:58',
                'key_signature' => 'D',
                'bpm' => 65,
                'notes' => 'Deep worship experience',
                'status' => 'available'
            ],
            [
                'title' => 'Way Maker',
                'artist' => 'Sinach',
                'genre' => 'Contemporary',
                'duration' => '5:20',
                'key_signature' => 'C',
                'bpm' => 70,
                'notes' => 'Faith-building anthem',
                'status' => 'available'
            ],
            [
                'title' => 'Cornerstone',
                'artist' => 'Hillsong Live',
                'genre' => 'Contemporary',
                'duration' => '6:15',
                'key_signature' => 'G',
                'bpm' => 85,
                'notes' => 'Solid foundation theme',
                'status' => 'available'
            ],
            [
                'title' => 'Great Are You Lord',
                'artist' => 'All Sons & Daughters',
                'genre' => 'Contemporary',
                'duration' => '4:45',
                'key_signature' => 'A',
                'bpm' => 78,
                'notes' => 'Declaration of praise',
                'status' => 'available'
            ],
            [
                'title' => 'Here I Am To Worship',
                'artist' => 'Tim Hughes',
                'genre' => 'Contemporary',
                'duration' => '4:05',
                'key_signature' => 'E',
                'bpm' => 82,
                'notes' => 'Heart of worship',
                'status' => 'available'
            ]
        ];

        foreach ($songs as $songData) {
            Song::create($songData);
        }
    }
}