<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\EmployeeAttendance;
use App\Models\Employee;

class AttendanceExportController extends Controller
{
    /**
     * Export data absensi harian
     * GET /api/attendance/export/daily
     */
    public function exportDaily(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', now()->format('Y-m-d'));
            $format = $request->get('format', 'excel');
            
            Log::info('Export daily attendance', [
                'date' => $date,
                'format' => $format
            ]);

            // Ambil data absensi untuk tanggal tertentu
            $attendances = Attendance::where('date', $date)
                ->with(['employee'])
                ->get();

            // Ambil semua employee yang terdaftar di mesin
            $registeredEmployees = EmployeeAttendance::where('is_active', true)
                ->with('employee')
                ->get();

            // Buat mapping employee
            $employeeMap = [];
            foreach ($registeredEmployees as $emp) {
                $employeeMap[$emp->machine_user_id] = [
                    'id' => $emp->employee_id,
                    'nama' => $emp->employee ? $emp->employee->nama_lengkap : $emp->name,
                    'pin' => $emp->machine_user_id,
                    'card_number' => $emp->employee ? $emp->employee->NumCard : null
                ];
            }

            // Siapkan data untuk export
            $exportData = [];
            
            // Tambahkan data yang ada di attendance
            foreach ($attendances as $attendance) {
                $employeeInfo = $employeeMap[$attendance->user_pin] ?? [
                    'id' => $attendance->employee_id,
                    'nama' => $attendance->user_name,
                    'pin' => $attendance->user_pin,
                    'card_number' => $attendance->card_number
                ];
                
                $exportData[] = [
                    'employee_id' => $employeeInfo['id'],
                    'nama' => $employeeInfo['nama'],
                    'user_pin' => $employeeInfo['pin'],
                    'card_number' => $employeeInfo['card_number'],
                    'date' => $attendance->date,
                    'check_in' => $attendance->check_in ? $attendance->check_in->format('H:i:s') : null,
                    'check_out' => $attendance->check_out ? $attendance->check_out->format('H:i:s') : null,
                    'status' => $attendance->status,
                    'work_hours' => $attendance->work_hours,
                    'total_taps' => $attendance->total_taps,
                    'notes' => $attendance->notes
                ];
            }

            // Tambahkan employee yang tidak ada data absensi (absent)
            foreach ($registeredEmployees as $emp) {
                $hasAttendance = $attendances->where('user_pin', $emp->machine_user_id)->count() > 0;
                
                if (!$hasAttendance) {
                    $exportData[] = [
                        'employee_id' => $emp->employee_id,
                        'nama' => $emp->employee ? $emp->employee->nama_lengkap : $emp->name,
                        'user_pin' => $emp->machine_user_id,
                        'card_number' => $emp->employee ? $emp->employee->NumCard : null,
                        'date' => $date,
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'absent',
                        'work_hours' => 0,
                        'total_taps' => 0,
                        'notes' => 'Tidak ada data absensi'
                    ];
                }
            }

            // Urutkan berdasarkan nama
            usort($exportData, function($a, $b) {
                return strcmp($a['nama'], $b['nama']);
            });

            if ($format === 'excel') {
                return $this->generateExcelFile($exportData, $date, 'daily');
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Export berhasil',
                    'data' => [
                        'date' => $date,
                        'total_records' => count($exportData),
                        'records' => $exportData
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Export daily attendance error: ' . $e->getMessage());
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
            $month = (int) $request->get('month', now()->month);
            $year = (int) $request->get('year', now()->year);
            $format = $request->get('format', 'excel');
            
            Log::info('Export monthly attendance', [
                'month' => $month,
                'year' => $year,
                'format' => $format
            ]);

            // Validasi input
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulan harus antara 1-12'
                ], 422);
            }

            if ($year < 2020 || $year > 2030) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun harus antara 2020-2030'
                ], 422);
            }

            // Ambil data absensi untuk bulan tertentu
            $attendances = Attendance::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->with(['employee'])
                ->get()
                ->groupBy('user_pin');

            // Ambil semua employee yang terdaftar di mesin
            $registeredEmployees = EmployeeAttendance::where('is_active', true)
                ->with('employee')
                ->get();

            // Buat mapping employee
            $employeeMap = [];
            foreach ($registeredEmployees as $emp) {
                $employeeMap[$emp->machine_user_id] = [
                    'id' => $emp->employee_id,
                    'nama' => $emp->employee ? $emp->employee->nama_lengkap : $emp->name,
                    'pin' => $emp->machine_user_id,
                    'card_number' => $emp->employee ? $emp->employee->NumCard : null
                ];
            }

            // Generate working days untuk bulan tersebut
            $workingDays = $this->generateWorkingDays($year, $month);
            $monthName = Carbon::create($year, $month)->format('F');

            // Siapkan data matrix untuk export
            $exportData = [];
            
            foreach ($registeredEmployees as $emp) {
                $userPin = $emp->machine_user_id;
                $employeeInfo = $employeeMap[$userPin];
                $empAttendances = $attendances->get($userPin, collect());
                
                $employeeData = [
                    'employee_id' => $employeeInfo['id'],
                    'nama' => $employeeInfo['nama'],
                    'user_pin' => $employeeInfo['pin'],
                    'card_number' => $employeeInfo['card_number'],
                    'month' => $monthName,
                    'year' => $year,
                    'daily_data' => [],
                    'total_hadir' => 0,
                    'total_jam_kerja' => 0,
                    'total_terlambat' => 0,
                    'total_absen' => 0
                ];

                // Isi data harian
                foreach ($workingDays as $workingDay) {
                    $date = $workingDay['date'];
                    $day = $workingDay['day'];
                    
                    $attendance = $empAttendances->filter(function($item) use ($date) {
                        return $item->date->format('Y-m-d') === $date;
                    })->first();

                    if ($attendance) {
                        if (in_array($attendance->status, ['on_leave', 'sick_leave'])) {
                            $leaveType = $attendance->status === 'sick_leave' ? 'Sakit' : 'Cuti';
                            $employeeData['daily_data'][$day] = [
                                'status' => 'cuti',
                                'type' => $leaveType,
                                'check_in' => null,
                                'check_out' => null,
                                'work_hours' => 0
                            ];
                            $employeeData['total_hadir']++;
                        } elseif ($attendance->check_in) {
                            $isLate = $attendance->status === 'present_late';
                            $employeeData['daily_data'][$day] = [
                                'status' => $attendance->status,
                                'type' => $isLate ? 'Terlambat' : 'Hadir',
                                'check_in' => $attendance->check_in->format('H:i:s'),
                                'check_out' => $attendance->check_out ? $attendance->check_out->format('H:i:s') : null,
                                'work_hours' => $attendance->work_hours ?? 0
                            ];
                            $employeeData['total_hadir']++;
                            $employeeData['total_jam_kerja'] += $attendance->work_hours ?? 0;
                            if ($isLate) $employeeData['total_terlambat']++;
                        } else {
                            $employeeData['daily_data'][$day] = [
                                'status' => 'absent',
                                'type' => 'Absen',
                                'check_in' => null,
                                'check_out' => null,
                                'work_hours' => 0
                            ];
                            $employeeData['total_absen']++;
                        }
                    } else {
                        $employeeData['daily_data'][$day] = [
                            'status' => 'no_data',
                            'type' => 'Tidak Ada Data',
                            'check_in' => null,
                            'check_out' => null,
                            'work_hours' => 0
                        ];
                        $employeeData['total_absen']++;
                    }
                }

                $exportData[] = $employeeData;
            }

            // Urutkan berdasarkan nama
            usort($exportData, function($a, $b) {
                return strcmp($a['nama'], $b['nama']);
            });

            if ($format === 'excel') {
                return $this->generateExcelFile($exportData, $monthName . ' ' . $year, 'monthly');
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Export berhasil',
                    'data' => [
                        'month' => $monthName,
                        'year' => $year,
                        'total_employees' => count($exportData),
                        'working_days' => count($workingDays),
                        'records' => $exportData
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Export monthly attendance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint: GET /api/attendance/monthly-table
     * Ambil data absensi bulanan, group by nama
     */
    public function monthlyTable(Request $request): JsonResponse
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        if ($month < 1 || $month > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Bulan harus antara 1-12'
            ], 422);
        }
        if ($year < 2020 || $year > 2030) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun harus antara 2020-2030'
            ], 422);
        }

        $attendances = \App\Models\Attendance::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->orderBy('user_name')
            ->get();

        $workingDays = $this->generateWorkingDays($year, $month);
        $monthName = \Carbon\Carbon::create($year, $month)->format('F');

        // Group by nama normalisasi
        $grouped = [];
        foreach ($attendances as $att) {
            $normName = strtolower(trim($att->user_name));
            if (!isset($grouped[$normName])) {
                $grouped[$normName] = collect();
            }
            $grouped[$normName]->push($att);
        }

        $tableData = [];
        foreach ($grouped as $normName => $records) {
            $first = $records->first();
            $row = [
                'user_pin' => $first->user_pin,
                'nama' => $first->user_name, // tampilkan nama asli dari data pertama
                'card_number' => $first->card_number,
                'total_hadir' => 0,
                'total_jam_kerja' => 0,
                'total_absen' => 0,
                'daily_data' => []
            ];
            foreach ($workingDays as $workingDay) {
                $date = $workingDay['date'];
                $dayKey = (string)$workingDay['day'];
                // Perbandingan tanggal pakai string agar selalu cocok
                $att = $records->first(function($item) use ($date) {
                    $itemDate = $item->date instanceof \DateTime ? $item->date->format('Y-m-d') : (string)$item->date;
                    return $itemDate == $date;
                });
                if ($att) {
                    if (in_array($att->status, ['on_leave', 'sick_leave'])) {
                        $row['daily_data'][$dayKey] = [
                            'status' => 'cuti',
                            'type' => $att->status === 'sick_leave' ? 'Sakit' : 'Cuti',
                            'check_in' => null,
                            'check_out' => null,
                            'work_hours' => 0
                        ];
                        $row['total_hadir']++;
                    } elseif ($att->check_in) {
                        $row['daily_data'][$dayKey] = [
                            'status' => $att->status,
                            'type' => $att->status === 'present_late' ? 'Terlambat' : 'Hadir',
                            'check_in' => is_string($att->check_in) ? $att->check_in : $att->check_in->format('H:i:s'),
                            'check_out' => is_string($att->check_out) ? $att->check_out : $att->check_out->format('H:i:s'),
                            'work_hours' => $att->work_hours ?? 0
                        ];
                        $row['total_hadir']++;
                        $row['total_jam_kerja'] += $att->work_hours ?? 0;
                    } else {
                        $row['daily_data'][$dayKey] = [
                            'status' => 'absent',
                            'type' => 'Absen',
                            'check_in' => null,
                            'check_out' => null,
                            'work_hours' => 0
                        ];
                        $row['total_absen']++;
                    }
                } else {
                    $row['daily_data'][$dayKey] = [
                        'status' => 'no_data',
                        'type' => 'Tidak Ada Data',
                        'check_in' => null,
                        'check_out' => null,
                        'work_hours' => 0
                    ];
                    $row['total_absen']++;
                }
            }
            $tableData[] = $row;
        }

        usort($tableData, function($a, $b) {
            return strcmp($a['nama'], $b['nama']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data absensi bulanan berhasil diambil',
            'data' => [
                'month' => $monthName,
                'year' => $year,
                'working_days' => $workingDays,
                'records' => $tableData
            ]
        ]);
    }

    /**
     * Generate working days untuk bulan tertentu
     */
    private function generateWorkingDays($year, $month): array
    {
        $workingDays = [];
        $startDate = Carbon::create($year, $month, 1);
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
                $workingDays[] = [
                    'day' => $currentDate->day,
                    'date' => $currentDate->format('Y-m-d'),
                    'dayName' => $currentDate->format('D')
                ];
            }
            $currentDate->addDay();
        }
        
        return $workingDays;
    }

    /**
     * Generate Excel file
     */
    private function generateExcelFile($data, $period, $type): JsonResponse
    {
        try {
            $filename = 'Absensi_' . $type . '_' . str_replace(' ', '_', $period) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = storage_path('app/public/exports/' . $filename);
            
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            // Generate Excel content (simplified for now)
            $html = $this->generateExcelHTML($data, $period, $type);
            file_put_contents($filePath, $html);

            return response()->json([
                'success' => true,
                'message' => 'Export berhasil',
                'data' => [
                    'filename' => $filename,
                    'download_url' => url('storage/exports/' . $filename),
                    'direct_download_url' => url('api/attendance/export/download/' . $filename),
                    'total_records' => count($data)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Generate Excel file error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate file Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate HTML untuk Excel
     */
    private function generateExcelHTML($data, $period, $type): string
    {
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Absensi ' . $period . ' Hope Channel Indonesia</title>';
        $html .= '<style>';
        $html .= 'table { border-collapse: collapse; width: 100%; font-size: 11px; }';
        $html .= 'th, td { border: 1px solid #333; padding: 6px; text-align: center; }';
        $html .= 'th { background: #4CAF50; color: white; font-weight: bold; }';
        $html .= '.nama { text-align: left; font-weight: bold; background: #f0f0f0; }';
        $html .= '.hadir { background-color: #4CAF50; color: white; }';
        $html .= '.terlambat { background-color: #FFC107; color: #333; }';
        $html .= '.absen { background-color: #F44336; color: white; }';
        $html .= '.cuti { background-color: #2196F3; color: white; }';
        $html .= '.title { text-align: center; font-weight: bold; font-size: 18px; margin: 15px 0; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="title">📊 LAPORAN ABSENSI ' . strtoupper($period) . '</div>';
        $html .= '<div class="title" style="font-size: 16px;">Hope Channel Indonesia</div>';
        
        if ($type === 'daily') {
            $html .= $this->generateDailyTable($data);
        } else {
            $html .= $this->generateMonthlyTable($data);
        }
        
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }

    /**
     * Generate table untuk export harian
     */
    private function generateDailyTable($data): string
    {
        $html = '<table>';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>No</th>';
        $html .= '<th>Nama</th>';
        $html .= '<th>PIN</th>';
        $html .= '<th>Card Number</th>';
        $html .= '<th>Tanggal</th>';
        $html .= '<th>Check In</th>';
        $html .= '<th>Check Out</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Jam Kerja</th>';
        $html .= '<th>Total Taps</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($data as $index => $record) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td class="nama">' . htmlspecialchars($record['nama']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['user_pin']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['card_number']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['date']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['check_in'] ?? '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($record['check_out'] ?? '-') . '</td>';
            $html .= '<td class="' . $this->getStatusClass($record['status']) . '">' . htmlspecialchars($record['status']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['work_hours'] ?? 0) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['total_taps'] ?? 0) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Generate table untuk export bulanan
     */
    private function generateMonthlyTable($data): string
    {
        if (empty($data)) {
            return '<p>Tidak ada data untuk ditampilkan</p>';
        }

        $html = '<table>';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>No</th>';
        $html .= '<th>Nama</th>';
        $html .= '<th>PIN</th>';
        $html .= '<th>Total Hadir</th>';
        $html .= '<th>Total Jam Kerja</th>';
        $html .= '<th>Total Terlambat</th>';
        $html .= '<th>Total Absen</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($data as $index => $record) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td class="nama">' . htmlspecialchars($record['nama']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['user_pin']) . '</td>';
            $html .= '<td class="hadir">' . $record['total_hadir'] . '</td>';
            $html .= '<td>' . number_format($record['total_jam_kerja'], 2) . '</td>';
            $html .= '<td class="terlambat">' . $record['total_terlambat'] . '</td>';
            $html .= '<td class="absen">' . $record['total_absen'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Get CSS class berdasarkan status
     */
    private function getStatusClass($status): string
    {
        switch ($status) {
            case 'present_ontime':
                return 'hadir';
            case 'present_late':
                return 'terlambat';
            case 'absent':
                return 'absen';
            case 'on_leave':
            case 'sick_leave':
                return 'cuti';
            default:
                return '';
        }
    }

    /**
     * Download file
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

            return response()->download($filePath, $filename);

        } catch (\Exception $e) {
            Log::error('Download file error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
} 