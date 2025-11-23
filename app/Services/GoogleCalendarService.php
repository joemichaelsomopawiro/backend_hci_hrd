<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\NationalHoliday;
use Carbon\Carbon;

class GoogleCalendarService
{
    protected $apiKey;
    protected $calendarId;
    protected $baseUrl = 'https://www.googleapis.com/calendar/v3';

    public function __construct()
    {
        $this->apiKey = config('services.google.calendar_api_key');
        $rawCalendarId = config('services.google.calendar_id', 'id.indonesian#holiday@group.v.calendar.google.com');
        
        // Fix: Jika Calendar ID tidak lengkap (kurang dari 40 karakter), gunakan default lengkap
        // Ini terjadi jika karakter # di .env dianggap sebagai komentar
        if (strlen($rawCalendarId) < 40) {
            \Illuminate\Support\Facades\Log::warning('Calendar ID from config is incomplete, using default', [
                'config_calendar_id' => $rawCalendarId,
                'config_length' => strlen($rawCalendarId),
                'using_default' => 'id.indonesian#holiday@group.v.calendar.google.com'
            ]);
            $this->calendarId = 'id.indonesian#holiday@group.v.calendar.google.com';
        } else {
            $this->calendarId = $rawCalendarId;
        }
        
        // Validasi API key
        if (empty($this->apiKey)) {
            \Illuminate\Support\Facades\Log::warning('Google Calendar API key is empty');
        }
        
        // Log untuk debugging
        \Illuminate\Support\Facades\Log::info('GoogleCalendarService initialized', [
            'calendar_id' => $this->calendarId,
            'calendar_id_length' => strlen($this->calendarId),
            'has_api_key' => !empty($this->apiKey),
            'api_key_preview' => !empty($this->apiKey) ? substr($this->apiKey, 0, 10) . '...' : 'empty'
        ]);
    }

    /**
     * Fetch hari libur nasional dari Google Calendar API
     */
    public function fetchNationalHolidays($year)
    {
        $cacheKey = "google_calendar_holidays_{$year}";
        
        // Cek cache dulu
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $startDate = "{$year}-01-01T00:00:00Z";
            $endDate = "{$year}-12-31T23:59:59Z";
            
            // Encode calendar ID untuk URL
            // Format: id.indonesian#holiday@group.v.calendar.google.com
            // Perlu encode karakter # menjadi %23 dan @ menjadi %40
            // Pastikan Calendar ID lengkap sebelum encode
            if (empty($this->calendarId) || strlen($this->calendarId) < 40) {
                // Fallback ke default jika masih tidak lengkap
                $this->calendarId = 'id.indonesian#holiday@group.v.calendar.google.com';
                \Illuminate\Support\Facades\Log::warning('Using fallback Calendar ID', [
                    'calendar_id' => $this->calendarId
                ]);
            }
            
            $encodedCalendarId = str_replace(['#', '@'], ['%23', '%40'], $this->calendarId);
            $url = "{$this->baseUrl}/calendars/{$encodedCalendarId}/events";
            $params = [
                'key' => $this->apiKey,
                'timeMin' => $startDate,
                'timeMax' => $endDate,
                'singleEvents' => 'true',
                'orderBy' => 'startTime'
            ];
            
            \Illuminate\Support\Facades\Log::info('Google Calendar API Request', [
                'url' => $url,
                'calendar_id' => $this->calendarId,
                'encoded_calendar_id' => $encodedCalendarId,
                'has_api_key' => !empty($this->apiKey),
                'api_key_preview' => !empty($this->apiKey) ? substr($this->apiKey, 0, 10) . '...' : 'empty'
            ]);

            $response = Http::timeout(10)->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                $holidays = $this->parseGoogleCalendarEvents($data['items'] ?? []);
                
                // Cache hasil selama 24 jam
                Cache::put($cacheKey, $holidays, now()->addHours(24));
                
                return $holidays;
            }

            // Jika API gagal, log error dan throw exception
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            $errorDetails = $errorBody['error'] ?? [];
            
            \Illuminate\Support\Facades\Log::error('Google Calendar API Error', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'error_details' => $errorDetails,
                'url' => $url,
                'full_url' => $url . '?' . http_build_query($params),
                'has_api_key' => !empty($this->apiKey),
                'calendar_id' => $this->calendarId,
                'encoded_calendar_id' => $encodedCalendarId,
                'response_body' => $response->body()
            ]);
            
            // Throw exception agar error ter-handle di controller
            throw new \Exception("Google Calendar API Error: {$errorMessage} (Status: {$response->status()})");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google Calendar API Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw exception agar ter-handle di controller
            throw $e;
        }
    }

    /**
     * Parse events dari Google Calendar API
     */
    protected function parseGoogleCalendarEvents($events)
    {
        $holidays = [];

        foreach ($events as $event) {
            $startDate = $event['start']['date'] ?? null;
            if (!$startDate) continue;

            $summary = $event['summary'] ?? 'Holiday';
            $description = $event['description'] ?? '';
            
            // Deteksi cuti bersama dari nama/description
            $isCutiBersama = stripos($summary, 'cuti bersama') !== false || 
                            stripos($description, 'cuti bersama') !== false;
            
            // Deteksi perayaan (bukan hari libur nasional)
            $isPerayaan = stripos($description, 'Perayaan') !== false ||
                         stripos($summary, 'Malam') !== false;

            $holidays[] = [
                'date' => $startDate,
                'name' => $summary,
                'description' => $description,
                'type' => $isCutiBersama ? 'cuti_bersama' : ($isPerayaan ? 'perayaan' : 'national'),
                'is_active' => true,
                'google_event_id' => $event['id'] ?? null
            ];
        }

        return $holidays;
    }

    /**
     * Data statis hari libur nasional sebagai fallback
     */
    protected function getStaticHolidays($year)
    {
        // Hari libur tetap (tanggal sama setiap tahun)
        $fixedHolidays = [
            '01-01' => 'Tahun Baru',
            '05-01' => 'Hari Buruh Internasional',
            '08-17' => 'Hari Kemerdekaan RI',
            '12-25' => 'Hari Raya Natal',
        ];

        // Hari libur variabel (perlu update manual setiap tahun)
        $variableHolidays = [
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

        $holidays = [];

        // Tambahkan hari libur tetap
        foreach ($fixedHolidays as $date => $name) {
            $holidays[] = [
                'date' => "{$year}-{$date}",
                'name' => $name,
                'description' => null,
                'type' => 'national',
                'is_active' => true,
                'google_event_id' => null
            ];
        }

        // Tambahkan hari libur variabel jika ada data untuk tahun tersebut
        if (isset($variableHolidays[$year])) {
            foreach ($variableHolidays[$year] as $date => $name) {
                $holidays[] = [
                    'date' => "{$year}-{$date}",
                    'name' => $name,
                    'description' => null,
                    'type' => 'national',
                    'is_active' => true,
                    'google_event_id' => null
                ];
            }
        }

        return $holidays;
    }

    /**
     * Sync hari libur nasional ke database
     */
    public function syncNationalHolidays($year)
    {
        $holidays = $this->fetchNationalHolidays($year);
        $syncedCount = 0;

        foreach ($holidays as $holiday) {
            $existing = NationalHoliday::where('date', $holiday['date'])
                ->where('type', 'national')
                ->first();

            if ($existing) {
                // Update jika ada perubahan
                $existing->update([
                    'name' => $holiday['name'],
                    'description' => $holiday['description'],
                    'is_active' => $holiday['is_active']
                ]);
            } else {
                // Create baru
                NationalHoliday::create([
                    'date' => $holiday['date'],
                    'name' => $holiday['name'],
                    'description' => $holiday['description'],
                    'type' => 'national',
                    'is_active' => $holiday['is_active']
                ]);
            }
            $syncedCount++;
        }

        return [
            'success' => true,
            'message' => "Berhasil sync {$syncedCount} hari libur nasional untuk tahun {$year}",
            'synced_count' => $syncedCount,
            'year' => $year
        ];
    }

    /**
     * Clear cache untuk tahun tertentu
     */
    public function clearCache($year = null)
    {
        if ($year) {
            Cache::forget("google_calendar_holidays_{$year}");
        } else {
            // Clear semua cache calendar
            for ($y = 2020; $y <= 2030; $y++) {
                Cache::forget("google_calendar_holidays_{$y}");
            }
        }

        return [
            'success' => true,
            'message' => $year ? "Cache cleared for year {$year}" : "All calendar cache cleared"
        ];
    }

    /**
     * Test koneksi ke Google Calendar API
     */
    public function testConnection()
    {
        try {
            $year = date('Y');
            $startDate = "{$year}-01-01T00:00:00Z";
            $endDate = "{$year}-01-31T23:59:59Z";
            
            // Encode calendar ID untuk URL
            $encodedCalendarId = str_replace(['#', '@'], ['%23', '%40'], $this->calendarId);
            $url = "{$this->baseUrl}/calendars/{$encodedCalendarId}/events";
            $params = [
                'key' => $this->apiKey,
                'timeMin' => $startDate,
                'timeMax' => $endDate,
                'maxResults' => 1
            ];

            $response = Http::timeout(10)->get($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Google Calendar API connection successful',
                    'status_code' => $response->status(),
                    'has_api_key' => !empty($this->apiKey),
                    'calendar_id' => $this->calendarId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Google Calendar API connection failed',
                    'status_code' => $response->status(),
                    'error' => $response->body(),
                    'has_api_key' => !empty($this->apiKey),
                    'calendar_id' => $this->calendarId
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Google Calendar API connection error',
                'error' => $e->getMessage(),
                'has_api_key' => !empty($this->apiKey),
                'calendar_id' => $this->calendarId
            ];
        }
    }
} 