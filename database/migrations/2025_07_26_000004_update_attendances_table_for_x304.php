<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Jika tabel attendances tidak ada, buat baru
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->date('date')->comment('Tanggal absensi');
                $table->time('check_in')->nullable()->comment('Waktu tap pertama (masuk)');
                $table->time('check_out')->nullable()->comment('Waktu tap terakhir (pulang)');
                $table->enum('status', [
                    'present_ontime',      // Hadir tepat waktu
                    'present_late',        // Hadir terlambat  
                    'absent',              // Tidak hadir (tidak ada tap)
                    'on_leave',            // Cuti (dari sistem leave request)
                    'sick_leave',          // Sakit
                    'permission'           // Izin
                ])->default('absent');
                $table->decimal('work_hours', 5, 2)->nullable()->comment('Total jam kerja (dalam jam)');
                $table->decimal('overtime_hours', 5, 2)->default(0)->comment('Jam lembur');
                $table->integer('late_minutes')->default(0)->comment('Menit keterlambatan');
                $table->integer('early_leave_minutes')->default(0)->comment('Menit pulang cepat');
                $table->integer('total_taps')->default(0)->comment('Total jumlah tap dalam sehari');
                $table->text('notes')->nullable()->comment('Catatan tambahan');
                $table->timestamps();
                
                $table->unique(['employee_id', 'date']);
                $table->index(['date', 'status']);
                $table->index(['employee_id', 'date', 'status']);
            });
        } else {
            // Jika tabel sudah ada, update struktur
            Schema::table('attendances', function (Blueprint $table) {
                // Cek dan tambah kolom yang belum ada
                if (!Schema::hasColumn('attendances', 'late_minutes')) {
                    $table->integer('late_minutes')->default(0)->comment('Menit keterlambatan')->after('overtime_hours');
                }
                if (!Schema::hasColumn('attendances', 'early_leave_minutes')) {
                    $table->integer('early_leave_minutes')->default(0)->comment('Menit pulang cepat')->after('late_minutes');
                }
                if (!Schema::hasColumn('attendances', 'total_taps')) {
                    $table->integer('total_taps')->default(0)->comment('Total jumlah tap dalam sehari')->after('early_leave_minutes');
                }
            });
            
            // Update enum status jika perlu (ini perlu dilakukan terpisah)
            DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('present_ontime','present_late','absent','on_leave','sick_leave','permission') DEFAULT 'absent'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('attendances')) {
            // Jika ini adalah tabel yang kita buat, drop seluruhnya
            $hasOurColumns = Schema::hasColumn('attendances', 'late_minutes') && 
                           Schema::hasColumn('attendances', 'early_leave_minutes') && 
                           Schema::hasColumn('attendances', 'total_taps');
            
            if ($hasOurColumns) {
                // Jika semua kolom kita ada, berarti kita yang buat tabel ini
                Schema::dropIfExists('attendances');
            } else {
                // Jika tidak, hanya drop kolom yang kita tambahkan
                Schema::table('attendances', function (Blueprint $table) {
                    if (Schema::hasColumn('attendances', 'late_minutes')) {
                        $table->dropColumn('late_minutes');
                    }
                    if (Schema::hasColumn('attendances', 'early_leave_minutes')) {
                        $table->dropColumn('early_leave_minutes');
                    }
                    if (Schema::hasColumn('attendances', 'total_taps')) {
                        $table->dropColumn('total_taps');
                    }
                });
                
                // Kembalikan enum status ke semula
                DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('present','absent','sick','leave','permission','overtime')");
            }
        }
    }
}; 