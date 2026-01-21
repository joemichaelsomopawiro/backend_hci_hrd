<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add missing columns
            $table->string('related_type')->nullable()->after('action_url');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->enum('status', ['unread', 'read', 'archived'])->default('unread')->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['related_type', 'related_id', 'status']);
        });
    }
};
