<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MorningReflection;
use App\Models\LeaveRequest;
use App\Models\Attendance;
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
            $morningReflection = MorningReflection::firstOrCreate(
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

        // Validasi waktu: hanya boleh join antara 07:10 - 07:35 (bisa dilewati untuk testing)
        // Tambahan: bypass otomatis jika environment adalah local/testing
        $isTestingEnvironment = config('app.env') === 'local' || config('app.env') === 'testing';
        
        if (!$skipTimeValidation && !$isTestingEnvironment) {
            $startTime = Carbon::today()->setTime(7, 10); // 07:10
            $endTime = Carbon::today()->setTime(7, 35);   // 07:35 - Closed
            
            if ($now->lt($startTime) || $now->gt($endTime)) {
                return response()->json([
                    'errors' => ['time' => 'Absensi Zoom hanya dapat dilakukan antara pukul 07:10 - 07:35.']
                ], 422);
            }
        }

        try {
            DB::beginTransaction();
            
            // Tentukan status berdasarkan waktu klik
            // 07:10-07:30 = Hadir, 07:31-07:35 = Terlambat, >07:35 = Closed
            $cutoffTime = Carbon::today()->setTime(7, 30); // 07:30
            $status = $now->lte($cutoffTime) ? 'Hadir' : 'Terlambat';
            
            // Use firstOrCreate to handle race conditions atomically
            $morningReflection = MorningReflection::firstOrCreate(
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
        $reflections = MorningReflection::with('employee')->get();
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
    
    // ========== DASHBOARD GA - READ ONLY FUNCTIONS ==========
    
    /**
     * Dashboard GA - Tampilkan semua data absensi (seperti HR)
     * Bagian B: Dashboard GA - Tampilkan semua data absensi
     */
    public function getAllAttendances(Request $request)
    {
        try {
            $query = Attendance::with(['employee']);
            
            // Filter berdasarkan tanggal jika diminta
            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }
            
            if ($request->has('year')) {
                $query->whereYear('date', $request->year);
            }
            
            if ($request->has('month')) {
                $query->whereMonth('date', $request->month);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            $attendances = $query->orderBy('date', 'desc')
                                ->orderBy('check_in', 'asc')
                                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $attendances,
                'message' => 'Data absensi berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting all attendances for GA', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi'
            ], 500);
        }
    }
    
    /**
     * Dashboard GA - Tampilkan semua data cuti (seperti HR)
     * Bagian B: Dashboard GA - Tampilkan semua data cuti
     */
    public function getAllLeaveRequests(Request $request)
    {
        try {
            $query = LeaveRequest::with(['employee.user', 'approvedBy.user']);
            
            // Filter berdasarkan status jika diminta
            if ($request->has('overall_status')) {
                $query->where('overall_status', $request->overall_status);
            }
            
            if ($request->has('leave_type')) {
                $query->where('leave_type', $request->leave_type);
            }
            
            if ($request->has('year')) {
                $query->whereYear('start_date', $request->year);
            }
            
            if ($request->has('month')) {
                $query->whereMonth('start_date', $request->month);
            }
            
            $leaveRequests = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $leaveRequests,
                'message' => 'Data cuti berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting all leave requests for GA', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data cuti'
            ], 500);
        }
    }
    
    /**
     * Dashboard GA - Statistik absensi
     * Bagian B: Dashboard khusus absensi renungan pagi
     */
    public function getAttendanceStatistics(Request $request)
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->month;
            $thisYear = Carbon::now()->year;
            
            // Statistik hari ini
            $todayStats = [
                'total_present' => Attendance::whereDate('date', $today)->where('status', 'present')->count(),
                'total_absent' => Attendance::whereDate('date', $today)->where('status', 'absent')->count(),
                'total_sick' => Attendance::whereDate('date', $today)->where('status', 'sick')->count(),
                'total_leave' => Attendance::whereDate('date', $today)->where('status', 'leave')->count(),
            ];
            
            // Statistik bulan ini
            $monthlyStats = [
                'total_work_hours' => Attendance::whereMonth('date', $thisMonth)
                                               ->whereYear('date', $thisYear)
                                               ->sum('work_hours'),
                'total_overtime_hours' => Attendance::whereMonth('date', $thisMonth)
                                                   ->whereYear('date', $thisYear)
                                                   ->sum('overtime_hours'),
                'total_employees' => Employee::count(),
                'total_attendances_this_month' => Attendance::whereMonth('date', $thisMonth)
                                                           ->whereYear('date', $thisYear)
                                                           ->count()
            ];
            
            // Statistik renungan pagi
            $morningReflectionStats = [
                'today_present' => MorningReflection::whereDate('date', $today)->where('status', 'Hadir')->count(),
                'today_late' => MorningReflection::whereDate('date', $today)->where('status', 'Terlambat')->count(),
                'today_absent' => MorningReflection::whereDate('date', $today)->where('status', 'Absen')->count(),
                'monthly_total' => MorningReflection::whereMonth('date', $thisMonth)
                                                  ->whereYear('date', $thisYear)
                                                  ->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $todayStats,
                    'monthly' => $monthlyStats,
                    'morning_reflection' => $morningReflectionStats,
                    'date' => $today->toDateString()
                ],
                'message' => 'Statistik absensi berhasil diambil'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error getting attendance statistics for GA', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik absensi'
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
            $query = MorningReflection::with(['employee']);
            
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
    
}