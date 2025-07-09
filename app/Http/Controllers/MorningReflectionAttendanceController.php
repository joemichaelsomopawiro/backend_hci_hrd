<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
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
            // Ambil data absensi dengan relasi employee
            $attendances = MorningReflectionAttendance::with('employee')
                ->when($request->date, function($query, $date) {
                    return $query->whereDate('date', $date);
                })
                ->when($request->employee_id, function($query, $employee_id) {
                    return $query->where('employee_id', $employee_id);
                })
                ->orderBy('date', 'desc')
                ->get();

            // Transform data untuk memastikan konsistensi dan menambahkan field employee_name
            $transformedAttendances = $attendances->map(function ($attendance) {
                $data = $attendance->toArray();
                
                // Pastikan employee_id adalah integer
                $data['employee_id'] = (int) $data['employee_id'];
                
                // Tambahkan field employee_name jika employee ada
                if ($attendance->employee) {
                    $data['employee_name'] = $attendance->employee->nama_lengkap;
                    // Pastikan data employee juga konsisten
                    $data['employee']['id'] = (int) $attendance->employee->id;
                } else {
                    $data['employee_name'] = 'Karyawan Tidak Ditemukan';
                    $data['employee'] = null;
                    
                    // Log warning untuk data yang tidak konsisten
                    Log::warning('Morning reflection attendance with invalid employee_id', [
                        'attendance_id' => $attendance->id,
                        'employee_id' => $attendance->employee_id,
                        'date' => $attendance->date
                    ]);
                }
                
                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => $transformedAttendances,
                'message' => 'Data absensi renungan pagi berhasil diambil',
                'total_records' => $transformedAttendances->count()
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
} 