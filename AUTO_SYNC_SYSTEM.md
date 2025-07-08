# AUTO-SYNC SYSTEM DOCUMENTATION

## Overview
Sistem auto-sinkronisasi yang secara otomatis menyesuaikan `employee_id` di semua tabel ketika ada kegiatan seperti absen, register, atau tambah pegawai. Sistem ini memastikan data konsisten di seluruh aplikasi berdasarkan nama yang sama persis.

## Fitur Utama

### 1. Auto-Sync Otomatis
- **Tambah Pegawai Baru**: Ketika HR menambah pegawai baru, sistem otomatis sinkronisasi ke semua tabel
- **Absensi Kantor**: Setiap kali ada absensi, sistem otomatis sinkronisasi data
- **Absensi Worship**: Setiap kali ada absensi renungan pagi, sistem otomatis sinkronisasi
- **Register User**: Ketika user register, sistem otomatis sinkronisasi dengan data employee

### 2. Manual Sync
- **Sync by Name**: Sinkronisasi berdasarkan nama employee
- **Sync by ID**: Sinkronisasi berdasarkan ID employee
- **Bulk Sync**: Sinkronisasi semua employee sekaligus
- **Sync Orphaned Records**: Sinkronisasi record yang tidak memiliki employee_id

### 3. Monitoring
- **Sync Status**: Melihat status sinkronisasi semua employee
- **Sync Issues**: Mendeteksi masalah sinkronisasi
- **Sync Results**: Melihat hasil sinkronisasi

## Tabel yang Disinkronisasi

### 1. Users Table
- Menghubungkan user dengan employee berdasarkan nama
- Sinkronisasi role dengan jabatan employee
- Membuat user otomatis jika belum ada

### 2. Attendance Tables
- **employee_attendance**: Menghubungkan dengan mesin absensi
- **attendances**: Data absensi harian
- **attendance_logs**: Log tap mesin absensi

### 3. Morning Reflection Attendance
- Data absensi renungan pagi
- Menghubungkan dengan employee berdasarkan nama

### 4. Leave Tables
- **leave_quotas**: Jatah cuti employee
- **leave_requests**: Pengajuan cuti

### 5. Employee Related Tables
- **employee_documents**: Dokumen employee
- **employment_histories**: Riwayat pekerjaan
- **promotion_histories**: Riwayat promosi
- **benefits**: Benefit employee
- **trainings**: Training employee

## Auto-Sync Triggers

### 1. Employee Creation (EmployeeController)
```php
// Ketika HR menambah pegawai baru
$syncResult = \App\Services\EmployeeSyncService::autoSyncNewEmployee($employee);
```

### 2. Attendance Sync (AttendanceController)
```php
// Ketika ada absensi dari mesin
$syncResults = [];
$uniqueUserNames = \App\Models\Attendance::whereNotNull('user_name')
                                        ->whereNull('employee_id')
                                        ->distinct()
                                        ->pluck('user_name');

foreach ($uniqueUserNames as $userName) {
    $syncResult = \App\Services\EmployeeSyncService::autoSyncAttendance($userName);
    $syncResults[$userName] = $syncResult;
}
```

### 3. Morning Reflection (MorningReflectionController)
```php
// Ketika ada absensi renungan pagi
$employee = \App\Models\Employee::find($request->employee_id);
if ($employee) {
    $syncResult = \App\Services\EmployeeSyncService::autoSyncMorningReflection($employee->nama_lengkap);
}
```

### 4. User Registration (AuthController)
```php
// Ketika user register
$syncResult = \App\Services\EmployeeSyncService::autoSyncUserRegistration($request->name);
```

## Manual Sync Endpoints

### 1. Sync by Name
**POST** `/api/employee-sync/sync-by-name`

```json
{
    "employee_name": "John Doe",
    "employee_id": 1 // optional
}
```

### 2. Sync by ID
**POST** `/api/employee-sync/sync-by-id`

```json
{
    "employee_id": 1
}
```

### 3. Bulk Sync
**POST** `/api/employee-sync/bulk-sync`

```json
{
    "employee_ids": [1, 2, 3] // optional, jika kosong sync semua
}
```

### 4. Get Sync Status
**GET** `/api/employee-sync/status`

Response:
```json
{
    "success": true,
    "data": {
        "total_employees": 8,
        "needs_sync_count": 2,
        "synced_count": 6,
        "employees": [
            {
                "employee_id": 1,
                "nama_lengkap": "John Doe",
                "sync_status": {
                    "has_user": true,
                    "has_employee_attendance": true,
                    "needs_sync": false,
                    "sync_issues": []
                }
            }
        ]
    }
}
```

### 5. Sync Orphaned Records
**POST** `/api/employee-sync/sync-orphaned-records`

Sync semua record yang tidak memiliki employee_id.

## Service Methods

### EmployeeSyncService

#### 1. syncEmployeeByName($employeeName, $employeeId = null)
Sinkronisasi employee berdasarkan nama.

#### 2. syncEmployeeById($employeeId)
Sinkronisasi employee berdasarkan ID.

#### 3. autoSyncNewEmployee($employee)
Auto-sync untuk employee baru.

#### 4. autoSyncAttendance($userName, $userPin = null)
Auto-sync untuk absensi.

#### 5. autoSyncUserRegistration($userName)
Auto-sync untuk registrasi user.

#### 6. autoSyncMorningReflection($employeeName)
Auto-sync untuk absensi renungan pagi.

#### 7. bulkSyncAllEmployees()
Bulk sync semua employee.

## Sync Process Flow

### 1. Find Employee
```php
$employee = Employee::where('nama_lengkap', $employeeName)->first();
```

### 2. Sync Users Table
```php
// Find users with matching name but no employee_id
$users = User::where('name', $employee->nama_lengkap)
            ->whereNull('employee_id')
            ->get();

foreach ($users as $user) {
    $user->update([
        'employee_id' => $employee->id,
        'role' => $employee->jabatan_saat_ini
    ]);
}
```

### 3. Sync Attendance Tables
```php
// Update employee_attendance
$employeeAttendance = EmployeeAttendance::where('name', $employee->nama_lengkap)
                                       ->whereNull('employee_id')
                                       ->first();

if ($employeeAttendance) {
    $employeeAttendance->update(['employee_id' => $employee->id]);
    
    // Update attendance and logs
    Attendance::where('user_name', $employee->nama_lengkap)
              ->whereNull('employee_id')
              ->update(['employee_id' => $employee->id]);
}
```

### 4. Sync Other Tables
```php
// Sync morning reflection, leave, documents, etc.
MorningReflectionAttendance::where('employee_id', null)
                           ->whereHas('employee', function($query) use ($employee) {
                               $query->where('nama_lengkap', $employee->nama_lengkap);
                           })
                           ->update(['employee_id' => $employee->id]);
```

## Error Handling

### 1. Employee Not Found
```json
{
    "success": false,
    "message": "Employee not found",
    "data": {
        "employee_found": false,
        "employee_id": null,
        "sync_operations": [],
        "errors": []
    }
}
```

### 2. Sync Errors
```json
{
    "success": false,
    "message": "Employee sync failed: [error message]",
    "data": {
        "employee_found": true,
        "employee_id": 1,
        "sync_operations": {
            "users": {
                "updated": 1,
                "created": 0,
                "errors": []
            },
            "attendance": {
                "attendance_updated": 5,
                "attendance_logs_updated": 10,
                "employee_attendance_updated": 1,
                "errors": []
            }
        },
        "errors": []
    }
}
```

## Logging

Sistem mencatat semua aktivitas sinkronisasi:

```php
Log::info('Employee sync completed successfully', [
    'employee_id' => $employee->id,
    'employee_name' => $employee->nama_lengkap,
    'sync_results' => $syncResults
]);
```

## Best Practices

### 1. Nama Harus Sama Persis
- Sistem menggunakan exact match untuk nama
- Pastikan format nama konsisten (contoh: "John Doe" bukan "john doe")

### 2. Regular Sync Check
- Gunakan endpoint `/api/employee-sync/status` untuk monitoring
- Jalankan bulk sync secara berkala

### 3. Handle Orphaned Records
- Gunakan `/api/employee-sync/sync-orphaned-records` untuk cleanup
- Jalankan setelah import data besar

### 4. Monitor Logs
- Perhatikan log sinkronisasi untuk error
- Setup alert untuk sync failures

## Testing

### Test Auto-Sync
```bash
# Test sync by name
curl -X POST http://localhost/api/employee-sync/sync-by-name \
  -H "Authorization: Bearer [token]" \
  -H "Content-Type: application/json" \
  -d '{"employee_name": "John Doe"}'

# Test bulk sync
curl -X POST http://localhost/api/employee-sync/bulk-sync \
  -H "Authorization: Bearer [token]" \
  -H "Content-Type: application/json"

# Check sync status
curl -X GET http://localhost/api/employee-sync/status \
  -H "Authorization: Bearer [token]"
```

## Monitoring Dashboard

Gunakan endpoint `/api/employee-sync/status` untuk membuat dashboard monitoring:

```javascript
// Frontend example
async function getSyncStatus() {
    const response = await fetch('/api/employee-sync/status', {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    const data = await response.json();
    
    console.log(`Total: ${data.data.total_employees}`);
    console.log(`Needs Sync: ${data.data.needs_sync_count}`);
    console.log(`Synced: ${data.data.synced_count}`);
}
```

## Troubleshooting

### 1. Employee Not Found
- Pastikan nama employee ada di tabel employees
- Cek format nama (case sensitive)

### 2. Sync Not Working
- Cek log untuk error detail
- Pastikan employee_id tidak null di tabel target
- Verifikasi foreign key constraints

### 3. Performance Issues
- Gunakan bulk sync untuk data besar
- Monitor query performance
- Consider indexing on nama_lengkap columns 