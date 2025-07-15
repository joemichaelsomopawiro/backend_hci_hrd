<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MorningReflectionAttendance;
use App\Models\LeaveRequest;
use App\Models\Employee;
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

    /**
     * Mendapatkan label status untuk frontend
     */
    private function getStatusLabel($status)
    {
        $statusLabels = [
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'leave' => 'Cuti',
            'not_worship_day' => 'Bukan Jadwal'
        ];

        return $statusLabels[$status] ?? 'Tidak Diketahui';
    }
} 