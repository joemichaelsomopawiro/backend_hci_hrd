<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_machine_id')->constrained()->onDelete('cascade');
            $table->enum('operation', [
                'pull_data', 'push_user', 'delete_user', 'delete_data', 
                'restart', 'sync_time', 'test_connection'
            ]);
            $table->enum('status', ['success', 'failed', 'partial']);
            $table->text('message')->nullable();
            $table->json('details')->nullable(); // Additional operation details
            $table->integer('records_processed')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('duration', 8, 3)->nullable(); // Duration in seconds
            $table->timestamps();
            
            $table->index(['attendance_machine_id', 'operation']);
            $table->index(['status', 'started_at']);
            $table->index('started_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_sync_logs');
    }
};