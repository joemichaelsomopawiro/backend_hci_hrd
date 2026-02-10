<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $items = [
            [
                'name' => 'Sony A7S III Camera Kit',
                'description' => 'Full frame mirrorless camera for video, includes 24-70mm lens',
                'total_quantity' => 2,
                'available_quantity' => 2,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Aputure 300D II Light',
                'description' => 'Professional LED Video Light with softbox',
                'total_quantity' => 4,
                'available_quantity' => 4,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sennheiser MKH 416 Boom Mic',
                'description' => 'Shotgun microphone for professional audio',
                'total_quantity' => 2,
                'available_quantity' => 2,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tripod Manfrotto 504HD',
                'description' => 'Professional video tripod with fluid head',
                'total_quantity' => 3,
                'available_quantity' => 3,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('inventory_items')->insert($items);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_items')->whereIn('name', [
            'Sony A7S III Camera Kit',
            'Aputure 300D II Light',
            'Sennheiser MKH 416 Boom Mic',
            'Tripod Manfrotto 504HD'
        ])->delete();
    }
};
