<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class NationalHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'description',
        'type',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_active' => 'boolean'
    ];
    
    /**
     * Prepare a date for array / JSON serialization.
     * Memastikan tanggal selalu dalam format Y-m-d tanpa komponen waktu
     * untuk mencegah masalah pergeseran tanggal karena zona waktu.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d');
    }

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByYear($query, $year)
    {
        return $query->whereYear('date', $year);
    }

    public function scopeByMonth($query, $year, $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    // Helper methods
    public static function isHoliday($date)
    {
        $date = Carbon::parse($date);
        
        // Cek weekend
        if ($date->isWeekend()) {
            return true;
        }
        
        // Cek hari libur nasional/kustom
        return self::active()->where('date', $date->format('Y-m-d'))->exists();
    }

    public static function getHolidayName($date)
    {
        $date = Carbon::parse($date);
        
        // Cek weekend
        if ($date->isWeekend()) {
            return $date->isSaturday() ? 'Sabtu' : 'Minggu';
        }
        
        // Cek hari libur nasional/kustom
        $holiday = self::active()->where('date', $date->format('Y-m-d'))->first();
        return $holiday ? $holiday->name : null;
    }

    public static function getHolidaysByMonth($year, $month)
    {
        return self::active()
            ->byMonth($year, $month)
            ->orderBy('date')
            ->get();
    }

    public static function seedNationalHolidays($year)
    {
        // Data hari libur nasional yang bisa digunakan untuk tahun-tahun berikutnya
        $holidayTemplates = [
            // Hari libur tetap (tanggal sama setiap tahun)
            '01-01' => 'Tahun Baru',
            '05-01' => 'Hari Buruh Internasional',
            '08-17' => 'Hari Kemerdekaan RI',
            '12-25' => 'Hari Raya Natal',
        ];

        // Hari libur yang bergantung pada kalender Hijriyah/Imlek (perlu update manual)
        $variableHolidays = [
            // Contoh untuk 2024 - perlu update setiap tahun
            '2024' => [
                '02-08' => 'Isra Mikraj Nabi Muhammad SAW',
                '02-10' => 'Tahun Baru Imlek 2575',
                '03-11' => 'Hari Suci Nyepi',
                '04-10' => 'Hari Raya Idul Fitri',
                '04-11' => 'Hari Raya Idul Fitri',
                '05-09' => 'Hari Raya Waisak',
                '05-23' => 'Hari Raya Idul Adha',
                '07-19' => 'Tahun Baru Islam 1446 Hijriyah',
                '09-28' => 'Maulid Nabi Muhammad SAW',
            ],
            // Template untuk 2025 (perlu update dengan data resmi)
            '2025' => [
                '01-28' => 'Tahun Baru Imlek 2576',
                '03-01' => 'Hari Suci Nyepi',
                '03-30' => 'Isra Mikraj Nabi Muhammad SAW',
                '03-31' => 'Hari Raya Idul Fitri',
                '04-01' => 'Hari Raya Idul Fitri',
                '05-03' => 'Hari Raya Waisak',
                '06-07' => 'Hari Raya Idul Adha',
                '07-09' => 'Tahun Baru Islam 1447 Hijriyah',
                '09-18' => 'Maulid Nabi Muhammad SAW',
            ],
            // Template untuk 2026 (perlu update dengan data resmi)
            '2026' => [
                '02-17' => 'Tahun Baru Imlek 2577',
                '03-21' => 'Hari Suci Nyepi',
                '03-20' => 'Isra Mikraj Nabi Muhammad SAW',
                '03-21' => 'Hari Raya Idul Fitri',
                '03-22' => 'Hari Raya Idul Fitri',
                '04-22' => 'Hari Raya Waisak',
                '05-27' => 'Hari Raya Idul Adha',
                '06-29' => 'Tahun Baru Islam 1448 Hijriyah',
                '09-07' => 'Maulid Nabi Muhammad SAW',
            ]
        ];

        // Tambahkan hari libur tetap
        foreach ($holidayTemplates as $date => $name) {
            $fullDate = "{$year}-{$date}";
            self::updateOrCreate(
                ['date' => $fullDate],
                [
                    'name' => $name,
                    'type' => 'national',
                    'is_active' => true
                ]
            );
        }

        // Tambahkan hari libur variabel jika ada data untuk tahun tersebut
        if (isset($variableHolidays[$year])) {
            foreach ($variableHolidays[$year] as $date => $name) {
                $fullDate = "{$year}-{$date}";
                self::updateOrCreate(
                    ['date' => $fullDate],
                    [
                        'name' => $name,
                        'type' => 'national',
                        'is_active' => true
                    ]
                );
            }
        }
    }

    public static function getAvailableYears()
    {
        return self::selectRaw('DISTINCT YEAR(date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year');
    }

    public static function getYearlyHolidaySummary($year)
    {
        $holidays = self::active()->byYear($year)->get();
        
        $summary = [
            'total_holidays' => $holidays->count(),
            'national_holidays' => $holidays->where('type', 'national')->count(),
            'custom_holidays' => $holidays->where('type', 'custom')->count(),
            'weekend_holidays' => $holidays->where('type', 'weekend')->count(),
            'holidays_by_month' => []
        ];

        // Group by month
        for ($month = 1; $month <= 12; $month++) {
            $monthHolidays = $holidays->filter(function ($holiday) use ($month) {
                return $holiday->date->month === $month;
            });
            
            $summary['holidays_by_month'][$month] = [
                'count' => $monthHolidays->count(),
                'holidays' => $monthHolidays->map(function ($holiday) {
                    return [
                        'date' => $holiday->date->format('Y-m-d'),
                        'name' => $holiday->name,
                        'type' => $holiday->type
                    ];
                })->values()
            ];
        }

        return $summary;
    }

    public static function createRecurringHoliday($dayOfWeek, $name, $description = null, $startYear = null, $endYear = null)
    {
        // dayOfWeek: 0 = Minggu, 1 = Senin, ..., 6 = Sabtu
        $startYear = $startYear ?? date('Y');
        $endYear = $endYear ?? ($startYear + 1);
        
        $createdCount = 0;
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $startDate = Carbon::create($year, 1, 1);
            $endDate = Carbon::create($year, 12, 31);
            
            // Cari semua tanggal dengan hari yang sama
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if ($date->dayOfWeek === (int)$dayOfWeek) {
                    // Cek apakah sudah ada hari libur di tanggal ini
                    $existingHoliday = self::where('date', $date->format('Y-m-d'))->first();
                    
                    if (!$existingHoliday) {
                        self::create([
                            'date' => $date->format('Y-m-d'),
                            'name' => $name,
                            'description' => $description,
                            'type' => 'custom',
                            'is_active' => true,
                            'created_by' => 1 // Default user ID
                        ]);
                        $createdCount++;
                    }
                }
            }
        }
        
        return $createdCount;
    }

    public static function createMonthlyHoliday($dayOfMonth, $name, $description = null, $startYear = null, $endYear = null)
    {
        // dayOfMonth: 1-31 (tanggal dalam bulan)
        $startYear = $startYear ?? date('Y');
        $endYear = $endYear ?? ($startYear + 1);
        
        $createdCount = 0;
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                // Cek apakah tanggal valid untuk bulan tersebut
                if ($dayOfMonth <= Carbon::create($year, $month)->daysInMonth) {
                    $date = Carbon::create($year, $month, $dayOfMonth);
                    
                    // Cek apakah sudah ada hari libur nasional di tanggal ini
                    $existingHoliday = self::where('date', $date->format('Y-m-d'))->first();
                    
                    if (!$existingHoliday) {
                        self::create([
                            'date' => $date->format('Y-m-d'),
                            'name' => $name,
                            'description' => $description,
                            'type' => 'custom',
                            'is_active' => true
                        ]);
                        $createdCount++;
                    }
                }
            }
        }
        
        return $createdCount;
    }

    public static function createDateRangeHoliday($startDate, $endDate, $name, $description = null)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $createdCount = 0;
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // Cek apakah sudah ada hari libur nasional di tanggal ini
            $existingHoliday = self::where('date', $date->format('Y-m-d'))->first();
            
            if (!$existingHoliday) {
                self::create([
                    'date' => $date->format('Y-m-d'),
                    'name' => $name,
                    'description' => $description,
                    'type' => 'custom',
                    'is_active' => true
                ]);
                $createdCount++;
            }
        }
        
        return $createdCount;
    }

    public static function getDayOfWeekName($dayOfWeek)
    {
        $days = [
            0 => 'Minggu',
            1 => 'Senin', 
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu'
        ];
        
        return $days[$dayOfWeek] ?? 'Tidak diketahui';
    }

    public static function getCalendarData($year, $month)
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        
        $calendarData = [];
        
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $isHoliday = self::isHoliday($dateStr);
            $holidayName = self::getHolidayName($dateStr);
            
            $calendarData[] = [
                'date' => $dateStr,
                'day' => $date->day,
                'is_holiday' => $isHoliday,
                'holiday_name' => $holidayName,
                'is_weekend' => $date->isWeekend(),
                'is_today' => $date->isToday()
            ];
        }
        
        return $calendarData;
    }
}
