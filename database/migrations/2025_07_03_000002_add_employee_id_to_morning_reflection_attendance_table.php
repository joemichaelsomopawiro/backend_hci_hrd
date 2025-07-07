<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Cek apakah tabel ada
        if (!Schema::hasTable('morning_reflection_attendance')) {
            Schema::create('morning_reflection_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->onDelete('cascade');
                $table->date('date');
                $table->enum('status', ['Hadir', 'Terlambat', 'Absen'])->default('Hadir');
                $table->timestamp('join_time')->nullable();
                $table->boolean('testing_mode')->default(false);
                $table->timestamps();
                
                // Unique constraint untuk mencegah duplikasi
                $table->unique(['employee_id', 'date'], 'unique_employee_date_attendance');
            });
        } else {
            // Jika tabel sudah ada, tambahkan kolom yang kurang
            Schema::table('morning_reflection_attendance', function (Blueprint $table) {
                if (!Schema::hasColumn('morning_reflection_attendance', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->after('id');
                    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                }
                if (!Schema::hasColumn('morning_reflection_attendance', 'date')) {
                    $table->date('date')->after('employee_id');
                }
                if (!Schema::hasColumn('morning_reflection_attendance', 'status')) {
                    $table->enum('status', ['Hadir', 'Terlambat', 'Absen'])->default('Hadir')->after('date');
                }
                if (!Schema::hasColumn('morning_reflection_attendance', 'join_time')) {
                    $table->timestamp('join_time')->nullable()->after('status');
                }
                if (!Schema::hasColumn('morning_reflection_attendance', 'testing_mode')) {
                    $table->boolean('testing_mode')->default(false)->after('join_time');
                }
            });
        }
    }

    public function down()
    {
        Schema::table('morning_reflection_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('morning_reflection_attendance', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
            if (Schema::hasColumn('morning_reflection_attendance', 'date')) {
                $table->dropColumn('date');
            }
            if (Schema::hasColumn('morning_reflection_attendance', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('morning_reflection_attendance', 'join_time')) {
                $table->dropColumn('join_time');
            }
            if (Schema::hasColumn('morning_reflection_attendance', 'testing_mode')) {
                $table->dropColumn('testing_mode');
            }
        });
    }
}; 