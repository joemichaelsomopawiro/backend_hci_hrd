<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MorningReflectionAttendance;
use App\Models\LeaveRequest;
use App\Models\Attendance;
use App\Models\EmployeeAttendance;
use App\Services\RoleHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GeneralAffairController extends Controller
{
    // Get all employees for dropdown (Bagian A)
    public function getEmployees()
    {
        $employees = Employee::select('id', 'full_name')->get();
        return response()->json(['data' => $employees], 200);
    }

    // Store morning reflection attendance manually (Bagian A)
    public function storeMorningReflection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'status' => 'required|in:Hadir,Absen,Terlambat',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Use firstOrCreate to handle race conditions atomically
            $morningReflection = MorningReflectionAttendance::firstOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'date' => $request->date
                ],
                [
                    'status' => $request->status,
                    'join_time' => null
                ]
            );
            
            // Check if record was just created or already existed
            if (!$morningReflection->wasRecentlyCreated) {
                DB::rollBack();
                return response()->json([
                    'errors' => ['date' => 'Absensi untuk pegawai ini pada tanggal ini sudah ada.']
                ], 422);
            }
            
            DB::commit();
            
            Log::info('Manual attendance recorded', [
                'employee_id' => $request->employee_id,
                'date' => $request->date,
                'status' => $request->status
            ]);
            
            return response()->json([
                'data' => $morningReflection,
                'message' => 'Absensi berhasil disimpan'
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error storing manual attendance', [
                'employee_id' => $request->employee_id,
                'date' => $request->date,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'errors' => ['system' => 'Terjadi kesalahan sistem. Silakan coba lagi.']
            ], 500);
        }
    }

    // Record Zoom join for morning worship (Bagian A - Zoom Integration)
    public function recordZoomJoin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'zoom_link' => 'nullable|url', // Opsional, untuk mencatat link Zoom
            'skip_time_validation' => 'nullable|boolean', // Parameter untuk testing
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Use single timestamp for consistency
        $now = Carbon::now();
        $date = $now->toDateString();
        $dayOfWeek = $now->dayOfWeek; // 1 = Senin, 3 = Rabu, 5 = Jumat
        
        // Cek hari (Senin, Rabu, Jumat) - bisa dilewati untuk testing
        $skipTimeValidation = $request->input('skip_time_validation', false);
        
        if (!$skipTimeValidation && !in_array($dayOfWeek, [1, 3, 5])) {
            return response()->json([
                'errors' => ['day' => 'Worship pagi hanya diadakan pada Senin, Rabu, dan Jumat.']
            ], 422);
        }

        // Validasi waktu: hanya boleh join antara 07:10 - 08:00 (bisa dilewati untuk testing)
        // Tambahan: bypass otomatis jika environment adalah local/testing
        $isTestingEnvironment = config('app.env') === 'local' || config('app.env') === 'testing';
        
        if (!$skipTimeValidation && !$isTestingEnvironment) {
            $startTime = Carbon::today()->setTime(7, 10); // 07:10
            $endTime = Carbon::today()->setTime(8, 0);    // 08:00 - Closed
            
            if ($now->lt($startTime) || $now->gt($endTime)) {
                return response()->json([
                    'errors' => ['time' => 'Absensi Zoom hanya dapat dilakukan antara pukul 07:10 - 08:00.']
                ], 422);
            }
        }

        try {
            DB::beginTransaction();
            
            // Tentukan status berdasarkan waktu klik
            // 07:10-07:30 = Hadir, 07:31-07:35 = Terlambat, 07:35-08:00 = Tidak Hadir
            $cutoffTime = Carbon::today()->setTime(7, 30); // 07:30
            $lateCutoffTime = Carbon::today()->setTime(7, 35); // 07:35
            
            if ($now->lte($cutoffTime)) {
                $status = 'Hadir';
            } elseif ($now->lte($lateCutoffTime)) {
                $status = 'Terlambat';
            } else {
                $status = 'Tidak Hadir';
            }
            
            // Use firstOrCreate to handle race conditions atomically
            $morningReflection = MorningReflectionAttendance::firstOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'date' => $date
                ],
                [
                    'status' => $status,
                    'join_time' => $now
                ]
            );
            
            // Check if record was just created or already existed
            if (!$morningReflection->wasRecentlyCreated) {
                DB::rollBack();
                
                Log::warning('Duplicate Zoom attendance attempt', [
                    'employee_id' => $request->employee_id,
                    'date' => $date,
                    'existing_status' => $morningReflection->status,
                    'existing_join_time' => $morningReflection->join_time
                ]);
                
                return response()->json([
                    'errors' => ['date' => 'Absensi untuk pegawai ini hari ini sudah ada.'],
                    'existing_data' => [
                        'status' => $morningReflection->status,
                        'join_time' => $morningReflection->join_time,
                        'date' => $morningReflection->date
                    ]
                ], 422);
            }
            
            DB::commit();
            
            Log::info('Zoom attendance recorded successfully', [
                'employee_id' => $request->employee_id,
                'date' => $date,
                'status' => $status,
                'join_time' => $now->toDateTimeString()
            ]);
            
            // Kembalikan data absensi dan link Zoom
            return response()->json([
                'data' => $morningReflection,
                'message' => 'Absensi Zoom berhasil dicatat',
                'zoom_link' => $request->zoom_link ?? 'https://zoom.us/j/meeting'
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error recording Zoom attendance', [
                'employee_id' => $request->employee_id,
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if it's a duplicate key error (unique constraint violation)
            if (str_contains($e->getMessage(), 'unique_employee_date_attendance') || 
                str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'errors' => ['date' => 'Absensi untuk pegawai ini hari ini sudah ada.']
                ], 422);
            }
            
            return response()->json([
                'errors' => ['system' => 'Terjadi kesalahan sistem. Silakan coba lagi.']
            ], 500);
        }
    }

    // Get all morning reflections for dashboard (Bagian C)
    public function getMorningReflections()
    {
        $reflections = MorningReflectionAttendance::with('employee')->get();
        return response()->json(['data' => $reflections], 200);
    }

    // Get all leaves for dashboard (Bagian C)
    public function getLeaves()
    {
        // Pastikan user sudah login
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        $query = LeaveRequest::with('employee');
        
        // Otorisasi berdasarkan role - sama seperti di LeaveRequestController
        if (RoleHierarchyService::isHrManager($user->role)) {
            // HR hanya dapat melihat permohonan dari bawahannya langsung (Finance, General Affairs, Office Assistant)
            $hrSubordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role);
            if (!empty($hrSubordinateRoles)) {
                $query->whereHas('employee.user', function ($q) use ($hrSubordinateRoles) {
                    $q->whereIn('role', $hrSubordinateRoles);
                });
            } else {
                return response()->json(['success' => true, 'data' => []]);
            }
        } elseif (RoleHierarchyService::isOtherManager($user->role)) {
            // Manager lain (Program/Distribution) hanya bisa melihat bawahannya
            $subordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role);
            if (!empty($subordinateRoles)) {
                $query->whereHas('employee.user', function ($q) use ($subordinateRoles) {
                    $q->whereIn('role', $subordinateRoles);
                });
            } else {
                return response()->json(['success' => true, 'data' => []]);
            }
        } else {
            // Karyawan biasa hanya bisa melihat permohonannya sendiri
            $query->where('employee_id', $user->employee_id);
        }
        
        $leaves = $query->get();
        return response()->json(['data' => $leaves], 200);
    }

    /**
     * Dashboard GA - Statistik absensi renungan pagi
     * Bagian B: Dashboard khusus absensi renungan pagi
     */
    public function getAttendanceStatistics(Request $request)
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->month;
            $thisYear = Carbon::now()->year;
            
            // Statistik renungan pagi
            $morningReflectionStats = [
                'today_present' => MorningReflectionAttendance::whereDate('date', $today)->where('status', 'Hadir')->count(),
                'today_late' => MorningReflectionAttendance::whereDate('date', $today)->where('status', 'Terlambat')->count(),
                'today_absent' => MorningReflectionAttendance::whereDate('date', $today)->whereIn('status', ['Absen', 'Tidak Hadir'])->count(),
                'monthly_total' => MorningReflectionAttendance::whereMonth('date', $thisMonth)
                                                  ->whereYear('date', $thisYear)
                                                  ->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'morning_reflection_attendance' => $morningReflectionStats,
                    'date' => $today->toDateString()
                ],
                'message' => 'Statistik absensi renungan pagi berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting morning reflection statistics for GA', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik absensi renungan pagi'
            ], 500);
        }
    }
    
    /**
     * Dashboard GA - Statistik cuti
     */
    public function getLeaveStatistics(Request $request)
    {
        try {
            $thisMonth = Carbon::now()->month;
            $thisYear = Carbon::now()->year;
            
            // Statistik cuti
            $leaveStats = [
                'pending' => LeaveRequest::where('overall_status', 'pending')->count(),
                'approved' => LeaveRequest::where('overall_status', 'approved')->count(),
                'rejected' => LeaveRequest::where('overall_status', 'rejected')->count(),
                'this_month' => LeaveRequest::whereMonth('start_date', $thisMonth)
                                          ->whereYear('start_date', $thisYear)
                                          ->count(),
                'this_year' => LeaveRequest::whereYear('start_date', $thisYear)->count()
            ];
            
            // Statistik berdasarkan jenis cuti
            $leaveTypeStats = [
                'annual' => LeaveRequest::where('leave_type', 'annual')->count(),
                'sick' => LeaveRequest::where('leave_type', 'sick')->count(),
                'maternity' => LeaveRequest::where('leave_type', 'maternity')->count(),
                'paternity' => LeaveRequest::where('leave_type', 'paternity')->count(),
                'marriage' => LeaveRequest::where('leave_type', 'marriage')->count(),
                'emergency' => LeaveRequest::where('leave_type', 'emergency')->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'status_summary' => $leaveStats,
                    'type_summary' => $leaveTypeStats
                ],
                'message' => 'Statistik cuti berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting leave statistics for GA', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik cuti'
            ], 500);
        }
    }
    
    /**
     * Dashboard GA - Riwayat kehadiran harian renungan pagi
     * Bagian A: Riwayat kehadiran harian
     */
    public function getDailyMorningReflectionHistory(Request $request)
    {
        try {
            $query = MorningReflectionAttendance::with(['employee']);
            
            // Filter berdasarkan tanggal jika diminta
            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            } else {
                // Default tampilkan hari ini
                $query->whereDate('date', Carbon::today());
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            $reflections = $query->orderBy('join_time', 'asc')
                                ->orderBy('created_at', 'asc')
                                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $reflections,
                'message' => 'Riwayat kehadiran renungan pagi berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting daily morning reflection history for GA', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil riwayat kehadiran'
            ], 500);
        }
    }

    /**
     * Get all leave requests for GA Dashboard
     * Endpoint: GET /ga/dashboard/leave-requests
     */
    public function getAllLeaveRequests(Request $request)
    {
        try {
            // Pastikan user sudah login dan memiliki role GA
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Validasi role GA/Admin
            if (!in_array($user->role, ['General Affairs', 'Admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya GA/Admin yang dapat mengakses endpoint ini.'
                ], 403);
            }

            $query = LeaveRequest::with(['employee', 'approvedBy.user']);
            
            // Apply filters
            if ($request->has('status')) {
                $query->where('overall_status', $request->status);
            }
            
            if ($request->has('leave_type')) {
                $query->where('leave_type', $request->leave_type);
            }
            
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            
            if ($request->has('start_date')) {
                $query->whereDate('start_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('end_date', '<=', $request->end_date);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $leaveRequests = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Transform data untuk response format yang diinginkan dengan validasi
            $transformedData = $leaveRequests->getCollection()->map(function ($leave) {
                // Validasi data employee
                if (!$leave->employee) {
                    Log::warning('Leave request without employee data', ['leave_id' => $leave->id]);
                    return null; // Skip data yang tidak valid
                }
                
                return [
                    'id' => $leave->id,
                    'employee' => [
                        'id' => $leave->employee->id,
                        'nama_lengkap' => $leave->employee->nama_lengkap ?? 'Data tidak tersedia'
                    ],
                    'leave_type' => $leave->leave_type ?? 'unknown',
                    'start_date' => $leave->start_date,
                    'end_date' => $leave->end_date,
                    'duration' => $leave->total_days ?? 0,
                    'reason' => $leave->reason ?? 'Tidak ada keterangan',
                    'overall_status' => $leave->overall_status ?? 'pending',
                    'created_at' => $leave->created_at,
                    'updated_at' => $leave->updated_at
                ];
            })->filter(); // Remove null values
            
            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $leaveRequests->currentPage(),
                    'last_page' => $leaveRequests->lastPage(),
                    'per_page' => $leaveRequests->perPage(),
                    'total' => $leaveRequests->total()
                ],
                'message' => 'Data cuti berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting all leave requests for GA', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data cuti'
            ], 500);
        }
    }

    /**
     * Get all attendances for GA Dashboard with leave integration
     * Endpoint: GET /ga/dashboard/attendances
     */
    public function getAllAttendances(Request $request)
    {
        try {
            // Pastikan user sudah login dan memiliki role GA
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Validasi role GA/Admin
            if (!in_array($user->role, ['General Affairs', 'Admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya GA/Admin yang dapat mengakses endpoint ini.'
                ], 403);
            }

            // Jika ada filter employee_id, gunakan logika integrasi cuti
            if ($request->has('employee_id')) {
                return $this->getIntegratedAttendanceForGA($request);
            }

            // Untuk request tanpa employee_id, tampilkan semua data dengan integrasi cuti
            return $this->getAllAttendancesWithLeaveIntegration($request);
            
        } catch (Exception $e) {
            Log::error('Error getting all attendances for GA', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi'
            ], 500);
        }
    }

    /**
     * Mendapatkan data absensi yang terintegrasi dengan data cuti untuk GA Dashboard
     */
    private function getIntegratedAttendanceForGA(Request $request)
    {
        try {
            $employeeId = $request->employee_id;
            $dateFilter = $request->date;

            // Ambil data absensi umum (EmployeeAttendance)
            $attendanceQuery = EmployeeAttendance::with('employee')
                ->where('employee_id', $employeeId);
            
            if ($dateFilter) {
                $attendanceQuery->whereDate('date', $dateFilter);
            }
            
            $attendances = $attendanceQuery->get();

            // Ambil data cuti yang disetujui
            $leaveQuery = LeaveRequest::with('employee')
                ->where('employee_id', $employeeId)
                ->where('overall_status', 'approved');
            
            if ($dateFilter) {
                $leaveQuery->where(function($query) use ($dateFilter) {
                    $query->whereDate('start_date', '<=', $dateFilter)
                          ->whereDate('end_date', '>=', $dateFilter);
                });
            }
            
            $leaves = $leaveQuery->get();

            // Gabungkan data berdasarkan tanggal
            $combinedData = $this->mergeGeneralAttendanceAndLeave($attendances, $leaves, $employeeId, $dateFilter);

            // Pagination manual untuk data yang sudah digabung
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $total = count($combinedData);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedData = array_slice($combinedData, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => $paginatedData,
                'pagination' => [
                    'current_page' => $currentPage,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total
                ],
                'message' => 'Data absensi dan cuti terintegrasi berhasil diambil'
            ], 200);
        } catch (Exception $e) {
            Log::error('Error getting integrated attendance for GA', [
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi terintegrasi'
            ], 500);
        }
    }

    /**
     * Menggabungkan data absensi umum dan cuti berdasarkan tanggal
     */
    private function mergeGeneralAttendanceAndLeave($attendances, $leaves, $employeeId, $dateFilter = null)
    {
        $combinedData = [];
        $processedDates = [];

        // Ambil data employee untuk nama
        $employee = Employee::find($employeeId);
        $employeeName = $employee ? $employee->nama_lengkap : 'Karyawan Tidak Ditemukan';

        // Proses data absensi umum
        foreach ($attendances as $attendance) {
            $date = Carbon::parse($attendance->date)->toDateString();
            $processedDates[] = $date;
            
            $combinedData[] = [
                'id' => $attendance->id,
                'employee_id' => (int) $attendance->employee_id,
                'employee_name' => $employeeName,
                'date' => $date,
                'status' => $this->mapGeneralAttendanceStatus($attendance->status),
                'check_in_time' => $attendance->check_in,
                'check_out_time' => $attendance->check_out,
                'work_hours' => $attendance->work_hours,
                'leave_type' => null,
                'leave_reason' => null,
                'data_source' => 'attendance',
                'employee' => $attendance->employee ? [
                    'id' => (int) $attendance->employee->id,
                    'nama_lengkap' => $attendance->employee->nama_lengkap
                ] : null
            ];
        }

        // Proses data cuti - expand untuk setiap hari dalam rentang cuti
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);
            
            // Iterasi setiap hari dalam rentang cuti
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateString = $date->toDateString();
                
                // Skip jika tanggal sudah ada di data absensi (prioritas absensi)
                if (in_array($dateString, $processedDates)) {
                    continue;
                }
                
                // Skip jika ada filter tanggal dan tidak sesuai
                if ($dateFilter && $dateString !== $dateFilter) {
                    continue;
                }
                
                $processedDates[] = $dateString;
                
                $combinedData[] = [
                    'id' => null, // Tidak ada ID untuk data cuti
                    'employee_id' => (int) $leave->employee_id,
                    'employee_name' => $employeeName,
                    'date' => $dateString,
                    'status' => 'leave', // Status cuti
                    'check_in_time' => null,
                    'check_out_time' => null,
                    'work_hours' => null,
                    'leave_type' => $leave->leave_type,
                    'leave_reason' => $leave->reason,
                    'data_source' => 'leave',
                    'employee' => $leave->employee ? [
                        'id' => (int) $leave->employee->id,
                        'nama_lengkap' => $leave->employee->nama_lengkap
                    ] : null
                ];
            }
        }

        // Urutkan berdasarkan tanggal (terbaru dulu)
        usort($combinedData, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $combinedData;
    }

    /**
     * Mapping status absensi umum ke format yang konsisten
     */
    private function mapGeneralAttendanceStatus($status)
    {
        $statusMap = [
            'Present' => 'present',
            'Hadir' => 'present',
            'Late' => 'late',
            'Terlambat' => 'late',
            'Absent' => 'absent',
            'Absen' => 'absent',
            'Tidak Hadir' => 'absent',
            'Half Day' => 'half_day',
            'Setengah Hari' => 'half_day'
        ];

        return $statusMap[$status] ?? strtolower($status);
    }

    /**
     * Mendapatkan semua data absensi dengan integrasi cuti untuk semua karyawan
     */
    private function getAllAttendancesWithLeaveIntegration(Request $request)
    {
        try {
            // Tentukan tanggal filter
            $dateFilter = $request->has('date') ? $request->date : Carbon::today()->toDateString();
            
            // Ambil semua data absensi untuk tanggal tersebut
            $query = EmployeeAttendance::with(['employee'])
                ->whereDate('date', $dateFilter);
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            $attendances = $query->get();

            // Ambil semua data cuti yang disetujui untuk tanggal tersebut
            $leaves = LeaveRequest::with('employee')
                ->where('status', 'approved')
                ->where('start_date', '<=', $dateFilter)
                ->where('end_date', '>=', $dateFilter)
                ->get();

            // Gabungkan data absensi dan cuti
            $combinedData = $this->mergeAllAttendancesAndLeaves($attendances, $leaves, $dateFilter);

            // Filter berdasarkan status jika diminta
            if ($request->has('status')) {
                $statusFilter = $request->status;
                $combinedData = array_filter($combinedData, function($item) use ($statusFilter) {
                    return $item['status'] === $statusFilter;
                });
                $combinedData = array_values($combinedData); // Re-index array
            }

            // Pagination manual
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $total = count($combinedData);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedData = array_slice($combinedData, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => $paginatedData,
                'pagination' => [
                    'current_page' => (int) $currentPage,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => (int) $perPage,
                    'total' => $total
                ],
                'message' => 'Data absensi dengan integrasi cuti berhasil diambil',
                'filters' => [
                    'date' => $dateFilter,
                    'status' => $request->get('status'),
                    'integrated_leave_data' => true
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error getting all attendances with leave integration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi terintegrasi'
            ], 500);
        }
    }

    /**
     * Menggabungkan data absensi dan cuti untuk semua karyawan pada tanggal tertentu
     */
    private function mergeAllAttendancesAndLeaves($attendances, $leaves, $dateFilter)
    {
        $combinedData = [];
        $processedEmployees = [];

        // Proses data absensi yang ada
        foreach ($attendances as $attendance) {
            $employeeId = $attendance->employee_id;
            $processedEmployees[] = $employeeId;
            
            $combinedData[] = [
                'id' => $attendance->id,
                'employee_id' => (int) $employeeId,
                'employee_name' => $attendance->employee ? $attendance->employee->nama_lengkap : 'Karyawan Tidak Ditemukan',
                'date' => $dateFilter,
                'status' => $this->mapGeneralAttendanceStatus($attendance->status),
                'check_in_time' => $attendance->check_in,
                'check_out_time' => $attendance->check_out,
                'work_hours' => $attendance->work_hours,
                'leave_type' => null,
                'leave_reason' => null,
                'data_source' => 'attendance',
                'employee' => $attendance->employee ? [
                    'id' => (int) $attendance->employee->id,
                    'nama_lengkap' => $attendance->employee->nama_lengkap
                ] : null
            ];
        }

        // Proses data cuti untuk karyawan yang tidak ada data absensi
        foreach ($leaves as $leave) {
            $employeeId = $leave->employee_id;
            
            // Skip jika karyawan sudah ada data absensi (prioritas absensi)
            if (in_array($employeeId, $processedEmployees)) {
                continue;
            }
            
            $combinedData[] = [
                'id' => null,
                'employee_id' => (int) $employeeId,
                'employee_name' => $leave->employee ? $leave->employee->nama_lengkap : 'Karyawan Tidak Ditemukan',
                'date' => $dateFilter,
                'status' => 'leave',
                'check_in_time' => null,
                'check_out_time' => null,
                'work_hours' => null,
                'leave_type' => $leave->leave_type,
                'leave_reason' => $leave->reason,
                'data_source' => 'leave',
                'employee' => $leave->employee ? [
                    'id' => (int) $leave->employee->id,
                    'nama_lengkap' => $leave->employee->nama_lengkap
                ] : null
            ];
        }

        // Urutkan berdasarkan nama karyawan
        usort($combinedData, function($a, $b) {
            return strcmp($a['employee_name'], $b['employee_name']);
        });

        return $combinedData;
    }
}
