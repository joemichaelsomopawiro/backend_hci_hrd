<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TxtAttendanceUploadService
{
    // Mapping posisi kolom fixed sesuai file real user
    private $fixedColumns = [
        'card_number'    => [0, 12],   // No. ID
        'user_name'      => [13, 36],  // Nama
        'date'           => [37, 47],  // Tanggal
        'check_in'       => [48, 57],  // Scan Masuk
        'check_out'      => [58, 68],  // Scan Pulang
        'absent'         => [69, 76],  // Absent
        'work_hours'     => [77, 89],  // Jml Jam Kerja
        'jml_kehadiran'  => [90, 102], // Jml Kehadiran
    ];

    /**
     * Preview data TXT (10 baris pertama + header)
     */
    public function previewTxtData(UploadedFile $file): array
    {
        $lines = $this->getLines($file);
        // Cari header (baris pertama non-kosong)
        $headerLineIndex = 0;
        while ($headerLineIndex < count($lines) && trim($lines[$headerLineIndex]) === '') {
            $headerLineIndex++;
        }
        if ($headerLineIndex >= count($lines) - 1) {
            return [
                'success' => false,
                'message' => 'File tidak berisi data yang valid. Minimal ada header dan 1 baris data.'
            ];
        }
        $header = $this->parseHeader($lines[$headerLineIndex]);
        if (count($header) < 2) {
            return [
                'success' => false,
                'message' => 'Header file TXT tidak valid atau tidak terdeteksi. Pastikan baris pertama adalah header.'
            ];
        }
        // Cari baris data pertama (setelah header dan baris kosong pemisah)
        $dataStart = $headerLineIndex + 1;
        while ($dataStart < count($lines) && trim($lines[$dataStart]) === '') {
            $dataStart++;
        }
        $preview = [];
        $errors = [];
        $maxPreview = 10;
        $rowNum = 1;
        for ($i = $dataStart; $i < count($lines) && count($preview) < $maxPreview; $i++) {
            if (trim($lines[$i]) === '') continue; // SKIP baris kosong di mana pun
            $row = $this->parseLine($lines[$i]);
            if ($row['valid']) {
                $preview[] = $row['data'];
            } else {
                $errors[] = [
                    'row' => $i + 1,
                    'message' => $row['error']
                ];
            }
            $rowNum++;
        }
        return [
            'success' => true,
            'header' => $header,
            'preview' => $preview,
            'errors' => $errors
        ];
    }

    /**
     * Proses upload TXT ke database
     */
    public function processTxtUpload(UploadedFile $file): array
    {
        $lines = $this->getLines($file);

        // Cek apakah file raw atau sudah fixed width
        $isRawFile = $this->isRawFile($lines);

        if ($isRawFile) {
            // Jika file raw, konversi dulu ke fixed width
            $lines = $this->convertRawLinesToFixedWidth($lines);
        }

        // Parse header untuk mendapatkan mapping kolom
        $header = $this->parseHeader($lines[0]);
        if (empty($header)) {
            return [
                'success' => false,
                'message' => 'Header tidak valid atau tidak ditemukan'
            ];
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        // Process setiap baris data (skip header)
        for ($i = 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (empty(trim($line))) continue;

            $parsed = $this->parseLine($line);
            if ($parsed['valid']) {
                try {
                    $this->saveAttendance($parsed['data']);
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                }
            } else {
                $failed++;
                $errors[] = "Baris " . ($i + 1) . ": " . $parsed['error'];
            }
        }

        // 🔥 BULK AUTO-SYNC: Sinkronisasi otomatis untuk semua employee yang baru diupload
        if ($success > 0) {
            try {
                $this->bulkAutoSyncAfterUpload();
            } catch (\Exception $e) {
                \Log::warning('Bulk auto-sync failed after TXT upload', [
                    'error' => $e->getMessage(),
                    'success_count' => $success
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Berhasil upload $success data, gagal $failed." . ($isRawFile ? " (File raw otomatis dikonversi)" : ""),
            'errors' => $errors
        ];
    }

    /**
     * Konversi file TXT raw dari mesin ke format fixed width sesuai backend
     * @param UploadedFile $file
     * @param bool $mapEmployeeId
     * @return string hasil konversi (siap diupload)
     */
    public function convertRawTxtToFixedWidth(UploadedFile $file, $mapEmployeeId = false): string
    {
        $lines = $this->getLines($file);
        $output = [];
        // Header fixed width
        $output[] = 'No. ID     Nama                     Tanggal    Scan Masuk Scan Pulang Absent  Jml Jam Kerja Jml Kehadiran';
        foreach ($lines as $line) {
            $cols = preg_split('/\s{2,}/', trim($line)); // Pisah berdasarkan 2 spasi atau lebih
            if (count($cols) < 3) continue; // Minimal harus ada No. ID, Nama, Tanggal
            $card_number = str_pad($cols[0], 13);
            $user_name = isset($cols[1]) ? str_pad($cols[1], 24) : str_repeat(' ', 24);
            $date = isset($cols[2]) ? str_pad($cols[2], 11) : str_repeat(' ', 11);
            $check_in = isset($cols[3]) ? str_pad($cols[3], 10) : str_repeat(' ', 10);
            $check_out = isset($cols[4]) ? str_pad($cols[4], 11) : str_repeat(' ', 11);
            $absent = isset($cols[5]) ? str_pad($cols[5], 8) : str_repeat(' ', 8);
            $work_hours = isset($cols[6]) ? str_pad($cols[6], 13) : str_repeat(' ', 13);
            $jml_kehadiran = isset($cols[7]) ? str_pad($cols[7], 13) : str_repeat(' ', 13);
            // Mapping employee_id jika diminta
            if ($mapEmployeeId) {
                $employee = \App\Models\Employee::where('NumCard', trim($cols[0]))->first();
                $employee_id = $employee ? $employee->id : '';
                // Tambahkan ke notes (atau bisa ke kolom baru jika backend support)
                $notes = json_encode(['employee_id' => $employee_id]);
                // Simpan di kolom jml_kehadiran jika ingin, atau tambahkan ke akhir baris (disesuaikan kebutuhan)
                $jml_kehadiran = str_pad($employee_id, 13);
            }
            $output[] = $card_number . $user_name . $date . $check_in . $check_out . $absent . $work_hours . $jml_kehadiran;
        }
        return implode("\n", $output);
    }

    /**
     * Ambil semua baris dari file TXT
     */
    private function getLines(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split('/\r\n|\r|\n/', $content);
        return array_filter($lines, fn($l) => trim($l) !== '');
    }

    /**
     * Parse header (baris pertama) dan tentukan posisi kolom otomatis
     */
    private function parseHeader(string $line): array
    {
        // Pad header jika kurang dari 103 karakter
        if (strlen($line) < 103) {
            $line = str_pad($line, 103, ' ');
        }
        // Ambil nama kolom dari header fixed width
        $header = [];
        foreach ($this->fixedColumns as $name => [$start, $end]) {
            $header[] = trim(substr($line, $start, $end - $start + 1));
        }
        $this->dynamicColumns = $this->fixedColumns;
        \Log::info('TXT HEADER DEBUG (FIXED)', [
            'header_line' => $line,
            'header_names' => $header,
            'dynamic_columns' => $this->dynamicColumns
        ]);
        return $header;
    }

    /**
     * Parse satu baris data sesuai posisi kolom dari header
     */
    private function parseLine(string $line): array
    {
        $minLength = 103; // Panjang baris minimal sesuai mapping
        if (strlen($line) < $minLength) {
            return ['valid' => false, 'error' => 'Baris terlalu pendek, pastikan format fixed width (minimal ' . $minLength . ' karakter)', 'data' => []];
        }
        $data = [];
        foreach ($this->fixedColumns as $name => [$start, $end]) {
            $data[$name] = trim(substr($line, $start, $end - $start + 1));
        }
        // Validasi minimal: No. ID, Nama, Tanggal harus ada
        if (empty($data['card_number']) || empty($data['user_name']) || empty($data['date'])) {
            return ['valid' => false, 'error' => 'No. ID, Nama, atau Tanggal kosong', 'data' => $data];
        }
        // Format tanggal
        try {
            $data['date'] = Carbon::parse($data['date'])->format('Y-m-d');
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Format tanggal tidak valid', 'data' => $data];
        }
        // Format jam masuk/pulang
        $data['check_in'] = $this->parseTime($data['check_in'] ?? '');
        $data['check_out'] = $this->parseTime($data['check_out'] ?? '');
        // Work hours
        $data['work_hours'] = $this->parseWorkHours($data['work_hours'] ?? '');
        // Status
        $data['status'] = $this->determineStatus($data);
        return ['valid' => true, 'data' => $data];
    }

    private function parseTime($str)
    {
        $str = trim($str);
        if (preg_match('/^\d{2}:\d{2}$/', $str)) {
            return $str . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $str)) {
            return $str;
        }
        return null;
    }

    private function parseWorkHours($str)
    {
        $str = trim($str);
        if (preg_match('/^(\d{2}):(\d{2})$/', $str, $m)) {
            return (int)$m[1] + ((int)$m[2] / 60);
        }
        if (is_numeric($str)) {
            return (float)$str;
        }
        return null;
    }

    private function determineStatus($data)
    {
        // Jika kolom absent ada dan bernilai Y/y, langsung absent
        if (isset($data['absent']) && strtolower($data['absent']) === 'y') {
            return 'absent';
        }
        // Jika tidak ada jam masuk, dianggap absent
        if (empty($data['check_in'])) {
            return 'absent';
        }
        // Jika ada jam masuk, cek keterlambatan
        $workStart = '07:30:00';
        if ($data['check_in'] > $workStart) {
            return 'present_late';
        }
        return 'present_ontime';
    }

    private function saveAttendance($data)
    {
        // 🔥 ENHANCED AUTO-SYNC: Mapping employee_id berdasarkan nama yang lebih robust
        $employee_id = null;
        $mapped_by = null;
        
        // PRIORITAS 1: Mapping berdasarkan nama lengkap (exact match)
        $employee = \App\Models\Employee::where('nama_lengkap', $data['user_name'])->first();
        
        if ($employee) {
            $employee_id = $employee->id;
            $mapped_by = 'exact_name_match';
        } else {
            // PRIORITAS 2: Fuzzy name matching (untuk handle case typo atau format nama berbeda)
            $employees = \App\Models\Employee::all();
            foreach ($employees as $emp) {
                // Case-insensitive comparison
                if (strtolower(trim($emp->nama_lengkap)) === strtolower(trim($data['user_name']))) {
                    $employee_id = $emp->id;
                    $mapped_by = 'case_insensitive_match';
                    $employee = $emp;
                    break;
                }
                
                // Partial match (jika nama di attendance mengandung nama di employee atau sebaliknya)
                if (strpos(strtolower($emp->nama_lengkap), strtolower($data['user_name'])) !== false ||
                    strpos(strtolower($data['user_name']), strtolower($emp->nama_lengkap)) !== false) {
                    $employee_id = $emp->id;
                    $mapped_by = 'partial_name_match';
                    $employee = $emp;
                    break;
                }
            }
        }
        
        // PRIORITAS 3: Fallback ke card_number jika nama tidak ditemukan
        if (!$employee_id && !empty($data['card_number'])) {
            $employee = \App\Models\Employee::where('NumCard', $data['card_number'])->first();
            if ($employee) {
                $employee_id = $employee->id;
                $mapped_by = 'card_number_match';
            }
        }
        
        // Log untuk debugging
        if ($employee_id) {
            \Log::info('Employee mapping successful', [
                'user_name' => $data['user_name'],
                'card_number' => $data['card_number'],
                'employee_id' => $employee_id,
                'mapped_by' => $mapped_by,
                'employee_name' => $employee->nama_lengkap ?? 'Unknown'
            ]);
        } else {
            \Log::warning('Employee mapping failed', [
                'user_name' => $data['user_name'],
                'card_number' => $data['card_number'],
                'total_employees' => \App\Models\Employee::count()
            ]);
        }
        
        // Update notes dengan informasi mapping
        $notes = json_encode([
            'jml_kehadiran' => $data['jml_kehadiran'] ?? null,
            'employee_id' => $employee_id,
            'mapped_by' => $mapped_by,
            'sync_status' => $employee_id ? 'synced' : 'not_found',
            'sync_timestamp' => now()->toISOString()
        ]);
        
        // Save atau update attendance dengan employee_id
        Attendance::updateOrCreate(
            [
                'card_number' => $data['card_number'],
                'date' => $data['date'],
            ],
            [
                'user_name' => $data['user_name'],
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'status' => $data['status'],
                'work_hours' => $data['work_hours'],
                'notes' => $notes,
                'employee_id' => $employee_id // 🔥 INI YANG PENTING: Set employee_id langsung
            ]
        );
    }
    
    /**
     * Cek apakah file raw (tidak fixed width)
     */
    private function isRawFile(array $lines): bool
    {
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;
            
            // Skip header
            if (strpos($trimmed, 'No. ID') !== false) continue;
            
            // Jika ada spasi di awal atau format tidak fixed width, anggap raw
            if (substr($line, 0, 1) === ' ' || strlen($trimmed) < 50) {
                return true;
            }
            
            // Cek apakah ada pattern card_number + nama + tanggal
            if (preg_match('/^\S+\s+.+?\s+\d{2}-[A-Za-z]{3}-\d{2}/', $trimmed)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Konversi baris raw ke fixed width
     */
    private function convertRawLinesToFixedWidth(array $lines): array
    {
        $output = [];
        // Header fixed width
        $output[] = 'No. ID     Nama                     Tanggal    Scan Masuk Scan Pulang Absent  Jml Jam Kerja Jml Kehadiran';
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;
            
            // Skip baris header jika ada
            if (strpos($trimmed, 'No. ID') !== false) continue;
            
            // Parse berdasarkan posisi karakter yang lebih sederhana
            // Format: [spasi]card_number[spasi]nama[spasi]tanggal[spasi]jam_masuk[spasi]jam_pulang[spasi]absent[spasi]jam_kerja[spasi]jumlah_kehadiran
            
            // Hapus spasi di awal
            $line = ltrim($line);
            
            // Cari posisi spasi pertama (setelah card_number)
            $firstSpace = strpos($line, ' ');
            if ($firstSpace === false) continue;
            
            $card_number = substr($line, 0, $firstSpace);
            $remaining = substr($line, $firstSpace + 1);
            
            // Cari tanggal (format: DD-MMM-YY)
            if (preg_match('/(\d{2}-[A-Za-z]{3}-\d{2})/', $remaining, $dateMatch)) {
                $date = $dateMatch[1];
                $datePos = strpos($remaining, $date);
                
                // Nama adalah teks antara spasi pertama dan tanggal
                $user_name = trim(substr($remaining, 0, $datePos));
                $afterDate = substr($remaining, $datePos + strlen($date));
                
                // Parse sisa data setelah tanggal
                $parts = preg_split('/\s+/', trim($afterDate));
                
                $check_in = str_repeat(' ', 10);
                $check_out = str_repeat(' ', 11);
                $absent = str_repeat(' ', 8);
                $work_hours = str_repeat(' ', 13);
                $jml_kehadiran = str_repeat(' ', 13);
                
                // Cari semua jam (format HH:MM)
                $timeValues = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^\d{1,2}:\d{2}$/', $part)) {
                        $timeValues[] = $part;
                    }
                }
                
                // Ambil 2 jam pertama sebagai check_in dan check_out
                if (isset($timeValues[0])) {
                    $check_in = str_pad($timeValues[0], 10);
                }
                if (isset($timeValues[1])) {
                    $check_out = str_pad($timeValues[1], 11);
                }
                
                // Cari absent (Y)
                foreach ($parts as $part) {
                    if (trim($part) === 'Y') {
                        $absent = str_pad('Y', 8);
                        break;
                    }
                }
                
                // Cari jam kerja (format HH:MM) - ambil jam ketiga jika ada
                if (isset($timeValues[2])) {
                    $work_hours = str_pad($timeValues[2], 13);
                }
                
                // Cari jumlah kehadiran (angka yang bukan jam) - ambil angka terakhir
                $numericValues = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (is_numeric($part) && !preg_match('/^\d{1,2}:\d{2}$/', $part)) {
                        $numericValues[] = $part;
                    }
                }
                
                // Ambil angka terakhir sebagai jumlah kehadiran
                if (!empty($numericValues)) {
                    $jml_kehadiran = str_pad(end($numericValues), 13);
                }
                
                // Pad semua field sesuai fixed width
                $card_number = str_pad($card_number, 13);
                $user_name = str_pad($user_name, 24);
                $date = str_pad($date, 11);
                
                $output[] = $card_number . $user_name . $date . $check_in . $check_out . $absent . $work_hours . $jml_kehadiran;
            }
        }
        return $output;
    }

    /**
     * Bulk auto-sync setelah upload TXT untuk memastikan semua employee ter-sync
     */
    private function bulkAutoSyncAfterUpload(): void
    {
        \Log::info('Starting bulk auto-sync after TXT upload');
        
        // 🔥 STEP 1: Direct sync berdasarkan nama yang belum ter-sync
        $unsyncedAttendances = \App\Models\Attendance::whereNull('employee_id')
                                                    ->whereNotNull('user_name')
                                                    ->get();
        
        $directSyncCount = 0;
        $partialSyncCount = 0;
        $cardSyncCount = 0;
        
        foreach ($unsyncedAttendances as $attendance) {
            $employee_id = null;
            $mapped_by = null;
            
            // Exact match
            $employee = \App\Models\Employee::where('nama_lengkap', $attendance->user_name)->first();
            if ($employee) {
                $employee_id = $employee->id;
                $mapped_by = 'exact_match';
                $directSyncCount++;
            } else {
                // Case insensitive match
                $employees = \App\Models\Employee::all();
                foreach ($employees as $emp) {
                    if (strtolower(trim($emp->nama_lengkap)) === strtolower(trim($attendance->user_name))) {
                        $employee_id = $emp->id;
                        $mapped_by = 'case_insensitive';
                        $directSyncCount++;
                        break;
                    }
                }
                
                // Partial match jika masih belum ketemu
                if (!$employee_id) {
                    foreach ($employees as $emp) {
                        if (strpos(strtolower($emp->nama_lengkap), strtolower($attendance->user_name)) !== false ||
                            strpos(strtolower($attendance->user_name), strtolower($emp->nama_lengkap)) !== false) {
                            $employee_id = $emp->id;
                            $mapped_by = 'partial_match';
                            $partialSyncCount++;
                            break;
                        }
                    }
                }
                
                // Card number fallback
                if (!$employee_id && !empty($attendance->card_number)) {
                    $employee = \App\Models\Employee::where('NumCard', $attendance->card_number)->first();
                    if ($employee) {
                        $employee_id = $employee->id;
                        $mapped_by = 'card_match';
                        $cardSyncCount++;
                    }
                }
            }
            
            // Update jika ditemukan mapping
            if ($employee_id) {
                $attendance->update([
                    'employee_id' => $employee_id,
                    'notes' => json_encode([
                        'mapped_by' => $mapped_by,
                        'sync_status' => 'bulk_synced',
                        'sync_timestamp' => now()->toISOString(),
                        'original_notes' => $attendance->notes ? json_decode($attendance->notes, true) : null
                    ])
                ]);
            }
        }
        
        // 🔥 STEP 2: Trigger EmployeeSyncService untuk cross-validation
        $uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                                ->whereNotNull('employee_id')
                                                ->distinct()
                                                ->pluck('user_name');
        
        $crossSyncCount = 0;
        foreach ($uniqueUserNames as $userName) {
            try {
                $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
                if ($syncResult['success']) {
                    $crossSyncCount++;
                }
            } catch (\Exception $e) {
                \Log::warning('Cross-sync failed for user after TXT upload', [
                    'user' => $userName,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        \Log::info('Bulk auto-sync completed after TXT upload', [
            'total_unsynced' => count($unsyncedAttendances),
            'direct_sync_count' => $directSyncCount,
            'partial_sync_count' => $partialSyncCount,
            'card_sync_count' => $cardSyncCount,
            'cross_sync_count' => $crossSyncCount,
            'remaining_unsynced' => \App\Models\Attendance::whereNull('employee_id')->count()
        ]);
    }

    /**
     * Manual bulk sync untuk semua attendance yang belum ter-sync (dapat dipanggil terpisah)
     */
    public function manualBulkSyncAttendance(): array
    {
        $this->bulkAutoSyncAfterUpload();
        
        $totalAttendance = \App\Models\Attendance::count();
        $syncedAttendance = \App\Models\Attendance::whereNotNull('employee_id')->count();
        $unsyncedAttendance = $totalAttendance - $syncedAttendance;
        
        return [
            'success' => true,
            'message' => 'Manual bulk sync completed',
            'data' => [
                'total_attendance' => $totalAttendance,
                'synced_attendance' => $syncedAttendance,
                'unsynced_attendance' => $unsyncedAttendance,
                'sync_percentage' => $totalAttendance > 0 ? round(($syncedAttendance / $totalAttendance) * 100, 2) : 0
            ]
        ];
    }
} 