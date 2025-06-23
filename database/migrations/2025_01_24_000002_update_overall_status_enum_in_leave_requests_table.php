<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Ubah enum overall_status untuk single-step approval
            $table->enum('overall_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Kembalikan ke enum lama
            $table->enum('overall_status', ['pending_manager', 'pending_hr', 'approved', 'rejected'])
                  ->default('pending_manager')
                  ->change();
        });
    }
};