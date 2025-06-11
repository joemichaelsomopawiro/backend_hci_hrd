<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with('employee');
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }
        
        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', $request->year);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $attendances = $query->orderBy('date', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $attendances
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,sick,leave,permission,overtime',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Cek apakah sudah ada absensi untuk tanggal tersebut
        $existingAttendance = Attendance::where('employee_id', $request->employee_id)
                                       ->where('date', $request->date)
                                       ->first();
        
        if ($existingAttendance) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi untuk tanggal tersebut sudah ada'
            ], 400);
        }

        $attendance = new Attendance($request->all());
        
        // Hitung jam kerja otomatis jika check_in dan check_out ada
        if ($request->check_in && $request->check_out) {
            $attendance->work_hours = $attendance->calculateWorkHours();
            $attendance->overtime_hours = $attendance->calculateOvertimeHours();
        }
        
        $attendance->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Absensi berhasil dicatat',
            'data' => $attendance->load('employee')
        ], 201);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $today = Carbon::today();
        $now = Carbon::now();
        
        // Cek apakah sudah check-in hari ini
        $attendance = Attendance::where('employee_id', $request->employee_id)
                               ->where('date', $today)
                               ->first();
        
        if ($attendance && $attendance->check_in) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah melakukan check-in hari ini'
            ], 400);
        }
        
        if (!$attendance) {
            $attendance = Attendance::create([
                'employee_id' => $request->employee_id,
                'date' => $today,
                'check_in' => $now->format('H:i'),
                'status' => 'present',
            ]);
        } else {
            $attendance->update([
                'check_in' => $now->format('H:i'),
                'status' => 'present',
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Check-in berhasil',
            'data' => $attendance->load('employee')
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $today = Carbon::today();
        $now = Carbon::now();
        
        $attendance = Attendance::where('employee_id', $request->employee_id)
                               ->where('date', $today)
                               ->first();
        
        if (!$attendance || !$attendance->check_in) {
            return response()->json([
                'success' => false,
                'message' => 'Belum melakukan check-in hari ini'
            ], 400);
        }
        
        if ($attendance->check_out) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah melakukan check-out hari ini'
            ], 400);
        }
        
        $attendance->update([
            'check_out' => $now->format('H:i'),
        ]);
        
        // Hitung ulang jam kerja dan lembur
        $attendance->work_hours = $attendance->calculateWorkHours();
        $attendance->overtime_hours = $attendance->calculateOvertimeHours();
        $attendance->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Check-out berhasil',
            'data' => $attendance->load('employee')
        ]);
    }

    public function workHoursSummary(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
        ]);

        $query = Attendance::query();
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        $summary = $query->whereMonth('date', $request->month)
                        ->whereYear('date', $request->year)
                        ->select(
                            'employee_id',
                            DB::raw('SUM(work_hours) as total_work_hours'),
                            DB::raw('SUM(overtime_hours) as total_overtime_hours'),
                            DB::raw('COUNT(CASE WHEN status = "present" THEN 1 END) as present_days'),
                            DB::raw('COUNT(CASE WHEN status = "absent" THEN 1 END) as absent_days'),
                            DB::raw('COUNT(CASE WHEN status = "sick" THEN 1 END) as sick_days'),
                            DB::raw('COUNT(CASE WHEN status = "leave" THEN 1 END) as leave_days'),
                            DB::raw('COUNT(CASE WHEN status = "permission" THEN 1 END) as permission_days')
                        )
                        ->groupBy('employee_id')
                        ->with('employee')
                        ->get();
        
        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
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
        ];
        
        // Permohonan cuti pending
        $pendingLeaveRequests = \App\Models\LeaveRequest::where('status', 'pending')->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'today' => $todayStats,
                'monthly' => $monthlyStats,
                'pending_leave_requests' => $pendingLeaveRequests,
            ]
        ]);
    }
}