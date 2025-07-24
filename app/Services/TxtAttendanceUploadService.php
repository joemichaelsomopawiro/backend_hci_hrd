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
        $success = 0;
        $failed = 0;
        $errors = [];
        for ($i = $dataStart; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') continue; // SKIP baris kosong di mana pun
            $row = $this->parseLine($lines[$i]);
            if ($row['valid']) {
                try {
                    $this->saveAttendance($row['data']);
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Baris " . ($i+1) . ": " . $e->getMessage();
                }
            } else {
                $failed++;
                $errors[] = "Baris " . ($i+1) . ": " . $row['error'];
            }
        }
        return [
            'success' => true,
            'message' => "Berhasil upload $success data, gagal $failed.",
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
                'notes' => json_encode([
                    'jml_kehadiran' => $data['jml_kehadiran'] ?? null
                ])
            ]
        );
    }
} 