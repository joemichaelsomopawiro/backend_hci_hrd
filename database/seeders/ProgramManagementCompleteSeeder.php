<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ProgramManagementCompleteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('program_notifications')->delete();
        DB::table('production_equipment')->delete();
        DB::table('media_files')->delete();
        DB::table('schedules')->delete();
        DB::table('episodes')->delete();
        DB::table('team_members')->delete();
        DB::table('program_team')->delete();
        DB::table('teams')->delete();
        DB::table('programs')->delete();

        // Create users for Program Management
        $users = [
            [
                'name' => 'Manager Program',
                'email' => 'manager.program@example.com',
                'password' => Hash::make('password'),
                'role' => 'Manager',
                'employee_id' => null,
                'phone' => '082234567890',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Producer Senior',
                'email' => 'producer.senior@example.com',
                'password' => Hash::make('password'),
                'role' => 'Producer',
                'employee_id' => null,
                'phone' => '082234567891',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Kreatif Lead',
                'email' => 'kreatif.lead@example.com',
                'password' => Hash::make('password'),
                'role' => 'Creative',
                'employee_id' => null,
                'phone' => '082234567892',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Promotion Specialist',
                'email' => 'promotion.specialist@example.com',
                'password' => Hash::make('password'),
                'role' => 'Promotion',
                'employee_id' => null,
                'phone' => '082234567893',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Graphic Design',
                'email' => 'graphic.design@example.com',
                'password' => Hash::make('password'),
                'role' => 'Graphic Design',
                'employee_id' => null,
                'phone' => '082234567894',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Produksi Manager',
                'email' => 'produksi.manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'Production',
                'employee_id' => null,
                'phone' => '082234567895',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Editor Senior',
                'email' => 'editor.senior@example.com',
                'password' => Hash::make('password'),
                'role' => 'Editor',
                'employee_id' => null,
                'phone' => '082234567896',
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

        // Get user IDs
        $managerId = DB::table('users')->where('email', 'manager.program@example.com')->value('id');
        $producerId = DB::table('users')->where('email', 'producer.senior@example.com')->value('id');
        $kreatifId = DB::table('users')->where('email', 'kreatif.lead@example.com')->value('id');
        $promosiId = DB::table('users')->where('email', 'promosi.specialist@example.com')->value('id');
        $designId = DB::table('users')->where('email', 'design.grafis@example.com')->value('id');
        $produksiId = DB::table('users')->where('email', 'produksi.manager@example.com')->value('id');
        $editorId = DB::table('users')->where('email', 'editor.senior@example.com')->value('id');

        // Create programs
        $programs = [
            [
                'name' => 'Program Pagi Inspirasi',
                'description' => 'Program inspirasi pagi untuk memulai hari dengan semangat',
                'status' => 'planning',
                'type' => 'weekly',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'air_time' => '07:00:00',
                'duration_minutes' => 30,
                'broadcast_channel' => 'YouTube Live',
                'rundown' => '1. Opening (2 menit) 2. Inspirasi (25 menit) 3. Closing (3 menit)',
                'requirements' => json_encode(['Camera', 'Microphone', 'Lighting', 'Green Screen']),
                'manager_id' => $managerId,
                'producer_id' => $producerId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Program Malam Refleksi',
                'description' => 'Program refleksi malam untuk mengakhiri hari dengan tenang',
                'status' => 'in_production',
                'type' => 'daily',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'air_time' => '21:00:00',
                'duration_minutes' => 45,
                'broadcast_channel' => 'Instagram Live',
                'rundown' => '1. Opening (3 menit) 2. Refleksi (35 menit) 3. Closing (7 menit)',
                'requirements' => json_encode(['Camera', 'Microphone', 'Soft Lighting']),
                'manager_id' => $managerId,
                'producer_id' => $producerId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('programs')->insert($programs);

        // Get program IDs
        $program1Id = DB::table('programs')->where('name', 'Program Pagi Inspirasi')->value('id');
        $program2Id = DB::table('programs')->where('name', 'Program Malam Refleksi')->value('id');

        // Create teams
        $teams = [
            [
                'name' => 'Tim Kreatif Pagi',
                'description' => 'Tim kreatif untuk program pagi',
                'role' => 'kreatif',
                'program_id' => $program1Id,
                'team_lead_id' => $kreatifId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Tim Promosi Pagi',
                'description' => 'Tim promosi untuk program pagi',
                'role' => 'promosi',
                'program_id' => $program1Id,
                'team_lead_id' => $promosiId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Tim Design Grafis',
                'description' => 'Tim design grafis untuk semua program',
                'role' => 'design_grafis',
                'program_id' => $program1Id,
                'team_lead_id' => $designId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Tim Produksi',
                'description' => 'Tim produksi untuk semua program',
                'role' => 'produksi',
                'program_id' => $program1Id,
                'team_lead_id' => $produksiId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Tim Editor',
                'description' => 'Tim editor untuk semua program',
                'role' => 'editor',
                'program_id' => $program1Id,
                'team_lead_id' => $editorId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('teams')->insert($teams);

        // Get team IDs
        $teamIds = DB::table('teams')->pluck('id')->toArray();

        // Create program-team relationships
        foreach ($teamIds as $teamId) {
            DB::table('program_team')->insert([
                'program_id' => $program1Id,
                'team_id' => $teamId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create team members
        $teamMembers = [
            // Tim Kreatif
            [
                'team_id' => $teamIds[0],
                'user_id' => $kreatifId,
                'role' => 'lead',
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            // Tim Promosi
            [
                'team_id' => $teamIds[1],
                'user_id' => $promosiId,
                'role' => 'lead',
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            // Tim Design Grafis
            [
                'team_id' => $teamIds[2],
                'user_id' => $designId,
                'role' => 'lead',
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            // Tim Produksi
            [
                'team_id' => $teamIds[3],
                'user_id' => $produksiId,
                'role' => 'lead',
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            // Tim Editor
            [
                'team_id' => $teamIds[4],
                'user_id' => $editorId,
                'role' => 'lead',
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('team_members')->insert($teamMembers);

        // Create episodes
        $episodes = [
            [
                'title' => 'Episode 1 - Memulai Hari dengan Semangat',
                'description' => 'Episode pertama tentang motivasi pagi',
                'episode_number' => 1,
                'program_id' => $program1Id,
                'air_date' => '2025-01-20',
                'production_date' => '2025-01-18',
                'status' => 'draft',
                'script' => 'Script untuk episode pertama...',
                'talent_data' => json_encode(['host' => 'John Doe', 'guest' => 'Jane Smith']),
                'location' => 'Studio A',
                'notes' => 'Catatan produksi episode pertama',
                'production_notes' => json_encode(['camera_1' => 'Wide shot', 'camera_2' => 'Close up']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Episode 2 - Kekuatan Doa Pagi',
                'description' => 'Episode kedua tentang kekuatan doa di pagi hari',
                'episode_number' => 2,
                'program_id' => $program1Id,
                'air_date' => '2025-01-27',
                'production_date' => '2025-01-25',
                'status' => 'in_production',
                'script' => 'Script untuk episode kedua...',
                'talent_data' => json_encode(['host' => 'John Doe', 'guest' => 'Pastor Mike']),
                'location' => 'Studio A',
                'notes' => 'Catatan produksi episode kedua',
                'production_notes' => json_encode(['camera_1' => 'Wide shot', 'camera_2' => 'Close up']),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('episodes')->insert($episodes);

        // Get episode IDs
        $episode1Id = DB::table('episodes')->where('episode_number', 1)->value('id');
        $episode2Id = DB::table('episodes')->where('episode_number', 2)->value('id');

        // Create schedules
        $schedules = [
            [
                'title' => 'Meeting Persiapan Episode 1',
                'description' => 'Meeting untuk persiapan episode pertama',
                'type' => 'meeting',
                'program_id' => $program1Id,
                'episode_id' => $episode1Id,
                'team_id' => $teamIds[0],
                'assigned_to' => $kreatifId,
                'start_time' => '2025-01-15 09:00:00',
                'end_time' => '2025-01-15 11:00:00',
                'deadline' => '2025-01-15 12:00:00',
                'status' => 'pending',
                'location' => 'Studio A',
                'notes' => 'Membahas script dan rundown',
                'is_recurring' => false,
                'recurring_pattern' => null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Syuting Episode 1',
                'description' => 'Syuting episode pertama',
                'type' => 'production',
                'program_id' => $program1Id,
                'episode_id' => $episode1Id,
                'team_id' => $teamIds[3],
                'assigned_to' => $produksiId,
                'start_time' => '2025-01-18 08:00:00',
                'end_time' => '2025-01-18 12:00:00',
                'deadline' => '2025-01-18 13:00:00',
                'status' => 'pending',
                'location' => 'Studio A',
                'notes' => 'Syuting episode pertama',
                'is_recurring' => false,
                'recurring_pattern' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('schedules')->insert($schedules);

        // Create production equipment
        $equipment = [
            [
                'name' => 'Kamera Sony FX6',
                'description' => 'Kamera profesional untuk produksi',
                'category' => 'Camera',
                'brand' => 'Sony',
                'model' => 'FX6',
                'serial_number' => 'SN123456',
                'status' => 'available',
                'assigned_to' => null,
                'program_id' => null,
                'episode_id' => null,
                'last_maintenance' => '2024-12-01',
                'next_maintenance' => '2025-03-01',
                'notes' => 'Kondisi baik',
                'specifications' => json_encode(['resolution' => '4K', 'sensor' => 'Full Frame']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Microphone Rode Wireless',
                'description' => 'Microphone wireless untuk talent',
                'category' => 'Audio',
                'brand' => 'Rode',
                'model' => 'Wireless GO II',
                'serial_number' => 'SN789012',
                'status' => 'available',
                'assigned_to' => null,
                'program_id' => null,
                'episode_id' => null,
                'last_maintenance' => '2024-11-15',
                'next_maintenance' => '2025-02-15',
                'notes' => 'Baterai perlu diganti',
                'specifications' => json_encode(['type' => 'Wireless', 'range' => '100m']),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('production_equipment')->insert($equipment);

        // Create program notifications
        $notifications = [
            [
                'title' => 'Deadline Script Episode 1',
                'message' => 'Script episode 1 harus selesai sebelum 15 Januari 2025',
                'type' => 'reminder',
                'user_id' => $kreatifId,
                'program_id' => $program1Id,
                'episode_id' => $episode1Id,
                'schedule_id' => null,
                'is_read' => false,
                'read_at' => null,
                'scheduled_at' => '2025-01-14 09:00:00',
                'data' => json_encode(['priority' => 'high']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Meeting Persiapan Episode 1',
                'message' => 'Meeting persiapan episode 1 akan dimulai dalam 1 jam',
                'type' => 'info',
                'user_id' => $kreatifId,
                'program_id' => $program1Id,
                'episode_id' => $episode1Id,
                'schedule_id' => null,
                'is_read' => false,
                'read_at' => null,
                'scheduled_at' => null,
                'data' => json_encode(['priority' => 'medium']),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('program_notifications')->insert($notifications);

        $this->command->info('Program Management data seeded successfully!');
        $this->command->info('Users created: ' . count($users));
        $this->command->info('Programs created: ' . count($programs));
        $this->command->info('Teams created: ' . count($teams));
        $this->command->info('Episodes created: ' . count($episodes));
        $this->command->info('Schedules created: ' . count($schedules));
        $this->command->info('Equipment created: ' . count($equipment));
        $this->command->info('Notifications created: ' . count($notifications));
    }
}
