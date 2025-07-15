<?php

namespace App\Http\Controllers;

use App\Models\NationalHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NationalHolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', date('Y'));
        
        // Ambil semua hari libur untuk tahun tersebut, bukan hanya bulan tertentu
        $holidays = NationalHoliday::active()->byYear($year)->orderBy('date')->get();
        
        return response()->json([
            'success' => true,
            'data' => $holidays
        ]);
    }

    public function store(Request $request)
    {
        // Log untuk debugging
        Log::info('🔄 Holiday store request received', [
            'raw_data' => $request->all(),
            'date_received' => $request->date,
            'user_timezone' => config('app.timezone')
        ]);
        
        // Cek apakah user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus login terlebih dahulu'
            ], 401);
        }
        
        // Cek akses HR dengan role yang lebih lengkap
        if (!in_array(Auth::user()->role, ['hr', 'hr_manager', 'admin', 'HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menambah hari libur'
            ], 403);
        }

        // CRITICAL FIX: Handle timezone properly
        $dateInput = $request->date;
        $processedDate = null;
        
        try {
            // Log informasi zona waktu untuk debugging
            Log::info('🌍 Timezone Debug Info', [
                'app_timezone' => config('app.timezone'),
                'server_timezone' => date_default_timezone_get(),
                'php_timezone' => ini_get('date.timezone'),
                'carbon_timezone' => Carbon::now()->getTimezone()->getName(),
                'input_date_raw' => $dateInput
            ]);
            
            // Parse tanggal yang dikirim dari frontend
            if (strpos($dateInput, 'T') !== false) {
                // Jika sudah format ISO dengan waktu
                // Gunakan zona waktu Asia/Jakarta secara eksplisit untuk konsistensi
                $carbonDate = Carbon::parse($dateInput, 'Asia/Jakarta');
                // PENTING: Ambil hanya bagian tanggal, abaikan waktu
                $processedDate = $carbonDate->format('Y-m-d');
                
                Log::info('📅 Date processing', [
                    'input' => $dateInput,
                    'parsed_carbon' => $carbonDate->toISOString(),
                    'carbon_timezone' => $carbonDate->getTimezone()->getName(),
                    'final_date' => $processedDate
                ]);
            } else {
                // Jika format YYYY-MM-DD biasa
                // Gunakan zona waktu Asia/Jakarta secara eksplisit
                $carbonDate = Carbon::parse($dateInput, 'Asia/Jakarta');
                $processedDate = $carbonDate->format('Y-m-d');
                
                Log::info('🗓️ Simple date processing', [
                    'input_date' => $dateInput,
                    'carbon_parsed' => $carbonDate->toDateTimeString(),
                    'processed_date' => $processedDate,
                    'carbon_timezone' => $carbonDate->getTimezone()->getName()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Date parsing error', [
                'input' => $dateInput,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid: ' . $e->getMessage()
            ], 400);
        }

        // Validasi dengan penanganan duplicate yang lebih baik
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:national,custom,weekend'
        ]);
        
        // PERBAIKAN DUPLICATE: Cek existing dengan handling yang lebih baik
        $existingHoliday = NationalHoliday::where('date', $processedDate)->first();
        
        if ($existingHoliday) {
            Log::warning('⚠️ Duplicate holiday detected', [
                'input_date' => $dateInput,
                'processed_date' => $processedDate,
                'existing_holiday' => [
                    'id' => $existingHoliday->id,
                    'name' => $existingHoliday->name,
                    'date' => $existingHoliday->date->format('Y-m-d'),
                    'type' => $existingHoliday->type
                ],
                'new_holiday_name' => $request->name
            ]);
            
            return response()->json([
                'success' => false,
                'message' => "Hari libur pada tanggal {$processedDate} sudah ada: {$existingHoliday->name}",
                'existing_holiday' => [
                    'id' => $existingHoliday->id,
                    'date' => $existingHoliday->date->format('Y-m-d'),
                    'name' => $existingHoliday->name,
                    'type' => $existingHoliday->type
                ],
                'debug_info' => [
                    'input_date' => $dateInput,
                    'processed_date' => $processedDate
                ]
            ], 409); // HTTP 409 Conflict
        }

        try {
            // Simpan hari libur baru dengan logging detail
            Log::info('💾 Creating new holiday', [
                'data_to_save' => [
                    'date' => $processedDate,
                    'name' => $request->name,
                    'description' => $request->description,
                    'type' => $request->type,
                    'created_by' => Auth::id()
                ]
            ]);
            
            $holiday = NationalHoliday::create([
                'date' => $processedDate, // Gunakan tanggal yang sudah diproses
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'created_by' => Auth::id()
            ]);
            
            // Refresh model untuk mendapatkan data terbaru dari database
            $holiday->refresh();
            
            // Verifikasi tanggal yang tersimpan dengan berbagai format
            $savedDate = $holiday->date;
            if (is_string($savedDate)) {
                $verifyDate = $savedDate;
            } else {
                $verifyDate = $savedDate->format('Y-m-d');
            }
                
            // Ambil data langsung dari database untuk verifikasi
            $dbRecord = DB::table('national_holidays')
                ->where('id', $holiday->id)
                ->first();
            
            Log::info('✅ Holiday created successfully', [
                'id' => $holiday->id,
                'input_date' => $dateInput,
                'processed_date' => $processedDate,
                'saved_date_model' => $verifyDate,
                'saved_date_db_raw' => $dbRecord->date ?? 'N/A',
                'name' => $holiday->name,
                'type' => $holiday->type,
                'date_match' => ($processedDate === $verifyDate) ? '✅' : '❌'
            ]);
            
            // Warning jika tanggal berubah
            if ($processedDate !== $verifyDate) {
                Log::error('🚨 TIMEZONE ISSUE IN BACKEND!', [
                    'expected' => $processedDate,
                    'saved' => $verifyDate,
                    'raw_saved' => $savedDate,
                    'db_raw' => $dbRecord->date ?? 'N/A'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Hari libur berhasil ditambahkan',
                'data' => [
                    'id' => $holiday->id,
                    'date' => $verifyDate, // Pastikan format konsisten
                    'name' => $holiday->name,
                    'description' => $holiday->description,
                    'type' => $holiday->type,
                    'created_by' => $holiday->created_by
                ],
                'debug_info' => [
                    'input_date' => $dateInput,
                    'processed_date' => $processedDate,
                    'saved_date' => $verifyDate
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Holiday creation failed', [
                'error' => $e->getMessage(),
                'date' => $processedDate,
                'name' => $request->name
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan hari libur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengedit hari libur'
            ], 403);
        }

        $holiday = NationalHoliday::findOrFail($id);
        
        // Tidak bisa edit hari libur nasional
        if ($holiday->type === 'national') {
            return response()->json([
                'success' => false,
                'message' => 'Hari libur nasional tidak dapat diedit'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $holiday->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active,
            'updated_by' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil diperbarui',
            'data' => $holiday
        ]);
    }

    public function destroy($id)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus hari libur'
            ], 403);
        }

        $holiday = NationalHoliday::findOrFail($id);
        
        // Tidak bisa hapus hari libur nasional
        if ($holiday->type === 'national') {
            return response()->json([
                'success' => false,
                'message' => 'Hari libur nasional tidak dapat dihapus'
            ], 403);
        }

        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus'
        ]);
    }

    public function checkHoliday(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $isHoliday = NationalHoliday::isHoliday($date);
        $holidayName = NationalHoliday::getHolidayName($date);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'is_holiday' => $isHoliday,
                'holiday_name' => $holidayName
            ]
        ]);
    }

    public function getCalendarData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('n'));
        
        $holidays = NationalHoliday::getHolidaysByMonth($year, $month);
        $calendarData = NationalHoliday::getCalendarData($year, $month);
        
        return response()->json([
            'success' => true,
            'data' => [
                'calendar' => $calendarData,
                'holidays' => $holidays
            ]
        ]);
    }

    /**
     * Get calendar data untuk frontend (sesuai dengan frontend yang sudah ada)
     */
    public function getCalendarDataForFrontend(Request $request)
    {
        $year = $request->get('year', date('Y'));
        
        // Get holidays untuk tahun tersebut (semua bulan), bukan hanya bulan tertentu
        $holidays = NationalHoliday::active()->byYear($year)->orderBy('date')->get();
        
        // Convert ke format yang diharapkan frontend
        $holidaysMap = [];
        foreach ($holidays as $holiday) {
            $holidaysMap[$holiday->date->format('Y-m-d')] = [
                'id' => $holiday->id,
                'date' => $holiday->date->format('Y-m-d'),
                'name' => $holiday->name,
                'description' => $holiday->description,
                'type' => $holiday->type,
                'is_active' => $holiday->is_active,
                'created_by' => $holiday->created_by,
                'updated_by' => $holiday->updated_by
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $holidaysMap
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    public function seedHolidays(Request $request)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk seed hari libur'
            ], 403);
        }

        $year = $request->get('year', date('Y'));
        NationalHoliday::seedNationalHolidays($year);

        return response()->json([
            'success' => true,
            'message' => "Hari libur nasional tahun {$year} berhasil di-seed"
        ]);
    }

    public function getAvailableYears()
    {
        $years = NationalHoliday::getAvailableYears();
        
        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }



    public function getYearlySummary(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $summary = NationalHoliday::getYearlyHolidaySummary($year);
        
        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'summary' => $summary
            ]
        ]);
    }

    public function getYearlyHolidays(Request $request)
    {
        $year = $request->get('year', date('Y'));
        
        // Get all holidays for the year, not just specific month
        $holidays = NationalHoliday::active()->byYear($year)->orderBy('date')->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'holidays' => $holidays
            ]
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    public function bulkSeedYears(Request $request)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk bulk seed hari libur'
            ], 403);
        }

        $request->validate([
            'years' => 'required|array',
            'years.*' => 'integer|min:2020|max:2030'
        ]);

        $years = $request->years;
        $seededYears = [];

        foreach ($years as $year) {
            NationalHoliday::seedNationalHolidays($year);
            $seededYears[] = $year;
        }

        return response()->json([
            'success' => true,
            'message' => 'Hari libur nasional berhasil di-seed untuk tahun: ' . implode(', ', $seededYears),
            'data' => [
                'seeded_years' => $seededYears
            ]
        ]);
    }

    public function createRecurringHoliday(Request $request)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membuat hari libur berulang'
            ], 403);
        }

        $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6', // 0=Minggu, 1=Senin, ..., 6=Sabtu
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_year' => 'nullable|integer|min:2020|max:2030',
            'end_year' => 'nullable|integer|min:2020|max:2030'
        ]);

        $dayOfWeek = $request->day_of_week;
        $startYear = $request->start_year ?? date('Y');
        $endYear = $request->end_year ?? ($startYear + 1);

        // Validasi end_year tidak boleh lebih kecil dari start_year
        if ($endYear < $startYear) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun akhir tidak boleh lebih kecil dari tahun awal'
            ], 422);
        }

        $createdCount = NationalHoliday::createRecurringHoliday(
            $dayOfWeek,
            $request->name,
            $request->description,
            $startYear,
            $endYear
        );

        $dayName = NationalHoliday::getDayOfWeekName($dayOfWeek);

        return response()->json([
            'success' => true,
            'message' => "Hari libur {$dayName} berhasil dibuat untuk {$createdCount} hari",
            'data' => [
                'day_of_week' => $dayOfWeek,
                'day_name' => $dayName,
                'created_count' => $createdCount,
                'start_year' => $startYear,
                'end_year' => $endYear
            ]
        ]);
    }

    public function createMonthlyHoliday(Request $request)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membuat hari libur bulanan'
            ], 403);
        }

        $request->validate([
            'day_of_month' => 'required|integer|min:1|max:31',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_year' => 'nullable|integer|min:2020|max:2030',
            'end_year' => 'nullable|integer|min:2020|max:2030'
        ]);

        $dayOfMonth = $request->day_of_month;
        $startYear = $request->start_year ?? date('Y');
        $endYear = $request->end_year ?? ($startYear + 1);

        // Validasi end_year tidak boleh lebih kecil dari start_year
        if ($endYear < $startYear) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun akhir tidak boleh lebih kecil dari tahun awal'
            ], 422);
        }

        $createdCount = NationalHoliday::createMonthlyHoliday(
            $dayOfMonth,
            $request->name,
            $request->description,
            $startYear,
            $endYear
        );

        return response()->json([
            'success' => true,
            'message' => "Hari libur tanggal {$dayOfMonth} setiap bulan berhasil dibuat untuk {$createdCount} hari",
            'data' => [
                'day_of_month' => $dayOfMonth,
                'created_count' => $createdCount,
                'start_year' => $startYear,
                'end_year' => $endYear
            ]
        ]);
    }

    public function createDateRangeHoliday(Request $request)
    {
        // Cek apakah user adalah HR
        if (!in_array(Auth::user()->role, ['HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membuat hari libur rentang tanggal'
            ], 403);
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $createdCount = NationalHoliday::createDateRangeHoliday(
            $request->start_date,
            $request->end_date,
            $request->name,
            $request->description
        );

        return response()->json([
            'success' => true,
            'message' => "Hari libur rentang tanggal berhasil dibuat untuk {$createdCount} hari",
            'data' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_count' => $createdCount
            ]
        ]);
    }

    public function getCustomHolidays(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $type = $request->get('type', 'custom');
        
        $holidays = NationalHoliday::active()
            ->byType($type)
            ->byYear($year)
            ->orderBy('date')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'type' => $type,
                'holidays' => $holidays
            ]
        ]);
    }

    public function getHolidayTypes()
    {
        $types = [
            'national' => 'Hari Libur Nasional',
            'custom' => 'Hari Libur Kustom',
            'weekend' => 'Hari Libur Weekend'
        ];
        
        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }
}
