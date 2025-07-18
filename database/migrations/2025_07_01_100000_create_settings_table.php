<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Insert data awal untuk link Zoom
        DB::table('settings')->insert([
            [
                'key' => 'zoom_link',
                'value' => 'https://us06web.zoom.us/j/84336282102?pwd=SRr6cnMImgBz88wlIYAMwqjjA39LdA.1',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'zoom_meeting_id',
                'value' => '84336282102',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'zoom_passcode',
                'value' => 'SRr6cnMImgBz88wlIYAMwqjjA39LdA.1',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
}; 