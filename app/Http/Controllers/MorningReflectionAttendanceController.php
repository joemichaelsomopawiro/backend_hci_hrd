<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class MorningReflectionAttendanceController extends Controller
{
    public function getAttendance(Request $request)
    {
        try {
            // Jika ada filter employee_id, gunakan logika integrasi cuti
            if ($request->employee_id) {
                return $this->getIntegratedAttendanceForEmployee($request);
            }

            // Untuk request tanpa employee_id, ambil semua data dengan integrasi cuti
            return $this->getAllIntegratedAttendance($request);
        } catch (Exception $e) {
            Log::error('Error getting morning reflection attendance', [
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
     * Mendapatkan semua data absensi yang terintegrasi dengan data cuti
     */
    private function getAllIntegratedAttendance(Request $request)
    {
        try {
            $dateFilter = $request->date;
            $combinedData = [];

            // Ambil semua employee yang memiliki data absensi atau cuti
            $employeeIds = collect();
            
            // Employee dengan data absensi
            $attendanceEmployeeIds = MorningReflectionAttendance::when($dateFilter, function($query, $date) {
                    return $query->whereDate('date', $date);
                })
                ->distinct()
                ->pluck('employee_id');
            
            $employeeIds = $employeeIds->merge($attendanceEmployeeIds);

            // Employee dengan data cuti yang disetujui
            $leaveEmployeeIds = LeaveRequest::where('overall_status', 'approved')
                ->when($dateFilter, function($query, $date) {
                    return $query->where(function($q) use ($date) {
                        $q->whereDate('start_date', '<=', $date)
                          ->whereDate('end_date', '>=', $date);
                    });
                })
                ->distinct()
                ->pluck('employee_id');
            
            $employeeIds = $employeeIds->merge($leaveEmployeeIds)->unique();

            // Proses setiap employee
            foreach ($employeeIds as $employeeId) {
                // Ambil data absensi untuk employee ini
                $attendanceQuery = MorningReflectionAttendance::with('employee')
                    ->where('employee_id', $employeeId);
                
                if ($dateFilter) {
                    $attendanceQuery->whereDate('date', $dateFilter);
                }
                
                $attendances = $attendanceQuery->get();

                // Ambil data cuti yang disetujui untuk employee ini
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

                // Gabungkan data untuk employee ini
                $employeeData = $this->mergeAttendanceAndLeave($attendances, $leaves, $employeeId, $dateFilter);
                $combinedData = array_merge($combinedData, $employeeData);
            }

            // Urutkan berdasarkan tanggal (terbaru dulu)
            usort($combinedData, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });

            return response()->json([
                'success' => true,
                'data' => $combinedData,
                'message' => 'Data absensi renungan pagi berhasil diambil',
                'total_records' => count($combinedData)
            ], 200);
        } catch (Exception $e) {
            Log::error('Error getting morning reflection attendance', [
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
     * Mendapatkan data absensi yang terintegrasi dengan data cuti untuk employee tertentu
     */
    private function getIntegratedAttendanceForEmployee(Request $request)
    {
        try {
            $employeeId = $request->employee_id;
            $dateFilter = $request->date;
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            // Ambil SEMUA data absensi (online & manual) untuk employee ini
            $attendanceQuery = MorningReflectionAttendance::with('employee')
                ->where('employee_id', $employeeId);
            if ($dateFilter) {
                $attendanceQuery->whereDate('date', $dateFilter);
            }
            // Tidak filter attendance_method, ambil SEMUA (online & manual)
            $attendances = $attendanceQuery
                ->orderBy('date', 'desc')
                ->orderBy('join_time', 'desc')
                ->get();

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

            // Tentukan rentang tanggal (dari absensi/cuti terawal sampai hari ini)
            $attendanceDates = $attendances->pluck('date')->toArray();
            $leaveDates = [];
            foreach ($leaves as $leave) {
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $leaveDates[] = $date->toDateString();
                }
            }
            $allDates = array_merge($attendanceDates, $leaveDates);
            $allDates = array_filter($allDates); // hapus null
            $startDate = $allDates ? min($allDates) : Carbon::now()->toDateString();
            $endDate = Carbon::now()->toDateString();
            if ($dateFilter) {
                $startDate = $dateFilter;
                $endDate = $dateFilter;
            }

            // Gabungkan data berdasarkan tanggal
            $combinedData = $this->mergeAttendanceAndLeave($attendances, $leaves, $employeeId, $dateFilter);
            // Isi hari kosong (absen)
            $combinedData = $this->fillMissingReflectionDays($combinedData, $employeeId, $startDate, $endDate);

            // Paginasi manual
            $total = count($combinedData);
            $offset = ($page - 1) * $perPage;
            $paginatedData = array_slice($combinedData, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'history' => $paginatedData
                ],
                'message' => 'Data absensi dan cuti berhasil diambil',
                'total_records' => $total,
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total)
                ],
                'filters' => [
                    'employee_id' => (int) $employeeId,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error getting integrated attendance for employee', [
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi terintegrasi',
                'data' => [
                    'history' => []
                ]
            ], 500);
        }
    }

    /**
     * Menggabungkan data absensi dan cuti berdasarkan tanggal - hanya untuk hari renungan
     */
    private function mergeAttendanceAndLeave($attendances, $leaves, $employeeId, $dateFilter = null)
    {
        $combinedData = [];
        $processedDates = [];

        // Ambil data employee untuk nama
        $employee = Employee::find($employeeId);
        $employeeName = $employee ? $employee->nama_lengkap : 'Karyawan Tidak Ditemukan';

        // Proses data absensi - filter hanya hari renungan (Senin, Rabu, Jumat)
        foreach ($attendances as $attendance) {
            $date = Carbon::parse($attendance->date);
            $dateString = $date->toDateString();

            // Skip jika bukan hari renungan (Senin, Rabu, Jumat)
            if (!$this->isReflectionDay($date)) {
                continue;
            }

            $processedDates[] = $dateString;

            // Mapping status cuti dari absensi
            $status = $this->mapAttendanceStatus($attendance->status);
            if (strtolower($attendance->status) === 'cuti') {
                $status = 'leave';
            }

            $combinedData[] = [
                'id' => $attendance->id,
                'employee_id' => (int) $attendance->employee_id,
                'employee_name' => $employeeName,
                'date' => $dateString,
                'status' => $status,
                'join_time' => $attendance->join_time,
                'check_in_time' => $attendance->join_time, // Alias untuk konsistensi
                'check_out_time' => null,
                'leave_type' => property_exists($attendance, 'leave_type') ? $attendance->leave_type : null,
                'leave_reason' => property_exists($attendance, 'leave_reason') ? $attendance->leave_reason : null,
                'data_source' => strtolower($attendance->status) === 'cuti' ? 'leave' : 'attendance',
                'attendance_method' => $attendance->attendance_method ?? null,
                'attendance_source' => $attendance->attendance_source ?? null,
                'employee' => $attendance->employee ? [
                    'id' => (int) $attendance->employee->id,
                    'nama_lengkap' => $attendance->employee->nama_lengkap
                ] : null
            ];
        }

        // Proses data cuti - hanya untuk hari renungan yang tidak ada data absensi
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);

            // Iterasi setiap hari dalam rentang cuti
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateString = $date->toDateString();

                // Skip jika bukan hari renungan (Senin, Rabu, Jumat)
                if (!$this->isReflectionDay($date)) {
                    continue;
                }

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
                    'join_time' => null,
                    'check_in_time' => null,
                    'check_out_time' => null,
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
     * Mengecek apakah tanggal tersebut adalah hari renungan (Senin, Rabu, Jumat)
     */
    private function isReflectionDay(Carbon $date)
    {
        // 1 = Senin, 2 = Selasa, 3 = Rabu, 4 = Kamis, 5 = Jumat
        // 6 = Sabtu, 0 = Minggu (Carbon: 0=Sunday, 6=Saturday)
        $dayOfWeek = $date->dayOfWeek;
        // Sekarang: hari kerja Senin-Jumat (1-5)
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
            'izin' => 'izin',
            'leave' => 'leave',
            'present' => 'present',
            'late' => 'late',
            'absent' => 'absent',
            'Present' => 'present',
            'Late' => 'late',
            'Absent' => 'absent'
        ];

        return $statusMap[$status] ?? 'absent'; // Default ke absent jika status tidak dikenal
    }

    public function attend(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|integer|exists:employees,id',
                'date' => 'nullable|date',
                'status' => 'nullable|in:Hadir,Terlambat,Absen',
                'join_time' => 'nullable|date_format:Y-m-d H:i:s',
                'testing_mode' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = $request->date ?? Carbon::now('Asia/Jakarta')->toDateString();
            $status = $request->status ?? 'Hadir';
            $joinTime = $request->join_time ?? Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
            $testingMode = $request->testing_mode ?? false;

            // Cek apakah sudah absen hari ini
            $existingAttendance = MorningReflectionAttendance::where('employee_id', $request->employee_id)
                ->whereDate('date', $date)
                ->first();

            if ($existingAttendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah hadir di renungan pagi hari ini',
                    'existing_data' => [
                        'status' => $existingAttendance->status,
                        'join_time' => $existingAttendance->join_time,
                        'date' => $existingAttendance->date
                    ]
                ], 422);
            }

            // Buat record baru
            $attendance = MorningReflectionAttendance::create([
                'employee_id' => $request->employee_id,
                'date' => $date,
                'status' => $status,
                'join_time' => $joinTime,
                'testing_mode' => $testingMode
            ]);

            // Load relasi employee dan transform data
            $attendance->load('employee');
            $data = $attendance->toArray();
            $data['employee_id'] = (int) $data['employee_id'];
            
            if ($attendance->employee) {
                $data['employee_name'] = $attendance->employee->nama_lengkap;
                $data['employee']['id'] = (int) $attendance->employee->id;
            } else {
                $data['employee_name'] = 'Karyawan Tidak Ditemukan';
                $data['employee'] = null;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Kehadiran renungan pagi berhasil dicatat'
            ], 201);

        } catch (Exception $e) {
            Log::error('Error recording morning reflection attendance', [
                'employee_id' => $request->employee_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mencatat kehadiran',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil riwayat renungan pagi untuk karyawan tertentu
     * Hanya menampilkan hari-hari renungan (Senin-Jumat)
     */
    public function getHistory($employeeId, Request $request)
    {
        try {
            // Validasi employee_id
            $employee = Employee::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Parameter untuk filter tanggal
            $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', Carbon::now()->toDateString());
            $perPage = $request->get('per_page', 15);

            // Validasi format tanggal
            try {
                $startDate = Carbon::parse($startDate)->toDateString();
                $endDate = Carbon::parse($endDate)->toDateString();
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format'
                ], 400);
            }

            // Ambil data absensi renungan dalam rentang tanggal
            $attendances = MorningReflectionAttendance::with('employee')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            // Ambil data cuti yang disetujui dalam rentang tanggal
             $leaves = LeaveRequest::with('employee')
                 ->where('employee_id', $employeeId)
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

            // Gabungkan data absensi dan cuti (hanya hari renungan)
            $combinedData = $this->mergeAttendanceAndLeave($attendances, $leaves, $employeeId);

            // Generate hari-hari renungan yang tidak ada data (status absent)
            $combinedData = $this->fillMissingReflectionDays($combinedData, $employeeId, $startDate, $endDate);

            // Paginasi manual
            $total = count($combinedData);
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedData = array_slice($combinedData, $offset, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Morning reflection history retrieved successfully',
                'data' => $paginatedData,
                'pagination' => [
                    'current_page' => (int) $currentPage,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total)
                ],
                'filters' => [
                    'employee_id' => (int) $employeeId,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting morning reflection history', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve morning reflection history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengisi hari-hari renungan yang tidak ada data dengan status absent
     */
    private function fillMissingReflectionDays($combinedData, $employeeId, $startDate, $endDate)
    {
        $employee = Employee::find($employeeId);
        $employeeName = $employee ? $employee->nama_lengkap : 'Karyawan Tidak Ditemukan';
        
        // Buat array tanggal yang sudah ada data
        $existingDates = array_column($combinedData, 'date');
        
        // Generate semua hari renungan dalam rentang tanggal
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // Skip jika bukan hari renungan (Senin-Jumat)
            if (!$this->isReflectionDay($date)) {
                continue;
            }
            
            $dateString = $date->toDateString();
            
            // Skip jika tanggal sudah ada data
            if (in_array($dateString, $existingDates)) {
                continue;
            }
            
            // Tambahkan data absent untuk hari renungan yang tidak ada data
            $combinedData[] = [
                'id' => null,
                'employee_id' => (int) $employeeId,
                'employee_name' => $employeeName,
                'date' => $dateString,
                'status' => 'absent',
                'join_time' => null,
                'check_in_time' => null,
                'check_out_time' => null,
                'leave_type' => null,
                'leave_reason' => null,
                'data_source' => 'generated',
                'employee' => $employee ? [
                    'id' => (int) $employee->id,
                    'nama_lengkap' => $employee->nama_lengkap
                ] : null
            ];
        }
        
        // Urutkan berdasarkan tanggal (terbaru dulu)
        usort($combinedData, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        return $combinedData;
    }
}