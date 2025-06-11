<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEmployeesTableForNewColumns extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            // Rename gaji to gaji_pokok
            $table->renameColumn('gaji', 'gaji_pokok');

            // Update column lengths to match new schema
            $table->string('nik', 16)->unique()->change();
            $table->string('nip', 20)->nullable()->unique()->change();
            $table->string('jabatan_saat_ini', 100)->change();
            $table->string('tingkat_pendidikan', 50)->change();

            // Add new columns
            $table->decimal('tunjangan', 15, 2)->nullable()->default(0)->after('gaji_pokok');
            $table->decimal('bonus', 15, 2)->nullable()->default(0)->after('tunjangan');
            $table->string('nomor_bpjs_kesehatan', 20)->nullable()->after('nomor_bpjs');
            $table->string('nomor_bpjs_ketenagakerjaan', 20)->nullable()->after('nomor_bpjs_kesehatan');
            $table->string('nomor_kontrak', 50)->nullable()->after('npwp');
            $table->date('tanggal_kontrak_berakhir')->nullable()->after('nomor_kontrak');

            // Drop the old nomor_bpjs column
            $table->dropColumn('nomor_bpjs');
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            // Revert column rename
            $table->renameColumn('gaji_pokok', 'gaji');

            // Revert column lengths
            $table->string('nik', 255)->unique()->change();
            $table->string('nip', 255)->nullable()->unique()->change();
            $table->string('jabatan_saat_ini', 255)->change();
            $table->string('tingkat_pendidikan', 255)->change();

            // Add back nomor_bpjs
            $table->string('nomor_bpjs', 255)->nullable()->after('gaji');

            // Drop new columns
            $table->dropColumn([
                'tunjangan',
                'bonus',
                'nomor_bpjs_kesehatan',
                'nomor_bpjs_ketenagakerjaan',
                'nomor_kontrak',
                'tanggal_kontrak_berakhir',
            ]);
        });
    }
}