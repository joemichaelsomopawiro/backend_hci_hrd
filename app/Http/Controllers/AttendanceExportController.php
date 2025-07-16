<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\LeaveAttendanceIntegrationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AttendanceExportController extends Controller
{
    protected $leaveService;

    public function __construct(LeaveAttendanceIntegrationService $leaveService)
    {
        $this->leaveService = $leaveService;
    }
    /**
     * Export data absensi harian
     * GET /api/attendance/export/daily
     */
    public function exportDaily(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'date' => 'nullable|date',
                'format' => 'nullable|in:csv,excel'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = $request->get('date', now()->format('Y-m-d'));
            $format = $request->get('format', 'csv');
            
            // Sinkronisasi status cuti sebelum export
            $this->leaveService->syncLeaveStatusToAttendance($date);
            
            // Ambil data absensi untuk tanggal tertentu dengan relasi employee
            $attendances = Attendance::where('date', $date)
                ->with(['employeeAttendance.employee'])
                ->get()
                ->sortBy(function($attendance) {
                    // Urutkan berdasarkan nama pegawai
                    if ($attendance->employeeAttendance && $attendance->employeeAttendance->employee) {
                        return $attendance->employeeAttendance->employee->nama_lengkap;
                    } elseif ($attendance->employeeAttendance) {
                        return $attendance->employeeAttendance->name;
                    }
                    return 'Unknown';
                });

            if ($format === 'excel') {
                return $this->exportDailyExcel($attendances, $date);
            } else {
                return $this->exportDailyCSV($attendances, $date);
            }

        } catch (\Exception $e) {
            Log::error('Error in export daily attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data absensi bulanan
     * GET /api/attendance/export/monthly
     */
    public function exportMonthly(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'year' => 'nullable|integer|min:2020|max:2030',
                'month' => 'nullable|integer|min:1|max:12',
                'format' => 'nullable|in:csv,excel'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $year = $request->get('year', now()->year);
            $month = $request->get('month', now()->month);
            $format = $request->get('format', 'csv');

            // Ambil semua karyawan yang terdaftar di sistem dan urutkan berdasarkan nama
            $employees = EmployeeAttendance::where('is_active', true)
                ->with('employee')
                ->get()
                ->sortBy(function($emp) {
                    // Urutkan berdasarkan nama lengkap atau name
                    if ($emp->employee) {
                        return $emp->employee->nama_lengkap;
                    }
                    return $emp->name;
                });

            // Sinkronisasi status cuti untuk seluruh bulan sebelum export
            $startDate = Carbon::create($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');
            $this->leaveService->syncLeaveStatusForDateRange($startDate, $endDate);
            
            // Ambil data absensi untuk bulan tertentu
            $attendances = Attendance::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->get()
                ->groupBy('user_pin');

            // Debug: Log jumlah data yang ditemukan
            Log::info("Export monthly: Found " . $attendances->count() . " unique user_pins with attendance data");
            foreach ($attendances as $userPin => $data) {
                Log::info("User PIN {$userPin}: " . $data->count() . " records");
            }

            // Generate working days (Senin-Jumat) untuk bulan ini
            $workingDays = $this->getWorkingDays($year, $month);
            $monthName = Carbon::create($year, $month)->format('F');
            $yearName = $year;

            if ($format === 'excel') {
                return $this->exportMonthlyExcel($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName);
            } else {
                return $this->exportMonthlyCSV($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName);
            }

        } catch (\Exception $e) {
            Log::error('Error in export monthly attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate working days (Senin-Jumat) untuk bulan tertentu
     */
    private function getWorkingDays($year, $month): array
    {
        $workingDays = [];
        $startDate = Carbon::create($year, $month, 1);
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            // Hanya ambil hari Senin (1) sampai Jumat (5)
            if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
                $workingDays[] = [
                    'day' => $currentDate->day,
                    'date' => $currentDate->format('Y-m-d'),
                    'dayName' => $currentDate->format('D') // Mon, Tue, Wed, Thu, Fri
                ];
            }
            $currentDate->addDay();
        }
        
        return $workingDays;
    }

    /**
     * Export CSV harian
     */
    private function exportDailyCSV($attendances, $date): JsonResponse
    {
        // Buat CSV content
        $csvContent = $this->generateDailyCSV($attendances, $date);

        // Generate filename
        $filename = 'Absensi_' . Carbon::parse($date)->format('d-m-Y') . '_Hope_Channel_Indonesia.csv';

        // Save file
        $filePath = storage_path('app/public/exports/' . $filename);
        
        // Create directory if not exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        file_put_contents($filePath, $csvContent);

        return response()->json([
            'success' => true,
            'message' => 'Export berhasil',
            'data' => [
                'filename' => $filename,
                'download_url' => url('storage/exports/' . $filename),
                'total_records' => $attendances->count(),
                'date' => $date,
                'format' => 'csv'
            ]
        ]);
    }

    /**
     * Export Excel harian (HTML table yang bisa dibuka di Excel)
     */
    private function exportDailyExcel($attendances, $date): JsonResponse
    {
        // Generate HTML table yang bisa dibuka di Excel
        $htmlContent = $this->generateDailyExcel($attendances, $date);

        // Generate filename
        $filename = 'Absensi_' . Carbon::parse($date)->format('d-m-Y') . '_Hope_Channel_Indonesia.xls';

        // Save file
        $filePath = storage_path('app/public/exports/' . $filename);
        
        // Create directory if not exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        file_put_contents($filePath, $htmlContent);

        return response()->json([
            'success' => true,
            'message' => 'Export berhasil',
            'data' => [
                'filename' => $filename,
                'download_url' => url('storage/exports/' . $filename),
                'total_records' => $attendances->count(),
                'date' => $date,
                'format' => 'excel'
            ]
        ]);
    }

    /**
     * Export CSV bulanan
     */
    private function exportMonthlyCSV($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName): JsonResponse
    {
        // Buat CSV content
        $csvContent = $this->generateMonthlyCSV($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName);

        // Generate filename
        $filename = 'Absensi_' . $monthName . '_' . $yearName . '_Hope_Channel_Indonesia.csv';

        // Save file
        $filePath = storage_path('app/public/exports/' . $filename);
        
        // Create directory if not exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        file_put_contents($filePath, $csvContent);

        return response()->json([
            'success' => true,
            'message' => 'Export berhasil',
            'data' => [
                'filename' => $filename,
                'download_url' => url('storage/exports/' . $filename),
                'total_employees' => $employees->count(),
                'working_days' => count($workingDays),
                'month' => $monthName . ' ' . $yearName,
                'format' => 'csv'
            ]
        ]);
    }

    /**
     * Export Excel bulanan (HTML table yang bisa dibuka di Excel)
     */
    private function exportMonthlyExcel($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName): JsonResponse
    {
        // Debug: Log employee data
        Log::info("Export monthly: Processing " . $employees->count() . " employees");
        
        // Buat mapping dari user_pin ke employee data
        $employeeMap = [];
        $userPinToEmployee = [];
        
        foreach ($employees as $emp) {
            $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
            $employeeMap[$emp->machine_user_id] = $nama;
            $userPinToEmployee[$emp->machine_user_id] = $emp;
            
            // Debug: Log mapping
            Log::info("Mapping employee: machine_user_id {$emp->machine_user_id} -> {$nama}");
        }

        // Ambil semua user_pin yang pernah scan di bulan tsb
        $userPins = $attendances->keys();
        Log::info("Export monthly: Found " . $userPins->count() . " user_pins with attendance data: " . $userPins->implode(', '));

        // Siapkan data matrix - gunakan semua employee yang aktif
        $matrix = [];
        foreach ($employees as $emp) {
            $userPin = $emp->machine_user_id;
            $nama = $employeeMap[$userPin] ?? $userPin;
            $matrix[$userPin] = [
                'nama' => $nama,
                'data' => [],
                'total_hadir' => 0,
                'total_jam' => 0.0
            ];
        }
        
        // Isi data matrix hanya untuk hari kerja
        foreach ($employees as $emp) {
            $userPin = $emp->machine_user_id;
            $empAttendances = $attendances->get($userPin, collect());
            
            // Debug: Log untuk setiap employee
            Log::info("Processing employee {$userPin} ({$matrix[$userPin]['nama']}): " . $empAttendances->count() . " attendance records");
            
            foreach ($workingDays as $workingDay) {
                $date = $workingDay['date'];
                $day = $workingDay['day'];
                $att = $empAttendances->filter(function($item) use ($date) {
                    return $item->date->format('Y-m-d') === $date;
                })->first();
                
                // Debug: Log untuk setiap hari
                Log::info("  Checking attendance for {$userPin} on {$date}: " . ($att ? 'Found' : 'Not found'));
                
                if ($att) {
                    // Cek apakah status adalah cuti
                    if (in_array($att->status, ['on_leave', 'sick_leave'])) {
                        // Status cuti - tampilkan jenis cuti
                        $leaveType = $att->status === 'sick_leave' ? 'Sakit' : 'Cuti';
                        $matrix[$userPin]['data'][$day] = 'CUTI_' . $leaveType;
                        $matrix[$userPin]['total_hadir']++; // Cuti dihitung sebagai hadir
                        Log::info("  Found leave status for {$date}: {$att->status}");
                    } elseif ($att->check_in) {
                        // Jika ada check-in, berarti hadir
                        if ($att->check_out) {
                            $jamKerja = $att->work_hours ? number_format($att->work_hours, 2) : '0.00';
                            $matrix[$userPin]['data'][$day] = $att->check_in->format('H:i') . '-' . $att->check_out->format('H:i');
                            $matrix[$userPin]['total_jam'] += (float)$jamKerja;
                            Log::info("  Found complete attendance for {$date}: {$att->check_in->format('H:i')} - {$att->check_out->format('H:i')}");
                        } else {
                            $matrix[$userPin]['data'][$day] = $att->check_in->format('H:i') . '-';
                            Log::info("  Found partial attendance for {$date}: {$att->check_in->format('H:i')} - (no check out)");
                        }
                        $matrix[$userPin]['total_hadir']++;
                    } else {
                        $matrix[$userPin]['data'][$day] = 'ABSEN';
                        Log::info("  Found attendance record but no check in for {$date}");
                    }
                } else {
                    $matrix[$userPin]['data'][$day] = '';
                }
            }
        }

        // Urutkan matrix berdasarkan nama pegawai (A-Z)
        $matrix = collect($matrix)->sortBy('nama')->toArray();

        // Generate HTML table
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Absensi ' . $monthName . ' ' . $yearName . ' Hope Channel Indonesia</title>';
        $html .= '<style>';
        $html .= 'table { border-collapse: collapse; width: 100%; font-size: 11px; }';
        $html .= 'th, td { border: 1px solid #333; padding: 6px; text-align: center; }';
        $html .= 'th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }';
        $html .= '.nama { text-align: left; font-weight: bold; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: black; text-shadow: 1px 1px 2px rgba(255,255,255,0.5); }';
        $html .= '.hadir-lengkap { background-color: #4CAF50; color: white; font-weight: bold; }'; // Hijau untuk hadir lengkap
        $html .= '.hadir-masuk { background-color: #FFC107; color: #333; font-weight: bold; }'; // Kuning untuk hanya masuk
        $html .= '.tidak-hadir { background-color: #F44336; color: white; font-weight: bold; }'; // Merah untuk tidak hadir
        $html .= '.cuti { background-color: #1565C0; color: white; font-weight: bold; }'; // Biru tua untuk cuti
        $html .= '.total-col { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); font-weight: bold; color: #333; }';
        $html .= '.title { text-align: center; font-weight: bold; font-size: 18px; margin: 15px 0; color: #333; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }';
        $html .= '.subtitle { text-align: center; font-size: 14px; margin: 10px 0; color: #666; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="title">📊 LAPORAN ABSENSI ' . strtoupper($monthName) . ' ' . $yearName . '</div>';
        $html .= '<div class="title" style="font-size: 16px;">Hope Channel Indonesia</div>';
        $html .= '<div class="subtitle">📅 Hari Kerja (Senin-Jumat)</div>';
        $html .= '<table>';
        $html .= '<thead><tr><th>👤 Nama Karyawan</th>';
        foreach ($workingDays as $workingDay) {
            $html .= '<th>' . $workingDay['day'] . '<br><small>(' . $workingDay['dayName'] . ')</small></th>';
        }
        $html .= '<th class="total-col">✅ Total Hadir</th><th class="total-col">⏰ Total Jam Kerja</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($matrix as $row) {
            $html .= '<tr>';
            $html .= '<td class="nama">' . htmlspecialchars($row['nama']) . '</td>';
            foreach ($workingDays as $workingDay) {
                $day = $workingDay['day'];
                $cellData = $row['data'][$day] ?? '';
                
                // Tentukan class CSS berdasarkan data
                if ($cellData === '') {
                    // Tidak ada data
                    $cellClass = 'tidak-hadir';
                    $cellContent = '-';
                } elseif ($cellData === 'ABSEN') {
                    // Ada record tapi tidak ada check-in
                    $cellClass = 'tidak-hadir';
                    $cellContent = '-';
                } elseif (strpos($cellData, 'CUTI_') === 0) {
                    // Status cuti
                    $cellClass = 'cuti';
                    $leaveType = str_replace('CUTI_', '', $cellData);
                    $cellContent = $leaveType;
                } elseif (strpos($cellData, '-') !== false && substr($cellData, -1) === '-') {
                    // Hanya check-in (format: 08:00-)
                    $cellClass = 'hadir-masuk';
                    $cellContent = $cellData . ' (Hanya Masuk)';
                } elseif (strpos($cellData, '-') !== false && substr($cellData, -1) !== '-') {
                    // Check-in dan check-out lengkap (format: 08:00-17:00)
                    $cellClass = 'hadir-lengkap';
                    $cellContent = $cellData;
                } else {
                    // Default
                    $cellClass = '';
                    $cellContent = $cellData;
                }
                
                $html .= '<td class="' . $cellClass . '">' . $cellContent . '</td>';
            }
            $html .= '<td class="total-col">' . $row['total_hadir'] . '</td>';
            $html .= '<td class="total-col">' . number_format($row['total_jam'], 2) . ' jam</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        // Legend
        $html .= '<div style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">';
        $html .= '<h3 style="margin-top: 0; color: #333;">📋 Keterangan Warna:</h3>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #4CAF50; margin-right: 10px; border-radius: 3px;"></span><strong>Hijau:</strong> Hadir Lengkap (Check-in & Check-out)</div>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #FFC107; margin-right: 10px; border-radius: 3px;"></span><strong>Kuning:</strong> Hanya Check-in</div>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #1565C0; margin-right: 10px; border-radius: 3px;"></span><strong>Biru Tua:</strong> Cuti/Sakit</div>';
        $html .= '<div style="margin: 10px 0;"><span style="display: inline-block; width: 20px; height: 20px; background-color: #F44336; margin-right: 10px; border-radius: 3px;"></span><strong>Merah:</strong> Tidak Hadir (-)</div>';
        $html .= '</div>';
        
        $html .= '</body></html>';

        // Generate filename
        $filename = 'Absensi_' . $monthName . '_' . $yearName . '_Hope_Channel_Indonesia.xls';
        $filePath = storage_path('app/public/exports/' . $filename);
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, $html);
        // Log successful export
        Log::info('Monthly Excel export completed', [
            'filename' => $filename,
            'total_employees' => count($matrix),
            'working_days' => count($workingDays),
            'month' => $monthName . ' ' . $yearName,
            'file_size' => filesize($filePath)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Export Excel berhasil dibuat',
            'data' => [
                'filename' => $filename,
                'download_url' => url('storage/exports/' . $filename),
                'direct_download_url' => url('api/attendance/export/download/' . $filename),
                'total_employees' => count($matrix),
                'working_days' => count($workingDays),
                'month' => $monthName . ' ' . $yearName,
                'format' => 'excel',
                'file_size' => filesize($filePath),
                'auto_download' => true
            ]
        ]);
    }

    /**
     * Download file Excel langsung
     * GET /api/attendance/export/download/{filename}
     */
    public function downloadFile($filename)
    {
        try {
            $filePath = storage_path('app/public/exports/' . $filename);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan'
                ], 404);
            }
            
            // Set headers untuk download
            $headers = [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => filesize($filePath)
            ];
            
            return response()->download($filePath, $filename, $headers);
            
        } catch (\Exception $e) {
            Log::error('Error downloading file: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat download: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate CSV content untuk export harian
     */
    private function generateDailyCSV($attendances, $date): string
    {
        $csv = [];
        
        // Header
        $csv[] = 'LAPORAN ABSENSI HARIAN';
        $csv[] = 'Tanggal: ' . Carbon::parse($date)->format('d F Y');
        $csv[] = 'Hope Channel Indonesia';
        $csv[] = ''; // Empty line
        
        // Table header
        $csv[] = 'No,Nama Pegawai,Tanggal Absen,Scan Masuk,Scan Pulang,Jam Kerja';
        
        // Data
        $no = 1;
        foreach ($attendances as $attendance) {
            // Ambil nama pegawai
            $employeeName = 'Unknown';
            if ($attendance->employeeAttendance && $attendance->employeeAttendance->employee) {
                $employeeName = $attendance->employeeAttendance->employee->nama_lengkap;
            } elseif ($attendance->employeeAttendance) {
                $employeeName = $attendance->employeeAttendance->name;
            }
            
            // Cek status cuti
            if (in_array($attendance->status, ['on_leave', 'sick_leave'])) {
                $statusText = $attendance->status === 'sick_leave' ? 'Sakit' : 'Cuti';
                $row = [
                    $no,
                    $employeeName,
                    $attendance->date,
                    $statusText,
                    '-',
                    '-'
                ];
            } else {
                $row = [
                    $no,
                    $employeeName,
                    $attendance->date,
                    $attendance->check_in ? $attendance->check_in->format('H:i:s') : '-',
                    $attendance->check_out ? $attendance->check_out->format('H:i:s') : '-',
                    $attendance->work_hours ? number_format($attendance->work_hours, 2) . ' jam' : '-'
                ];
            }
            $csv[] = implode(',', $row);
            $no++;
        }
        
        return implode("\n", $csv);
    }

    /**
     * Generate Excel content untuk export harian (HTML table)
     */
    private function generateDailyExcel($attendances, $date): string
    {
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>LAPORAN ABSENSI HARIAN</title>';
        $html .= '<style>';
        $html .= 'table { border-collapse: collapse; width: 100%; }';
        $html .= 'th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }';
        $html .= 'th { background-color: #4472C4; color: white; font-weight: bold; }';
        $html .= '.title { text-align: center; font-weight: bold; font-size: 16px; margin: 10px 0; }';
        $html .= '.subtitle { text-align: center; font-size: 14px; margin: 5px 0; }';
        $html .= '.cuti { background-color: #1565C0; color: white; font-weight: bold; }'; // Biru tua untuk cuti
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        
        // Title
        $html .= '<div class="title">LAPORAN ABSENSI HARIAN</div>';
        $html .= '<div class="subtitle">Tanggal: ' . Carbon::parse($date)->format('d F Y') . '</div>';
        $html .= '<div class="subtitle">Hope Channel Indonesia</div>';
        $html .= '<br>';
        
        // Table
        $html .= '<table>';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>No</th>';
        $html .= '<th>Nama Pegawai</th>';
        $html .= '<th>Tanggal Absen</th>';
        $html .= '<th>Scan Masuk</th>';
        $html .= '<th>Scan Pulang</th>';
        $html .= '<th>Jam Kerja</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $no = 1;
        foreach ($attendances as $attendance) {
            // Ambil nama pegawai
            $employeeName = 'Unknown';
            if ($attendance->employeeAttendance && $attendance->employeeAttendance->employee) {
                $employeeName = $attendance->employeeAttendance->employee->nama_lengkap;
            } elseif ($attendance->employeeAttendance) {
                $employeeName = $attendance->employeeAttendance->name;
            }
            
            // Tentukan class CSS berdasarkan status
            $rowClass = '';
            $statusText = '';
            if (in_array($attendance->status, ['on_leave', 'sick_leave'])) {
                $rowClass = 'cuti';
                $statusText = $attendance->status === 'sick_leave' ? 'Sakit' : 'Cuti';
            }
            
            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td>' . $no . '</td>';
            $html .= '<td>' . htmlspecialchars($employeeName) . '</td>';
            $html .= '<td>' . $attendance->date . '</td>';
            if ($statusText) {
                $html .= '<td colspan="3">' . $statusText . '</td>';
            } else {
                $html .= '<td>' . ($attendance->check_in ? $attendance->check_in->format('H:i:s') : '-') . '</td>';
                $html .= '<td>' . ($attendance->check_out ? $attendance->check_out->format('H:i:s') : '-') . '</td>';
                $html .= '<td>' . ($attendance->work_hours ? number_format($attendance->work_hours, 2) . ' jam' : '-') . '</td>';
            }
            $html .= '</tr>';
            $no++;
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }

    /**
     * Generate CSV content untuk export bulanan
     */
    private function generateMonthlyCSV($employees, $attendances, $year, $month, $workingDays, $monthName, $yearName): string
    {
        $csv = [];
        
        // Header
        $csv[] = 'Absensi ' . $monthName . ' ' . $yearName . ' Hope Channel Indonesia';
        $csv[] = 'Hari Kerja (Senin-Jumat)';
        $csv[] = ''; // Empty line
        
        // Header row dengan tanggal
        $headerRow = ['Nama Karyawan'];
        foreach ($workingDays as $workingDay) {
            $headerRow[] = $workingDay['day'] . ' (' . $workingDay['dayName'] . ')';
        }
        $csv[] = implode(',', $headerRow);
        
        // Data karyawan
        foreach ($employees as $employeeAttendance) {
            $employeeName = $employeeAttendance->employee ? $employeeAttendance->employee->nama_lengkap : 'Unknown';
            $row = [$employeeName];
            
            // Ambil data absensi untuk karyawan ini
            $userPin = $employeeAttendance->machine_user_id;
            $employeeAttendances = $attendances->get($userPin, collect());
            
            // Isi data per hari kerja
            foreach ($workingDays as $workingDay) {
                $date = $workingDay['date'];
                $attendance = $employeeAttendances->where('date', $date)->first();
                
                if ($attendance) {
                    // Cek status cuti
                    if (in_array($attendance->status, ['on_leave', 'sick_leave'])) {
                        $row[] = $attendance->status === 'sick_leave' ? 'SAKIT' : 'CUTI';
                    } elseif ($attendance->check_in && $attendance->check_out) {
                        $row[] = 'HADIR';
                    } elseif ($attendance->check_in) {
                        $row[] = 'IN';
                    } else {
                        $row[] = 'ABSEN';
                    }
                } else {
                    $row[] = '';
                }
            }
            
            $csv[] = implode(',', $row);
        }
        
        // Tambahkan legend
        $csv[] = ''; // Empty line
        $csv[] = 'Keterangan:';
        $csv[] = 'HADIR = Hadir (Tap In & Tap Out)';
        $csv[] = 'IN = Hanya Tap In';
        $csv[] = 'CUTI = Sedang Cuti';
        $csv[] = 'SAKIT = Sakit';
        $csv[] = 'ABSEN = Tidak Hadir';
        $csv[] = 'Kosong = Hari Libur (Sabtu-Minggu)';
        
        return implode("\n", $csv);
    }


}