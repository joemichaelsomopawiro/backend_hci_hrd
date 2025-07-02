<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AttendanceMachine;

class AttendanceMachineSeeder extends Seeder
{
    public function run(): void
    {
        AttendanceMachine::updateOrCreate(
            ['ip_address' => '10.10.10.85'],
            [
                'name' => 'Solution X304 - Main Office',
                'ip_address' => '10.10.10.85',
                'port' => 80,
                'comm_key' => '0',
                'device_id' => 'X304-001',
                'serial_number' => 'SOL-X304-2024-001',
                'status' => 'active',
                'settings' => [
                    'model' => 'Solution X304',
                    'features' => [
                        'fingerprint' => true,
                        'card_reader' => true,
                        'tft_lcd' => true,
                        'usb_port' => true,
                        'access_control' => true,
                        'web_server' => true
                    ],
                    'capacity' => [
                        'users' => 6000,
                        'transactions' => 100000
                    ],
                    'timezone' => 'Asia/Jakarta',
                    'work_hours' => [
                        'start' => '07:30:00',
                        'end' => '16:30:00'
                    ]
                ],
                'description' => 'Mesin Absensi Sidik Jari dan Akses Kontrol Pintu dengan Sensor Sidik Jari terbaik. Dilengkapi dengan Layar TFT LCD 3 Inch Full Color, USB Port, Alarm Function, SMS Message, Web Server, Scheduled Bell, dan berbagai fitur lainnya.'
            ]
        );
    }
} 