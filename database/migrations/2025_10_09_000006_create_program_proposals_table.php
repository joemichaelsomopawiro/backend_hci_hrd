<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Program Proposals - Proposal program yang terkoneksi dengan Google Spreadsheet
     * Data dari spreadsheet akan di-sync ke database untuk ditampilkan di website
     */
    public function up(): void
    {
        Schema::create('program_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_regular_id')->nullable()->constrained('program_regular')->onDelete('cascade');
            
            // Google Spreadsheet Integration
            $table->string('spreadsheet_id'); // ID dari Google Spreadsheet
            $table->string('spreadsheet_url')->nullable(); // Full URL untuk akses
            $table->string('sheet_name')->default('Sheet1'); // Nama sheet yang digunakan
            
            // Proposal Data (di-sync dari spreadsheet)
            $table->string('proposal_title');
            $table->text('proposal_description')->nullable();
            $table->enum('format_type', ['mingguan', 'kwartal'])->default('mingguan');
            
            // Jika format kwartal, ada detail pelajaran
            $table->json('kwartal_data')->nullable(); // Array kwartal 1-4 dengan pelajaran 1-14 masing-masing
            
            // Opsi Jadwal Tayang
            $table->json('schedule_options')->nullable(); // Array opsi jadwal (Episode 1-53 atau Kwartal 1-4)
            
            // Status Proposal
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'needs_revision'
            ])->default('draft');
            
            // Sync Info
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('auto_sync')->default(true); // Auto sync dari spreadsheet
            
            // Approval
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['program_regular_id', 'status']);
            $table->index('spreadsheet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_proposals');
    }
};

