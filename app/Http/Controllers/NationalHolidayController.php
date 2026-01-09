<?php

namespace App\Http\Controllers;

use App\Models\NationalHoliday;
use App\Services\GoogleCalendarService;
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
        // Cek apakah user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus login terlebih dahulu'
            ], 401);
        }
        
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menambah hari libur'
            ], 403);
        }

        // Validasi input
        $request->validate([
            'date' => 'required|date|unique:national_holidays,date',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:national,custom,weekend'
        ]);

        $holiday = NationalHoliday::create([
            'date' => $request->date,
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'created_by' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan',
            'data' => $holiday
        ]);
    }

    public function update(Request $request, $id)
    {
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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
        // Cek apakah user adalah HR atau Program Manager
        if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
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

    /**
     * Get national holidays from Google Calendar API
     * Endpoint: GET /api/calendar/national-holidays?year=2025
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNationalHolidays(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            
            // Validasi year
            if (!is_numeric($year) || $year < 2020 || $year > 2100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid year parameter. Year must be between 2020 and 2100'
                ], 400);
            }
            
            // Cek apakah API key sudah di-setup
            $apiKey = config('services.google.calendar_api_key');
            if (empty($apiKey)) {
                Log::warning('Google Calendar API key not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Google Calendar API key not configured. Please add GOOGLE_CALENDAR_API_KEY to .env file',
                    'data' => []
                ], 500);
            }
            
            $googleCalendarService = new GoogleCalendarService();
            $holidays = $googleCalendarService->fetchNationalHolidays($year);
            
            // Pastikan holidays adalah array
            if (!is_array($holidays)) {
                $holidays = [];
            }
            
            return response()->json([
                'success' => true,
                'data' => $holidays,
                'year' => (int)$year,
                'count' => count($holidays)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Calendar Controller Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch national holidays from Google Calendar API',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'data' => []
            ], 500);
        }
    }

    /**
     * Test koneksi ke Google Calendar API
     * Endpoint: GET /api/calendar/test-google-connection
     */
    public function testGoogleCalendarConnection()
    {
        try {
            $googleCalendarService = new GoogleCalendarService();
            $result = $googleCalendarService->testConnection();
            
            return response()->json($result, $result['success'] ? 200 : 500);
            
        } catch (\Exception $e) {
            Log::error('Test Google Calendar Connection Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error testing Google Calendar connection',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Sync hari libur dari Google Calendar
     * Endpoint: POST /api/calendar/sync-google?year=2025
     */
    public function syncFromGoogleCalendar(Request $request)
    {
        try {
            // Cek apakah user adalah HR atau Program Manager
            if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk sync hari libur'
                ], 403);
            }

            $year = $request->get('year', date('Y'));
            
            $googleCalendarService = new GoogleCalendarService();
            $result = $googleCalendarService->syncNationalHolidays($year);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Sync Google Calendar Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync from Google Calendar',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Clear cache Google Calendar
     * Endpoint: POST /api/calendar/clear-google-cache?year=2025
     */
    public function clearGoogleCalendarCache(Request $request)
    {
        try {
            // Cek apakah user adalah HR atau Program Manager
            if (!in_array(Auth::user()->role, ['HR', 'Program Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk clear cache'
                ], 403);
            }

            $year = $request->get('year');
            
            $googleCalendarService = new GoogleCalendarService();
            $result = $googleCalendarService->clearCache($year);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Clear Google Calendar Cache Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
