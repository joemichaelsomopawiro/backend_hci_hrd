<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NationalHoliday;
use Carbon\Carbon;

class UpdateNationalHolidays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:update-holidays 
                            {--year= : Tahun yang akan diupdate (default: tahun berikutnya)}
                            {--years=* : Multiple tahun yang akan diupdate}
                            {--force : Force update tanpa konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update kalender hari libur nasional untuk tahun-tahun berikutnya';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $years = $this->option('years');
        $singleYear = $this->option('year');
        $force = $this->option('force');

        // Jika tidak ada parameter tahun, gunakan tahun berikutnya
        if (empty($years) && empty($singleYear)) {
            $years = [Carbon::now()->addYear()->year];
        } elseif (!empty($singleYear)) {
            $years = [$singleYear];
        }

        $this->info('=== UPDATE KALENDER HARI LIBUR NASIONAL ===');
        $this->info('Tahun yang akan diupdate: ' . implode(', ', $years));

        if (!$force) {
            if (!$this->confirm('Apakah Anda yakin ingin melanjutkan?')) {
                $this->info('Operasi dibatalkan.');
                return;
            }
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($years as $year) {
            try {
                $this->info("\nMemproses tahun {$year}...");
                
                // Cek apakah tahun sudah ada
                $existingHolidays = NationalHoliday::byYear($year)->count();
                if ($existingHolidays > 0) {
                    $this->warn("Tahun {$year} sudah memiliki {$existingHolidays} hari libur.");
                    if (!$force && !$this->confirm("Apakah Anda ingin menimpa data tahun {$year}?")) {
                        $this->info("Tahun {$year} dilewati.");
                        continue;
                    }
                }

                // Seed hari libur untuk tahun tersebut
                NationalHoliday::seedNationalHolidays($year);
                
                $newHolidays = NationalHoliday::byYear($year)->count();
                $this->info("✓ Tahun {$year} berhasil diupdate dengan {$newHolidays} hari libur.");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("✗ Error saat memproses tahun {$year}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->info("\n=== HASIL UPDATE ===");
        $this->info("Berhasil: {$successCount} tahun");
        $this->info("Gagal: {$errorCount} tahun");

        if ($successCount > 0) {
            $this->info("\nUntuk melihat hasil, gunakan:");
            $this->comment("php artisan tinker");
            $this->comment("App\\Models\\NationalHoliday::getAvailableYears();");
        }
    }
}
