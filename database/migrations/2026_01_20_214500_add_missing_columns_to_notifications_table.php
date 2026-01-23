<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Check if columns already exist before adding
            if (!Schema::hasColumn('notifications', 'related_type')) {
                // Try to add after 'action_url' if it exists, otherwise add after 'data'
                if (Schema::hasColumn('notifications', 'action_url')) {
                    $table->string('related_type')->nullable()->after('action_url');
                } else {
                    $table->string('related_type')->nullable()->after('data');
                }
            }
            
            if (!Schema::hasColumn('notifications', 'related_id')) {
                if (Schema::hasColumn('notifications', 'related_type')) {
                    $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
                } else {
                    $table->unsignedBigInteger('related_id')->nullable()->after('data');
                }
            }
            
            if (!Schema::hasColumn('notifications', 'status')) {
                $table->enum('status', ['unread', 'read', 'archived'])->default('unread')->after('data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['related_type', 'related_id', 'status']);
        });
    }
};
