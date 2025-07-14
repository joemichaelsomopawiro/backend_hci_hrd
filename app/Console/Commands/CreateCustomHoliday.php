<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NationalHoliday;
use Carbon\Carbon;

class CreateCustomHoliday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:create-custom-holiday 
                            {--type=recurring : Tipe hari libur (recurring/monthly/date-range)}
                            {--day-of-week= : Hari dalam minggu (0=Minggu, 1=Senin, ..., 6=Sabtu)}
                            {--day-of-month= : Tanggal dalam bulan (1-31)}
                            {--start-date= : Tanggal mulai (untuk date-range)}
                            {--end-date= : Tanggal akhir (untuk date-range)}
                            {--name= : Nama hari libur}
                            {--description= : Deskripsi hari libur}
                            {--start-year= : Tahun mulai (default: tahun ini)}
                            {--end-year= : Tahun akhir (default: tahun berikutnya)}
                            {--force : Force create tanpa konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat hari libur kustom (berulang, bulanan, atau rentang tanggal)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $force = $this->option('force');

        $this->info('=== MEMBUAT HARI LIBUR KUSTOM ===');

        switch ($type) {
            case 'recurring':
                $this->createRecurringHoliday($force);
                break;
            case 'monthly':
                $this->createMonthlyHoliday($force);
                break;
            case 'date-range':
                $this->createDateRangeHoliday($force);
                break;
            default:
                $this->error('Tipe tidak valid. Gunakan: recurring, monthly, atau date-range');
                return;
        }
    }

    private function createRecurringHoliday($force)
    {
        $dayOfWeek = $this->option('day-of-week');
        $name = $this->option('name');
        $description = $this->option('description');
        $startYear = $this->option('start-year') ?? date('Y');
        $endYear = $this->option('end-year') ?? ($startYear + 1);

        if (!$dayOfWeek || !$name) {
            $this->error('Parameter --day-of-week dan --name wajib diisi');
            return;
        }

        $dayName = NationalHoliday::getDayOfWeekName($dayOfWeek);
        $this->info("Membuat hari libur {$dayName} ({$name})");
        $this->info("Periode: {$startYear} - {$endYear}");

        if (!$force && !$this->confirm('Apakah Anda yakin ingin melanjutkan?')) {
            $this->info('Operasi dibatalkan.');
            return;
        }

        $createdCount = NationalHoliday::createRecurringHoliday(
            $dayOfWeek,
            $name,
            $description,
            $startYear,
            $endYear
        );

        $this->info("✓ Berhasil membuat {$createdCount} hari libur {$dayName}");
    }

    private function createMonthlyHoliday($force)
    {
        $dayOfMonth = $this->option('day-of-month');
        $name = $this->option('name');
        $description = $this->option('description');
        $startYear = $this->option('start-year') ?? date('Y');
        $endYear = $this->option('end-year') ?? ($startYear + 1);

        if (!$dayOfMonth || !$name) {
            $this->error('Parameter --day-of-month dan --name wajib diisi');
            return;
        }

        $this->info("Membuat hari libur tanggal {$dayOfMonth} setiap bulan ({$name})");
        $this->info("Periode: {$startYear} - {$endYear}");

        if (!$force && !$this->confirm('Apakah Anda yakin ingin melanjutkan?')) {
            $this->info('Operasi dibatalkan.');
            return;
        }

        $createdCount = NationalHoliday::createMonthlyHoliday(
            $dayOfMonth,
            $name,
            $description,
            $startYear,
            $endYear
        );

        $this->info("✓ Berhasil membuat {$createdCount} hari libur tanggal {$dayOfMonth}");
    }

    private function createDateRangeHoliday($force)
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $name = $this->option('name');
        $description = $this->option('description');

        if (!$startDate || !$endDate || !$name) {
            $this->error('Parameter --start-date, --end-date, dan --name wajib diisi');
            return;
        }

        $this->info("Membuat hari libur rentang tanggal ({$name})");
        $this->info("Periode: {$startDate} - {$endDate}");

        if (!$force && !$this->confirm('Apakah Anda yakin ingin melanjutkan?')) {
            $this->info('Operasi dibatalkan.');
            return;
        }

        $createdCount = NationalHoliday::createDateRangeHoliday(
            $startDate,
            $endDate,
            $name,
            $description
        );

        $this->info("✓ Berhasil membuat {$createdCount} hari libur rentang tanggal");
    }
}
