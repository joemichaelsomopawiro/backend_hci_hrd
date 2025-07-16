<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MorningReflectionAttendance;
use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;



class GaDashboardController extends Controller
{
    /**
     * Mendapatkan semua data absensi renungan pagi untuk GA Dashboard
     * Menggunakan logika yang sama seperti di komponen "Riwayat absensi renungan"
     */
    public function getAllWorshipAttendance(Request $request)
    {
        try {
            $dateFilter = $request->date;
            $allData = $request->boolean('all', false);
            
            Log::info('GA Dashboard: Loading worship attendance data', [
                'date_filter' => $dateFilter,
                'all_data' => $allData,
                'user_id' => auth()->id()
            ]);

            // Tentukan rentang tanggal
            if ($allData) {
                // Jika meminta semua data, ambil 30 hari terakhir
                $startDate = Carbon::now()->subDays(30)->toDateString();
                $endDate = Carbon::now()->toDateString();
            } elseif ($dateFilter) {
                // Jika ada filter tanggal, gunakan tanggal tersebut
                $startDate = $dateFilter;
                $endDate = $dateFilter;
            } else {
                // Default: hari ini
                $startDate = Carbon::today()->toDateString();
                $endDate = Carbon::today()->toDateString();
            }

            // Ambil semua data absensi renungan dalam rentang tanggal
            $attendances = MorningReflectionAttendance::with(['employee.user'])
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            // Ambil semua data cuti yang disetujui dalam rentang tanggal
            $leaves = LeaveRequest::with(['employee.user'])
                ->where('overall_status', 'approved')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                          });
                })
                ->get();

            // Gabungkan data absensi dan cuti untuk semua employee
            $combinedData = $this->mergeAllAttendanceAndLeave($attendances, $leaves, $startDate, $endDate);

            // Transform data untuk frontend
            $transformedData = $combinedData->map(function ($record) {
                return [
                    'id' => $record['id'],
                    'employee_id' => $record['employee_id'],
                    'name' => $record['employee_name'],
                    'position' => $record['employee_position'] ?? '-',
                    'date' => $record['date'],
                    'attendance_time' => $record['join_time'] ? 
                        Carbon::parse($record['join_time'])->format('H:i') : '-',
                    'status' => $record['status'],
                    'status_label' => $this->getStatusLabel($record['status']),
                    'testing_mode' => $record['testing_mode'] ?? false,
                    'created_at' => $record['created_at'],
                    'data_source' => $record['data_source']
                ];
            });

            Log::info('GA Dashboard: Worship attendance data loaded', [
                'total_records' => $transformedData->count(),
                'date_filter' => $dateFilter,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'message' => 'Data absensi renungan pagi berhasil diambil',
                'total_records' => $transformedData->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error loading worship attendance data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan semua data permohonan cuti untuk GA Dashboard
     * Menampilkan SEMUA data tanpa batasan role
     */
    public function getAllLeaveRequests(Request $request)
    {
        try {
            Log::info('GA Dashboard: Loading leave requests data', [
                'user_id' => auth()->id()
            ]);

            $query = LeaveRequest::with(['employee.user', 'approvedBy.user'])
                ->select([
                    'leave_requests.*',
                    'employees.nama_lengkap as employee_name',
                    'employees.jabatan_saat_ini as employee_position',
                    'users.role as user_role'
                ])
                ->leftJoin('employees', 'leave_requests.employee_id', '=', 'employees.id')
                ->leftJoin('users', 'employees.id', '=', 'users.employee_id');

            // Urutkan berdasarkan tanggal terbaru
            $query->orderBy('leave_requests.created_at', 'desc');

            $leaveRequests = $query->get();

            // Transform data untuk frontend
            $transformedData = $leaveRequests->map(function ($request) {
                // Generate leave_dates dari start_date ke end_date
                $start = \Carbon\Carbon::parse($request->start_date);
                $end = \Carbon\Carbon::parse($request->end_date);
                $dates = [];
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dates[] = $date->toDateString();
                }
                return [
                    'id' => $request->id,
                    'employee_id' => $request->employee_id,
                    'employee' => [
                        'id' => $request->employee_id,
                        'nama_lengkap' => $request->employee_name ?? 
                                        ($request->employee->nama_lengkap ?? 'Unknown Employee'),
                        'jabatan_saat_ini' => $request->employee_position ?? 
                                            ($request->employee->jabatan_saat_ini ?? '-')
                    ],
                    'leave_type' => $request->leave_type,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'total_days' => $request->total_days,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'overall_status' => $request->overall_status,
                    'status' => $request->overall_status, // Alias untuk kompatibilitas
                    'approved_by' => $request->approved_by,
                    'approved_at' => $request->approved_at,
                    'created_at' => $request->created_at,
                    'updated_at' => $request->updated_at,
                    'leave_dates' => $dates,
                    'raw_data' => $request // Untuk debugging
                ];
            });

            Log::info('GA Dashboard: Leave requests data loaded', [
                'total_records' => $transformedData->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'message' => 'Data permohonan cuti berhasil diambil',
                'total_records' => $transformedData->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error loading leave requests data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data cuti: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan statistik absensi renungan pagi
     */
    public function getWorshipStatistics(Request $request)
    {
        try {
            $dateFilter = $request->date ?? Carbon::today()->toDateString();
            
            $query = MorningReflectionAttendance::whereDate('date', $dateFilter);
            
            $total = $query->count();
            $present = $query->clone()->where('status', 'present')->count();
            $late = $query->clone()->where('status', 'late')->count();
            $absent = $query->clone()->where('status', 'absent')->count();
            $leave = $query->clone()->where('status', 'leave')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'leave' => $leave,
                    'date' => $dateFilter
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error getting worship statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik'
            ], 500);
        }
    }

    /**
     * Mendapatkan statistik permohonan cuti
     */
    public function getLeaveStatistics(Request $request)
    {
        try {
            $query = LeaveRequest::query();
            
            $total = $query->count();
            $pending = $query->where('overall_status', 'pending')->count();
            $approved = $query->where('overall_status', 'approved')->count();
            $rejected = $query->where('overall_status', 'rejected')->count();
            $expired = $query->where('overall_status', 'expired')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'expired' => $expired
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('GA Dashboard: Error getting leave statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik cuti'
            ], 500);
        }
    }

    /**
     * Export data absensi ibadah ke Excel
     * Format: Tabel tahunan dengan checkbox untuk hadir/absen
     * Senin-Rabu-Jumat: dari morning_reflection_attendance
     * Selasa-Kamis: dari attendances (tap kartu)
     */
    public function exportWorshipAttendance(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));
            $allData = $request->get('all', false);
            $date = $request->get('date', null);
            
            Log::info('GA Dashboard: Exporting worship attendance data', [
                'year' => $year,
                'all_data' => $allData,
                'date' => $date,
                'user_id' => auth()->id()
            ]);

            // Jika ada parameter date, gunakan tahun dari date tersebut
            if ($date) {
                $year = date('Y', strtotime($date));
            }

            // Ambil semua employee
            $employees = Employee::orderBy('nama_lengkap')->get();
            
            // Generate semua tanggal Senin-Jumat untuk tahun tersebut
            $workDays = $this->generateWorkDays($year);
            
            // Ambil data absensi ibadah (Senin, Rabu, Jumat)
            $worshipData = $this->getWorshipAttendanceData($year, $allData);
            
            // Ambil data absensi kantor (Selasa, Kamis)
            $officeData = $this->getOfficeAttendanceData($year, $allData);
            
            // Ambil data cuti
            $leaveData = $this->getLeaveData($year, $allData);
            
            // Gabungkan semua data
            $combinedData = $this->combineAttendanceData($employees, $workDays, $worshipData, $officeData, $leaveData);
            
            // Buat HTML Excel file
            $htmlContent = $this->createWorshipAttendanceHTML($combinedData, $year);
            
            // Generate filename
            $filename = "Data_Absensi_Ibadah_{$year}_Hope_Channel_Indonesia.xls";
            
            // Save file
            $filePath = storage_path('app/public/exports/' . $filename);
            
            // Create directory if not exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            file_put_contents($filePath, $htmlContent);
            
            Log::info('GA Dashboard: Worship attendance export completed', [
                'filename' => $filename,
                'total_employees' => $employees->count(),
                'total_days' => count($workDays)
            ]);
            
            // Return file download response
            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
            
        } catch (Exception $e) {
            Log::error('GA Dashboard: Error exporting worship attendance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate semua tanggal Senin-Jumat untuk tahun tertentu
     */
    private function generateWorkDays($year)
    {
        $workDays = [];
        $startDate = Carbon::createFromDate($year, 1, 1);
        $endDate = Carbon::createFromDate($year, 12, 31);
        
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            // Senin = 1, Selasa = 2, Rabu = 3, Kamis = 4, Jumat = 5
            if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
                $workDays[] = $currentDate->format('Y-m-d');
            }
            $currentDate->addDay();
        }
        
        return $workDays;
    }

    /**
     * Ambil data absensi ibadah (Senin, Rabu, Jumat)
     */
    private function getWorshipAttendanceData($year, $allData)
    {
        $query = MorningReflectionAttendance::with('employee')
            ->whereYear('date', $year);
            
        if (!$allData) {
            $query->where('testing_mode', false);
        }
        
        $data = $query->get();
        
        $result = [];
        foreach ($data as $attendance) {
            $date = $attendance->date->format('Y-m-d');
            $dayOfWeek = $attendance->date->dayOfWeek;
            
            // Hanya ambil Senin (1), Rabu (3), Jumat (5)
            if (in_array($dayOfWeek, [1, 3, 5])) {
                $result[$attendance->employee_id][$date] = [
                    'status' => $attendance->status,
                    'join_time' => $attendance->join_time,
                    'source' => 'worship'
                ];
            }
        }
        
        return $result;
    }

    /**
     * Ambil data absensi kantor (Selasa, Kamis)
     */
    private function getOfficeAttendanceData($year, $allData)
    {
        $query = Attendance::with('employee')
            ->whereYear('date', $year);
            
        if (!$allData) {
            // Filter untuk data non-testing jika diperlukan
        }
        
        $data = $query->get();
        
        $result = [];
        foreach ($data as $attendance) {
            $date = $attendance->date->format('Y-m-d');
            $dayOfWeek = $attendance->date->dayOfWeek;
            
            // Hanya ambil Selasa (2), Kamis (4)
            if (in_array($dayOfWeek, [2, 4])) {
                $status = $this->determineOfficeAttendanceStatus($attendance);
                
                $result[$attendance->employee_id][$date] = [
                    'status' => $status,
                    'check_in' => $attendance->check_in,
                    'source' => 'office'
                ];
            }
        }
        
        return $result;
    }

    /**
     * Tentukan status absensi kantor berdasarkan waktu check-in
     */
    private function determineOfficeAttendanceStatus($attendance)
    {
        if (!$attendance->check_in) {
            return 'Absen';
        }
        
        $checkInTime = Carbon::parse($attendance->check_in);
        $eightOClock = Carbon::parse('08:00:00');
        $eightFive = Carbon::parse('08:05:00');
        $eightTen = Carbon::parse('08:10:00');
        
        if ($checkInTime <= $eightOClock) {
            return 'Hadir';
        } elseif ($checkInTime <= $eightFive) {
            return 'Terlambat';
        } elseif ($checkInTime <= $eightTen) {
            return 'Terlambat';
        } else {
            return 'Absen';
        }
    }

    /**
     * Ambil data cuti
     */
    private function getLeaveData($year, $allData)
    {
        $query = LeaveRequest::with('employee')
            ->where('overall_status', 'approved')
            ->whereYear('start_date', $year);
            
        $data = $query->get();
        
        $result = [];
        foreach ($data as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);
            
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $date = $currentDate->format('Y-m-d');
                $dayOfWeek = $currentDate->dayOfWeek;
                
                // Hanya untuk hari kerja (Senin-Jumat)
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    $result[$leave->employee_id][$date] = [
                        'status' => 'Cuti',
                        'leave_type' => $leave->leave_type,
                        'reason' => $leave->reason,
                        'source' => 'leave'
                    ];
                }
                
                $currentDate->addDay();
            }
        }
        
        return $result;
    }

    /**
     * Gabungkan semua data absensi
     */
    private function combineAttendanceData($employees, $workDays, $worshipData, $officeData, $leaveData)
    {
        $combined = [];
        
        foreach ($employees as $employee) {
            $employeeData = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->nama_lengkap,
                'attendance' => []
            ];
            
            foreach ($workDays as $date) {
                $dayOfWeek = Carbon::parse($date)->dayOfWeek;
                $status = 'Absen';
                $source = 'none';
                $leaveInfo = null;
                
                // Cek data cuti terlebih dahulu
                if (isset($leaveData[$employee->id][$date])) {
                    $status = 'Cuti';
                    $source = 'leave';
                    $leaveInfo = $leaveData[$employee->id][$date];
                }
                // Cek data absensi ibadah (Senin, Rabu, Jumat)
                elseif (in_array($dayOfWeek, [1, 3, 5]) && isset($worshipData[$employee->id][$date])) {
                    $status = $worshipData[$employee->id][$date]['status'];
                    $source = 'worship';
                }
                // Cek data absensi kantor (Selasa, Kamis)
                elseif (in_array($dayOfWeek, [2, 4]) && isset($officeData[$employee->id][$date])) {
                    $status = $officeData[$employee->id][$date]['status'];
                    $source = 'office';
                }
                
                $employeeData['attendance'][$date] = [
                    'status' => $status,
                    'source' => $source,
                    'leave_info' => $leaveInfo
                ];
            }
            
            $combined[] = $employeeData;
        }
        
        return $combined;
    }

    /**
     * Buat file HTML Excel untuk absensi ibadah
     */
    private function createWorshipAttendanceHTML($data, $year)
    {
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Data Absensi Ibadah ' . $year . ' - Hope Channel Indonesia</title>';
        $html .= '<style>';
        $html .= 'table { border-collapse: collapse; width: 100%; font-size: 10px; }';
        $html .= 'th, td { border: 1px solid #333; padding: 4px; text-align: center; }';
        $html .= 'th { background: linear-gradient(135deg, #4472C4 0%, #764ba2 100%); color: white; font-weight: bold; }';
        $html .= '.nama { text-align: left; font-weight: bold; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: black; }';
        $html .= '.hadir { background-color: #90EE90; color: black; font-weight: bold; }'; // Hijau
        $html .= '.terlambat { background-color: #FFFF00; color: black; font-weight: bold; }'; // Kuning
        $html .= '.absen { background-color: #FF6B6B; color: white; font-weight: bold; }'; // Merah
        $html .= '.cuti { background-color: #FFA500; color: black; font-weight: bold; }'; // Orange
        $html .= '.title { text-align: center; font-weight: bold; font-size: 16px; margin: 10px 0; }';
        $html .= '.subtitle { text-align: center; font-size: 12px; margin: 5px 0; }';
        $html .= '.legend { margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        
        // Title
        $html .= '<div class="title">📊 DATA ABSENSI IBADAH ' . $year . '</div>';
        $html .= '<div class="subtitle">Hope Channel Indonesia</div>';
        $html .= '<div class="subtitle">📅 Hari Kerja (Senin-Jumat)</div>';
        $html .= '<br>';
        
        // Table
        $html .= '<table>';
        $html .= '<thead><tr><th>No</th><th>👤 Nama Karyawan</th>';
        
        // Generate header tanggal
        $workDays = $this->generateWorkDays($year);
        foreach ($workDays as $date) {
            $carbonDate = Carbon::parse($date);
            $dayName = $this->getDayName($carbonDate->dayOfWeek);
            $html .= '<th>' . $dayName . '<br>' . $carbonDate->format('d/m') . '</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        $leaveReasons = [];
        $reasonCounter = 1;
        
        foreach ($data as $index => $employeeData) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td class="nama">' . htmlspecialchars($employeeData['employee_name']) . '</td>';
            
            foreach ($workDays as $date) {
                $attendance = $employeeData['attendance'][$date];
                $cellClass = '';
                $cellContent = '';
                
                if ($attendance['status'] === 'Cuti') {
                    // Untuk cuti, buat kode referensi
                    $leaveCode = 'C' . $reasonCounter;
                    $cellClass = 'cuti';
                    $cellContent = $leaveCode;
                    
                    // Simpan alasan cuti
                    $leaveReasons[$leaveCode] = $attendance['leave_info']['reason'];
                    $reasonCounter++;
                    
                } elseif ($attendance['status'] === 'Hadir') {
                    $cellClass = 'hadir';
                    $cellContent = '✓';
                    
                } elseif ($attendance['status'] === 'Terlambat') {
                    $cellClass = 'terlambat';
                    $cellContent = '✓';
                    
                } else {
                    // Absen
                    $cellClass = 'absen';
                    $cellContent = '';
                }
                
                $html .= '<td class="' . $cellClass . '">' . $cellContent . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Legend
        $html .= '<div class="legend">';
        $html .= '<h3>📋 Keterangan Warna:</h3>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #90EE90; margin-right: 10px; border-radius: 3px;"></span><strong>Hijau:</strong> Hadir</div>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #FFFF00; margin-right: 10px; border-radius: 3px;"></span><strong>Kuning:</strong> Terlambat</div>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #FFA500; margin-right: 10px; border-radius: 3px;"></span><strong>Orange:</strong> Cuti</div>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #FF6B6B; margin-right: 10px; border-radius: 3px;"></span><strong>Merah:</strong> Tidak Hadir</div>';
        $html .= '</div>';
        
        // Alasan cuti
        if (!empty($leaveReasons)) {
            $html .= '<div class="legend">';
            $html .= '<h3>📝 ALASAN CUTI:</h3>';
            foreach ($leaveReasons as $code => $reason) {
                $html .= '<div><strong>' . $code . ':</strong> ' . htmlspecialchars($reason) . '</div>';
            }
            $html .= '</div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Get nama hari dalam bahasa Indonesia
     */
    private function getDayName($dayOfWeek)
    {
        $days = [
            1 => 'Sen',
            2 => 'Sel', 
            3 => 'Rab',
            4 => 'Kam',
            5 => 'Jum'
        ];
        
        return $days[$dayOfWeek] ?? '';
    }

    /**
     * Export data cuti ke Excel
     */
    public function exportLeaveRequests(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));
            $allData = $request->get('all', false);
            
            Log::info('GA Dashboard: Exporting leave requests data', [
                'year' => $year,
                'all_data' => $allData,
                'user_id' => auth()->id()
            ]);

            // Ambil semua data cuti
            $query = LeaveRequest::with(['employee', 'approvedBy.user'])
                ->whereYear('start_date', $year);
                
            if (!$allData) {
                // Filter untuk data non-testing jika diperlukan
            }
            
            $leaveRequests = $query->orderBy('created_at', 'desc')->get();
            
            // Buat HTML Excel file
            $htmlContent = $this->createLeaveRequestsHTML($leaveRequests, $year);
            
            // Generate filename
            $filename = "Data_Cuti_{$year}_Hope_Channel_Indonesia.xls";
            
            // Save file
            $filePath = storage_path('app/public/exports/' . $filename);
            
            // Create directory if not exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            file_put_contents($filePath, $htmlContent);
            
            Log::info('GA Dashboard: Leave requests export completed', [
                'filename' => $filename,
                'total_records' => $leaveRequests->count()
            ]);
            
            // Return file download response
            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
            
        } catch (Exception $e) {
            Log::error('GA Dashboard: Error exporting leave requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export data cuti: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buat file HTML Excel untuk data cuti
     */
    private function createLeaveRequestsHTML($leaveRequests, $year)
    {
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Data Cuti ' . $year . ' - Hope Channel Indonesia</title>';
        $html .= '<style>';
        $html .= 'table { border-collapse: collapse; width: 100%; font-size: 11px; }';
        $html .= 'th, td { border: 1px solid #333; padding: 6px; text-align: center; }';
        $html .= 'th { background: linear-gradient(135deg, #4472C4 0%, #764ba2 100%); color: white; font-weight: bold; }';
        $html .= '.nama { text-align: left; font-weight: bold; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: black; }';
        $html .= '.approved { background-color: #90EE90; color: black; font-weight: bold; }'; // Hijau
        $html .= '.rejected { background-color: #FF6B6B; color: white; font-weight: bold; }'; // Merah
        $html .= '.expired { background-color: #D3D3D3; color: black; font-weight: bold; }'; // Abu-abu
        $html .= '.pending { background-color: #FFFF00; color: black; font-weight: bold; }'; // Kuning
        $html .= '.title { text-align: center; font-weight: bold; font-size: 16px; margin: 10px 0; }';
        $html .= '.subtitle { text-align: center; font-size: 12px; margin: 5px 0; }';
        $html .= '.legend { margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        
        // Title
        $html .= '<div class="title">📋 DATA CUTI ' . $year . '</div>';
        $html .= '<div class="subtitle">Hope Channel Indonesia</div>';
        $html .= '<br>';
        
        // Table
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>No</th>';
        $html .= '<th>👤 Nama Karyawan</th>';
        $html .= '<th>📝 Jenis Cuti</th>';
        $html .= '<th>📅 Tanggal Mulai</th>';
        $html .= '<th>📅 Tanggal Selesai</th>';
        $html .= '<th>📊 Total Hari</th>';
        $html .= '<th>💬 Alasan</th>';
        $html .= '<th>✅ Status</th>';
        $html .= '<th>👨‍💼 Disetujui Oleh</th>';
        $html .= '<th>⏰ Tanggal Persetujuan</th>';
        $html .= '<th>📝 Catatan</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        foreach ($leaveRequests as $index => $leave) {
            // Tentukan class CSS berdasarkan status
            $rowClass = '';
            switch ($leave->overall_status) {
                case 'approved':
                    $rowClass = 'approved';
                    break;
                case 'rejected':
                    $rowClass = 'rejected';
                    break;
                case 'expired':
                    $rowClass = 'expired';
                    break;
                default:
                    $rowClass = 'pending';
                    break;
            }
            
            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td class="nama">' . htmlspecialchars($leave->employee ? $leave->employee->nama_lengkap : 'Karyawan Tidak Ditemukan') . '</td>';
            $html .= '<td>' . htmlspecialchars($this->getLeaveTypeLabel($leave->leave_type)) . '</td>';
            $html .= '<td>' . $leave->start_date->format('d/m/Y') . '</td>';
            $html .= '<td>' . $leave->end_date->format('d/m/Y') . '</td>';
            $html .= '<td>' . $leave->total_days . '</td>';
            $html .= '<td>' . htmlspecialchars($leave->reason) . '</td>';
            $html .= '<td>' . htmlspecialchars($this->getStatusLabel($leave->overall_status, 'leave')) . '</td>';
            $html .= '<td>' . htmlspecialchars($leave->approvedBy ? $leave->approvedBy->nama_lengkap : '-') . '</td>';
            $html .= '<td>' . ($leave->approved_at ? Carbon::parse($leave->approved_at)->format('d/m/Y H:i') : '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($leave->notes ?? '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Legend
        $html .= '<div class="legend">';
        $html .= '<h3>📋 Keterangan Warna:</h3>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #90EE90; margin-right: 10px; border-radius: 3px;"></span><strong>Hijau:</strong> Disetujui</div>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #FF6B6B; margin-right: 10px; border-radius: 3px;"></span><strong>Merah:</strong> Ditolak</div>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #D3D3D3; margin-right: 10px; border-radius: 3px;"></span><strong>Abu-abu:</strong> Kadaluarsa</div>';
        $html .= '<div><span style="display: inline-block; width: 20px; height: 20px; background-color: #FFFF00; margin-right: 10px; border-radius: 3px;"></span><strong>Kuning:</strong> Menunggu</div>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Get label untuk jenis cuti
     */
    private function getLeaveTypeLabel($leaveType)
    {
        $labels = [
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'paternity' => 'Cuti Ayah',
            'marriage' => 'Cuti Menikah',
            'bereavement' => 'Cuti Duka'
        ];
        
        return $labels[$leaveType] ?? $leaveType;
    }

    /**
     * Get label untuk status (absensi dan cuti)
     */
    private function getStatusLabel($status, $type = 'attendance')
    {
        if ($type === 'leave') {
            $labels = [
                'pending' => 'Menunggu',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                'expired' => 'Kadaluarsa'
            ];
        } else {
            $labels = [
                'present' => 'Hadir',
                'late' => 'Terlambat',
                'absent' => 'Tidak Hadir',
                'leave' => 'Cuti',
                'not_worship_day' => 'Bukan Jadwal'
            ];
        }
        
        return $labels[$status] ?? $status;
    }

    /**
     * Menggabungkan data absensi dan cuti untuk semua employee
     */
    private function mergeAllAttendanceAndLeave($attendances, $leaves, $startDate, $endDate)
    {
        $combinedData = [];
        $processedDates = [];

        // Ambil semua employee untuk mendapatkan data lengkap
        $allEmployees = Employee::all()->keyBy('id');

        // Proses data absensi - filter hanya hari renungan (Senin-Jumat)
        foreach ($attendances as $attendance) {
            $date = Carbon::parse($attendance->date);
            $dateString = $date->toDateString();
            
            // Skip jika bukan hari renungan (Senin=1, Jumat=5)
            if (!$this->isReflectionDay($date)) {
                continue;
            }
            
            $employee = $allEmployees->get($attendance->employee_id);
            $employeeName = $employee ? $employee->nama_lengkap : 'Karyawan Tidak Ditemukan';
            $employeePosition = $employee ? $employee->jabatan_saat_ini : '-';
            
            $processedDates[] = $dateString . '_' . $attendance->employee_id;
            
            $combinedData[] = [
                'id' => $attendance->id,
                'employee_id' => (int) $attendance->employee_id,
                'employee_name' => $employeeName,
                'employee_position' => $employeePosition,
                'date' => $dateString,
                'status' => $this->mapAttendanceStatus($attendance->status),
                'join_time' => $attendance->join_time,
                'testing_mode' => $attendance->testing_mode,
                'created_at' => $attendance->created_at,
                'data_source' => 'attendance'
            ];
        }

        // Proses data cuti - hanya untuk hari renungan yang tidak ada data absensi
        foreach ($leaves as $leave) {
            $startLeaveDate = Carbon::parse($leave->start_date);
            $endLeaveDate = Carbon::parse($leave->end_date);
            
            // Iterasi setiap hari dalam rentang cuti
            for ($date = $startLeaveDate->copy(); $date->lte($endLeaveDate); $date->addDay()) {
                $dateString = $date->toDateString();
                
                // Skip jika bukan hari renungan (Senin-Jumat)
                if (!$this->isReflectionDay($date)) {
                    continue;
                }
                
                // Skip jika tanggal sudah ada di data absensi (prioritas absensi)
                if (in_array($dateString . '_' . $leave->employee_id, $processedDates)) {
                    continue;
                }
                
                $employee = $allEmployees->get($leave->employee_id);
                $employeeName = $employee ? $employee->nama_lengkap : 'Karyawan Tidak Ditemukan';
                $employeePosition = $employee ? $employee->jabatan_saat_ini : '-';
                
                $processedDates[] = $dateString . '_' . $leave->employee_id;
                
                $combinedData[] = [
                    'id' => null, // Tidak ada ID untuk data cuti
                    'employee_id' => (int) $leave->employee_id,
                    'employee_name' => $employeeName,
                    'employee_position' => $employeePosition,
                    'date' => $dateString,
                    'status' => 'leave', // Status cuti
                    'join_time' => null,
                    'testing_mode' => false,
                    'created_at' => $leave->created_at,
                    'data_source' => 'leave'
                ];
            }
        }

        // Generate hari-hari renungan yang tidak ada data (status absent) untuk semua employee
        $combinedData = $this->fillMissingReflectionDaysForAll($combinedData, $allEmployees, $startDate, $endDate);

        // Urutkan berdasarkan tanggal (terbaru dulu), lalu berdasarkan nama employee
        usort($combinedData, function($a, $b) {
            $dateCompare = strcmp($b['date'], $a['date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return strcmp($a['employee_name'], $b['employee_name']);
        });

        return collect($combinedData);
    }

    /**
     * Mengisi hari-hari renungan yang tidak ada data dengan status absent untuk semua employee
     */
    private function fillMissingReflectionDaysForAll($combinedData, $allEmployees, $startDate, $endDate)
    {
        // Buat array tanggal yang sudah ada data per employee
        $existingDates = [];
        foreach ($combinedData as $record) {
            $key = $record['date'] . '_' . $record['employee_id'];
            $existingDates[$key] = true;
        }
        
        // Generate semua hari renungan dalam rentang tanggal
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // Skip jika bukan hari renungan (Senin-Jumat)
            if (!$this->isReflectionDay($date)) {
                continue;
            }
            
            $dateString = $date->toDateString();
            
            // Cek untuk setiap employee
            foreach ($allEmployees as $employee) {
                $key = $dateString . '_' . $employee->id;
                
                // Skip jika tanggal sudah ada data
                if (isset($existingDates[$key])) {
                    continue;
                }
                
                // Tambahkan data absent untuk hari renungan yang tidak ada data
                $combinedData[] = [
                    'id' => null,
                    'employee_id' => (int) $employee->id,
                    'employee_name' => $employee->nama_lengkap,
                    'employee_position' => $employee->jabatan_saat_ini,
                    'date' => $dateString,
                    'status' => 'absent',
                    'join_time' => null,
                    'testing_mode' => false,
                    'created_at' => null,
                    'data_source' => 'generated'
                ];
            }
        }
        
        return $combinedData;
    }

    /**
     * Mengecek apakah tanggal tersebut adalah hari renungan (Senin-Jumat)
     */
    private function isReflectionDay(Carbon $date)
    {
        // 1 = Senin, 2 = Selasa, 3 = Rabu, 4 = Kamis, 5 = Jumat
        // 6 = Sabtu, 7 = Minggu
        $dayOfWeek = $date->dayOfWeek;
        
        // Renungan pagi hanya Senin-Jumat (1-5)
        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }

    /**
     * Mapping status absensi ke format yang konsisten
     */
    private function mapAttendanceStatus($status)
    {
        $statusMap = [
            'Hadir' => 'present',
            'Terlambat' => 'late',
            'Absen' => 'absent',
            'present' => 'present',
            'late' => 'late',
            'absent' => 'absent'
        ];

        return $statusMap[$status] ?? 'absent';
    }


} 