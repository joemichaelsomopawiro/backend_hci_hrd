<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
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

        // 1. Create Test Users
        $this->createTestUsers();

        // 2. Create Test Songs
        $this->createTestSongs();

        // 3. Create Test Singers
        $this->createTestSingers();

        echo "✅ Music Test Data seeded successfully!\n";
    }

    private function createTestUsers()
    {
        echo "Creating test users...\n";

        $users = [
            [
                'name' => 'Music Arranger Test',
                'email' => 'musicarranger@example.com',
                'password' => Hash::make('password'),
                'role' => 'Music Arranger',
                'phone' => '08123456701',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Producer Test',
                'email' => 'producer@example.com',
                'password' => Hash::make('password'),
                'role' => 'Producer',
                'phone' => '08123456702',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Sound Engineer Test',
                'email' => 'soundengineer@example.com',
                'password' => Hash::make('password'),
                'role' => 'Sound Engineer',
                'phone' => '08123456703',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Creative Test',
                'email' => 'creative@example.com',
                'password' => Hash::make('password'),
                'role' => 'Creative',
                'phone' => '08123456704',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Manager Program Test',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'Manager Program',
                'phone' => '08123456705',
                'email_verified_at' => now()
            ],
            // Additional crew members for team assignments (Phase 2)
            [
                'name' => 'Crew Member 1',
                'email' => 'crew1@example.com',
                'password' => Hash::make('password'),
                'role' => 'Crew',
                'phone' => '08123456710',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Crew Member 2',
                'email' => 'crew2@example.com',
                'password' => Hash::make('password'),
                'role' => 'Crew',
                'phone' => '08123456711',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Crew Member 3',
                'email' => 'crew3@example.com',
                'password' => Hash::make('password'),
                'role' => 'Crew',
                'phone' => '08123456712',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Crew Member 4',
                'email' => 'crew4@example.com',
                'password' => Hash::make('password'),
                'role' => 'Crew',
                'phone' => '08123456713',
                'email_verified_at' => now()
            ],
            [
                'name' => 'Crew Member 5',
                'email' => 'crew5@example.com',
                'password' => Hash::make('password'),
                'role' => 'Crew',
                'phone' => '08123456714',
                'email_verified_at' => now()
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

