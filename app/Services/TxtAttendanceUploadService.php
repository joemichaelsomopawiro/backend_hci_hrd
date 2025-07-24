<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TxtAttendanceUploadService
{
    // Definisi posisi kolom (fixed width, index mulai 0)
    private $columns = [
        'card_number'    => [0, 12],   // No. ID
        'user_name'      => [13, 36],  // Nama
        'date'           => [37, 47],  // Tanggal
        'check_in'       => [48, 57],  // Scan Masuk
        'check_out'      => [58, 68],  // Scan Pulang
        'absent'         => [69, 76],  // Absent
        'work_hours'     => [77, 89],  // Jml Jam Kerja
        'jml_kehadiran'  => [90, 102], // Jml Kehadiran (opsional)
    ];

    /**
     * Preview data TXT (10 baris pertama + header)
     */
    public function previewTxtData(UploadedFile $file): array
    {
        $lines = $this->getLines($file);
        if (count($lines) < 2) {
            return [
                'success' => false,
                'message' => 'File tidak berisi data yang valid. Minimal ada header dan 1 baris data.'
            ];
        }
        $header = $this->parseHeader($lines[0]);
        $preview = [];
        $errors = [];
        for ($i = 1; $i < min(11, count($lines)); $i++) {
            $row = $this->parseLine($lines[$i]);
            if ($row['valid']) {
                $preview[] = $row['data'];
            } else {
                $errors[] = "Baris " . ($i+1) . ": " . $row['error'];
            }
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
        if (count($lines) < 2) {
            return [
                'success' => false,
                'message' => 'File tidak berisi data yang valid. Minimal ada header dan 1 baris data.'
            ];
        }
        $header = $this->parseHeader($lines[0]);
        $success = 0;
        $failed = 0;
        $errors = [];
        for ($i = 1; $i < count($lines); $i++) {
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
     * Ambil semua baris dari file TXT
     */
    private function getLines(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split('/\r\n|\r|\n/', $content);
        return array_filter($lines, fn($l) => trim($l) !== '');
    }

    /**
     * Parse header (baris pertama)
     */
    private function parseHeader(string $line): array
    {
        $header = [];
        foreach ($this->columns as $name => [$start, $end]) {
            $header[] = trim(substr($line, $start, $end - $start + 1));
        }
        return $header;
    }

    /**
     * Parse satu baris data
     */
    private function parseLine(string $line): array
    {
        $data = [];
        foreach ($this->columns as $name => [$start, $end]) {
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
        $data['check_in'] = $this->parseTime($data['check_in']);
        $data['check_out'] = $this->parseTime($data['check_out']);
        // Work hours
        $data['work_hours'] = $this->parseWorkHours($data['work_hours']);
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