# Leave Attendance Integration API Documentation

Dokumentasi ini menjelaskan API endpoints untuk integrasi logika cuti dengan sistem absensi kantor dan absensi worship.

## Overview

Sistem ini secara otomatis mengintegrasikan status cuti yang telah disetujui ke dalam tabel absensi kantor (`attendances`) dan absensi worship (`morning_reflection_attendance`). Ketika cuti disetujui, status absensi akan otomatis diperbarui untuk mencerminkan bahwa karyawan sedang cuti.

## Features

1. **Automatic Leave Sync**: Otomatis sinkronisasi status cuti ke tabel absensi
2. **Export Integration**: Export Excel otomatis menyertakan status cuti
3. **Manual Sync**: Endpoint untuk sinkronisasi manual
4. **Scheduled Sync**: Sinkronisasi otomatis harian melalui cron job
5. **Leave Status Mapping**: Mapping jenis cuti ke status absensi yang sesuai

## API Endpoints

### 1. Sync Leave to Attendance (Single Date)

**Endpoint:** `POST /api/attendance/sync-leave`

**Description:** Sinkronisasi status cuti ke absensi untuk tanggal tertentu

**Parameters:**
```json
{
  "date": "2025-01-29",        // Optional, default: today
  "employee_id": 123           // Optional, sync specific employee
}
```

**Response:**
```json
{
  "success": true,
  "message": "Leave status berhasil disinkronisasi ke attendance",
  "data": {
    "date": "2025-01-29",
    "employee_id": 123,
    "result": {
      "attendance_updated": 2,
      "morning_reflection_updated": 2,
      "employees_on_leave": [
        {
          "employee_id": 123,
          "employee_name": "John Doe",
          "leave_type": "annual",
          "attendance_status": "on_leave",
          "morning_reflection_status": "Cuti"
        }
      ]
    }
  }
}
```

### 2. Sync Leave to Attendance (Date Range)

**Endpoint:** `POST /api/attendance/sync-leave-date-range`

**Description:** Sinkronisasi status cuti ke absensi untuk rentang tanggal

**Parameters:**
```json
{
  "start_date": "2025-01-29",   // Required
  "end_date": "2025-01-31",     // Required
  "employee_id": 123            // Optional, sync specific employee
}
```

**Response:**
```json
{
  "success": true,
  "message": "Leave status berhasil disinkronisasi ke attendance untuk rentang tanggal",
  "data": {
    "start_date": "2025-01-29",
    "end_date": "2025-01-31",
    "employee_id": null,
    "result": {
      "total_days_processed": 3,
      "total_attendance_updated": 6,
      "total_morning_reflection_updated": 6,
      "summary_by_date": {
        "2025-01-29": {
          "attendance_updated": 2,
          "morning_reflection_updated": 2
        },
        "2025-01-30": {
          "attendance_updated": 2,
          "morning_reflection_updated": 2
        },
        "2025-01-31": {
          "attendance_updated": 2,
          "morning_reflection_updated": 2
        }
      }
    }
  }
}
```

### 3. Export Attendance with Leave Integration

**Endpoint:** `GET /api/attendance/export/daily`

**Description:** Export absensi harian dengan integrasi status cuti

**Parameters:**
```
date=2025-01-29&format=excel
```

**Response:** File Excel dengan status cuti terintegrasi

**Endpoint:** `GET /api/attendance/export/monthly`

**Description:** Export absensi bulanan dengan integrasi status cuti

**Parameters:**
```
month=2025-01&format=excel
```

**Response:** File Excel dengan status cuti terintegrasi

## Artisan Commands

### 1. Sync Leave to Attendance Command

**Command:** `php artisan attendance:sync-leave`

**Options:**
- `--date=YYYY-MM-DD`: Sync untuk tanggal tertentu
- `--start-date=YYYY-MM-DD --end-date=YYYY-MM-DD`: Sync untuk rentang tanggal
- `--today`: Sync untuk hari ini (default)
- `--employee-id=ID`: Sync untuk karyawan tertentu

**Examples:**
```bash
# Sync untuk hari ini
php artisan attendance:sync-leave --today

# Sync untuk tanggal tertentu
php artisan attendance:sync-leave --date=2025-01-29

# Sync untuk rentang tanggal
php artisan attendance:sync-leave --start-date=2025-01-01 --end-date=2025-01-31

# Sync untuk karyawan tertentu
php artisan attendance:sync-leave --today --employee-id=123
```

## Scheduled Tasks

Sistem secara otomatis menjalankan sinkronisasi harian pada pukul 07:30 WIB:

```php
// app/Console/Kernel.php
$schedule->command('attendance:sync-leave --today')
         ->dailyAt('07:30')
         ->appendOutputTo(storage_path('logs/attendance-sync-leave.log'));
```

## Leave Status Mapping

Mapping jenis cuti ke status absensi:

| Leave Type | Attendance Status | Morning Reflection Status |
|------------|-------------------|---------------------------|
| sick       | sick_leave        | Cuti                      |
| annual     | on_leave          | Cuti                      |
| emergency  | on_leave          | Cuti                      |
| maternity  | on_leave          | Cuti                      |
| paternity  | on_leave          | Cuti                      |
| other      | on_leave          | Cuti                      |

## Database Changes

### 1. Morning Reflection Attendance Status Update

Tabel `morning_reflection_attendance` telah diperbarui untuk menambahkan status 'Cuti':

```sql
ALTER TABLE morning_reflection_attendance 
MODIFY COLUMN status ENUM('Hadir', 'Terlambat', 'Absen', 'Cuti') NOT NULL;
```

### 2. Attendance Table

Tabel `attendances` sudah memiliki status cuti:
- `on_leave`: Cuti umum
- `sick_leave`: Cuti sakit

## Service Class

### LeaveAttendanceIntegrationService

Service utama untuk integrasi cuti dengan absensi:

**Location:** `app/Services/LeaveAttendanceIntegrationService.php`

**Key Methods:**
- `syncLeaveStatusToAttendance($date, $employeeId = null)`
- `syncLeaveStatusForDateRange($startDate, $endDate, $employeeId = null)`
- `handleLeaveApproval($leaveRequest)`
- `handleLeaveRejection($leaveRequest)`
- `isEmployeeOnLeave($employeeId, $date)`

## Error Handling

Semua endpoint mengembalikan response error yang konsisten:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": ["validation error"]
  }
}
```

## Integration Points

### 1. Leave Request Approval

Ketika cuti disetujui melalui `LeaveRequestController::approve()`, sistem otomatis:
1. Update status absensi untuk seluruh periode cuti
2. Update status morning reflection attendance
3. Set notes yang sesuai

### 2. Leave Request Rejection

Ketika cuti ditolak melalui `LeaveRequestController::reject()`, sistem otomatis:
1. Reset status absensi ke 'absent' atau recalculate jika ada tap
2. Reset status morning reflection attendance ke 'Absen'
3. Clear notes terkait cuti

### 3. Export Integration

Sebelum export, sistem otomatis menjalankan sinkronisasi cuti untuk memastikan data terbaru.

## Testing

Untuk testing, gunakan endpoint berikut:

```bash
# Test sync untuk hari ini
curl -X POST http://localhost:8000/api/attendance/sync-leave \
  -H "Content-Type: application/json" \
  -d '{"date": "2025-01-29"}'

# Test sync untuk rentang tanggal
curl -X POST http://localhost:8000/api/attendance/sync-leave-date-range \
  -H "Content-Type: application/json" \
  -d '{"start_date": "2025-01-29", "end_date": "2025-01-31"}'
```

## Monitoring

Log file untuk monitoring:
- `storage/logs/attendance-sync-leave.log`: Log scheduled sync
- `storage/logs/laravel.log`: Log error dan debug

## Performance Considerations

1. **Batch Processing**: Sync dilakukan dalam batch untuk performa optimal
2. **Selective Updates**: Hanya update record yang perlu diubah
3. **Efficient Queries**: Menggunakan query yang optimal untuk mengurangi load database
4. **Caching**: Hasil query leave request di-cache untuk mengurangi query berulang

## Security

1. **Authentication**: Semua endpoint memerlukan autentikasi (kecuali yang disebutkan)
2. **Validation**: Input validation ketat untuk semua parameter
3. **Authorization**: Role-based access control untuk operasi sensitif
4. **Audit Trail**: Log semua operasi untuk audit