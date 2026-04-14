<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pr_talents', function (Blueprint $table) {
            $table->string('social_media')->nullable()->after('expertise'); // e.g. @instagramhandle
            $table->string('phone')->nullable()->after('social_media');     // Phone number
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_talents', function (Blueprint $table) {
            $table->dropColumn(['social_media', 'phone']);
        });
    }
};
