<?php

namespace App\Services;

use App\Models\MorningReflectionAttendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ManualAttendanceService
{
    /**
     * Simpan data absensi manual untuk hari Selasa & Kamis
     */
    public function storeManualAttendance(array $attendanceData, string $date)
    {
        try {
            DB::beginTransaction();

            // Validasi tanggal harus Selasa atau Kamis
            $this->validateWorshipDay($date);

            $savedCount = 0;
            $errors = [];

            foreach ($attendanceData as $data) {
                try {
                    // Validasi data
                    $this->validateAttendanceData($data);

                    // Cek apakah sudah ada data untuk employee dan tanggal ini
                    $existingAttendance = MorningReflectionAttendance::where([
                        'employee_id' => $data['pegawai_id'],
                        'date' => $date
                    ])->first();

                    if ($existingAttendance) {
                        // Update data existing
                        $existingAttendance->update([
                            'status' => $this->mapStatusToDatabase($data['status']),
                            'attendance_method' => 'manual',
                            'attendance_source' => 'manual_input',
                            'join_time' => now()
                        ]);
                    } else {
                        // Buat data baru
                        MorningReflectionAttendance::create([
                            'employee_id' => $data['pegawai_id'],
                            'date' => $date,
                            'status' => $this->mapStatusToDatabase($data['status']),
                            'attendance_method' => 'manual',
                            'attendance_source' => 'manual_input',
                            'join_time' => now(),
                            'testing_mode' => false
                        ]);
                    }

                    $savedCount++;
                } catch (Exception $e) {
                    $errors[] = "Error untuk pegawai ID {$data['pegawai_id']}: " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info('Manual worship attendance saved', [
                'date' => $date,
                'saved_count' => $savedCount,
                'total_data' => count($attendanceData),
                'errors' => $errors
            ]);

            return [
                'success' => true,
                'saved_count' => $savedCount,
                'total_data' => count($attendanceData),
                'errors' => $errors
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error saving manual worship attendance', [
                'error' => $e->getMessage(),
                'date' => $date,
                'data' => $attendanceData
            ]);

            throw $e;
        }
    }

    /**
     * Validasi bahwa tanggal adalah hari Selasa atau Kamis
     */
    private function validateWorshipDay(string $date)
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek; // 2 = Selasa, 4 = Kamis

        if (!in_array($dayOfWeek, [2, 4])) {
            throw new Exception('Input manual hanya diperbolehkan untuk hari Selasa dan Kamis');
        }
    }

    /**
     * Validasi data absensi
     */
    private function validateAttendanceData(array $data)
    {
        // Validasi pegawai_id
        if (!isset($data['pegawai_id']) || empty($data['pegawai_id'])) {
            throw new Exception('Pegawai ID tidak boleh kosong');
        }

        // Cek apakah employee exists
        $employee = Employee::find($data['pegawai_id']);
        if (!$employee) {
            throw new Exception("Pegawai dengan ID {$data['pegawai_id']} tidak ditemukan");
        }

        // Validasi status
        if (!isset($data['status']) || !in_array($data['status'], ['present', 'late', 'absent'])) {
            throw new Exception('Status harus present, late, atau absent');
        }
    }

    /**
     * Map status dari frontend ke database
     */
    private function mapStatusToDatabase(string $status): string
    {
        $statusMap = [
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Absen'
        ];

        return $statusMap[$status] ?? 'Absen';
    }

    /**
     * Map status dari database ke frontend
     */
    public function mapStatusToFrontend(string $status): string
    {
        $statusMap = [
            'Hadir' => 'present',
            'Terlambat' => 'late',
            'Absen' => 'absent'
        ];

        return $statusMap[$status] ?? 'absent';
    }

    /**
     * Update data existing untuk set attendance_method dan attendance_source
     */
    public function updateExistingData()
    {
        try {
            $updatedCount = MorningReflectionAttendance::whereNull('attendance_method')
                ->orWhereNull('attendance_source')
                ->update([
                    'attendance_method' => 'online',
                    'attendance_source' => 'zoom'
                ]);

            Log::info('Updated existing worship attendance data', [
                'updated_count' => $updatedCount
            ]);

            return $updatedCount;
        } catch (Exception $e) {
            Log::error('Error updating existing worship attendance data', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get worship attendance dengan filter metode
     */
    public function getWorshipAttendanceWithMethod($date = null, $attendanceMethod = null)
    {
        $query = MorningReflectionAttendance::with('employee');

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($attendanceMethod) {
            $query->byAttendanceMethod($attendanceMethod);
        }

        return $query->get()->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'pegawai_id' => $attendance->employee_id,
                'nama_lengkap' => $attendance->employee->nama_lengkap ?? 'Unknown',
                'status' => $this->mapStatusToFrontend($attendance->status),
                'tanggal' => $attendance->date->format('Y-m-d'),
                'attendance_method' => $attendance->attendance_method,
                'attendance_source' => $attendance->attendance_source,
                'created_at' => $attendance->created_at->format('Y-m-d H:i:s')
            ];
        });
    }
}