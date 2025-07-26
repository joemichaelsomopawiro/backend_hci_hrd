# Employee Attendance Auto-Sync System

## Overview
Sistem ini menyelesaikan masalah `employee_id` yang `NULL` di tabel `attendances` dengan melakukan auto-sync berdasarkan nama karyawan setiap kali upload file TXT attendance.

## Masalah yang Diselesaikan
- `employee_id` di tabel `attendances` masih `NULL` setelah upload TXT
- Data attendance tidak terhubung dengan data employee
- Sulit untuk melakukan laporan attendance berdasarkan employee

## Solusi yang Diimplementasikan

### 1. Model Attendance (Fixed)
**File:** `app/Models/Attendance.php`

```php
protected $fillable = [
    // ... existing fields ...
    'employee_id', // ✅ DITAMBAHKAN untuk auto-sync dengan Employee
];

// ✅ DITAMBAHKAN relationship ke Employee
public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class, 'employee_id');
}
```

### 2. Enhanced Auto-Sync Logic
**File:** `app/Services/TxtAttendanceUploadService.php`

#### A. Smart Name Matching
Sistem menggunakan 3 prioritas matching:

1. **Exact Match**: `user_name` = `nama_lengkap` (exact)
2. **Case-Insensitive Match**: Handle perbedaan huruf besar/kecil  
3. **Partial Match**: Handle nama yang tidak lengkap atau format berbeda
4. **Card Number Fallback**: Jika nama tidak ditemukan, coba card number

```php
private function saveAttendance($data)
{
    $employee_id = null;
    $mapped_by = null;
    
    // PRIORITAS 1: Exact match
    $employee = Employee::where('nama_lengkap', $data['user_name'])->first();
    if ($employee) {
        $employee_id = $employee->id;
        $mapped_by = 'exact_name_match';
    } else {
        // PRIORITAS 2: Case-insensitive + Partial match
        // PRIORITAS 3: Card number fallback
    }
    
    // Save dengan employee_id
    Attendance::updateOrCreate([...], [
        // ... other fields ...
        'employee_id' => $employee_id // 🔥 AUTO-SYNC
    ]);
}
```

#### B. Bulk Auto-Sync After Upload
Setiap upload TXT memicu bulk sync otomatis:

```php
private function bulkAutoSyncAfterUpload(): void
{
    // STEP 1: Direct sync semua attendance yang belum ter-sync
    $unsyncedAttendances = Attendance::whereNull('employee_id')
                                    ->whereNotNull('user_name')
                                    ->get();
    
    foreach ($unsyncedAttendances as $attendance) {
        // Smart matching logic...
        if ($employee_id) {
            $attendance->update(['employee_id' => $employee_id]);
        }
    }
    
    // STEP 2: Cross-validation dengan EmployeeSyncService
}
```

### 3. Enhanced EmployeeSyncService
**File:** `app/Services/EmployeeSyncService.php`

```php
private static function syncAttendanceTables($employee)
{
    // PRIORITAS 1: Exact name match
    $attendanceUpdated = Attendance::where('user_name', $employee->nama_lengkap)
                                  ->whereNull('employee_id')
                                  ->update(['employee_id' => $employee->id]);
    
    // PRIORITAS 2: Case-insensitive match
    if ($attendanceUpdated == 0) {
        $attendanceCaseInsensitive = Attendance::whereRaw('LOWER(TRIM(user_name)) = ?', [strtolower(trim($employee->nama_lengkap))])
                                               ->whereNull('employee_id')
                                               ->update(['employee_id' => $employee->id]);
    }
    
    // PRIORITAS 3: Card number fallback
    if ($results['attendance_updated'] == 0 && !empty($employee->NumCard)) {
        $attendanceByCard = Attendance::where('card_number', $employee->NumCard)
                                      ->whereNull('employee_id')
                                      ->update(['employee_id' => $employee->id]);
    }
}
```

### 4. New API Endpoints
**File:** `app/Http/Controllers/AttendanceTxtUploadController.php`

#### A. Manual Bulk Sync
```
POST /api/attendance/upload-txt/manual-sync
```
**Response:**
```json
{
    "success": true,
    "message": "Manual bulk sync completed",
    "data": {
        "total_attendance": 1500,
        "synced_attendance": 1350,
        "unsynced_attendance": 150,
        "sync_percentage": 90.0
    }
}
```

#### B. Sync Status Check
```
GET /api/attendance/upload-txt/sync-status
```
**Response:**
```json
{
    "success": true,
    "message": "Status sync berhasil diambil",
    "data": {
        "total_attendance": 1500,
        "synced_attendance": 1350,
        "unsynced_attendance": 150,
        "sync_percentage": 90.0,
        "unsynced_samples": [...],
        "synced_samples": [...],
        "total_employees": 45
    }
}
```

## Alur Kerja Auto-Sync

### 1. Upload TXT File
```
User upload TXT → AttendanceTxtUploadController.uploadTxt()
                ↓
                TxtAttendanceUploadService.processTxtUpload()
                ↓
                saveAttendance() with smart matching
                ↓
                bulkAutoSyncAfterUpload() for remaining data
```

### 2. Smart Matching Process
```
Input: user_name = "Jefri Siadari"
       card_number = "12345"

Step 1: Exact match
        Employee::where('nama_lengkap', 'Jefri Siadari') → Found ✅
        
Step 2: Case-insensitive (if Step 1 failed)
        Employee::whereRaw('LOWER(TRIM(nama_lengkap)) = ?', ['jefri siadari'])
        
Step 3: Partial match (if Step 2 failed)
        Check if "Jefri Siadari" contains any employee name or vice versa
        
Step 4: Card fallback (if Step 3 failed)
        Employee::where('NumCard', '12345')

Result: employee_id = 15 (found), mapped_by = 'exact_name_match'
```

### 3. Bulk Sync Process
```
After TXT upload success:
1. Find all Attendance with employee_id = NULL
2. Apply smart matching to each record
3. Update employee_id if match found
4. Log results with detailed statistics
```

## Testing & Verification

### 1. Manual Testing Script
**File:** `test_employee_sync_fixed.php`

```bash
php test_employee_sync_fixed.php
```

**Output Example:**
```
=== TEST EMPLOYEE SYNC SYSTEM ===

1. Checking current sync status...
   Total Attendance: 1500
   Synced: 1350
   Unsynced: 150
   Sync Percentage: 90%

3. Testing manual bulk sync...
   Sync Result: SUCCESS
   Data: {
     "total_attendance": 1500,
     "synced_attendance": 1425,
     "unsynced_attendance": 75,
     "sync_percentage": 95.0
   }

✅ SUCCESS: Sync percentage improved from 90% to 95%
🎉 EXCELLENT: Sync percentage is very good (95%)
```

### 2. API Testing
```bash
# Check sync status
curl -X GET "http://your-domain/api/attendance/upload-txt/sync-status" \
     -H "Authorization: Bearer YOUR_TOKEN"

# Manual bulk sync
curl -X POST "http://your-domain/api/attendance/upload-txt/manual-sync" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

## Debugging & Monitoring

### 1. Log Monitoring
```bash
# Monitor upload logs
tail -f storage/logs/laravel.log | grep "Employee mapping"

# Monitor sync results  
tail -f storage/logs/laravel.log | grep "Bulk auto-sync completed"
```

### 2. Database Queries
```sql
-- Check sync status
SELECT 
    COUNT(*) as total,
    COUNT(employee_id) as synced,
    COUNT(*) - COUNT(employee_id) as unsynced
FROM attendances;

-- Check unsynced data with employee matches
SELECT 
    a.user_name, 
    a.card_number,
    e.id as employee_id,
    e.nama_lengkap
FROM attendances a
LEFT JOIN employees e ON a.user_name = e.nama_lengkap
WHERE a.employee_id IS NULL
LIMIT 10;
```

### 3. Notes Analysis
Setiap record attendance menyimpan informasi mapping di kolom `notes`:

```json
{
    "employee_id": 15,
    "mapped_by": "exact_name_match",
    "sync_status": "synced", 
    "sync_timestamp": "2025-01-23T10:30:00.000000Z"
}
```

**Mapping Types:**
- `exact_name_match`: Nama persis sama
- `case_insensitive_match`: Cocok setelah case insensitive
- `partial_name_match`: Cocok secara partial
- `card_number_match`: Cocok berdasarkan card number

## Best Practices

### 1. Upload TXT
- Pastikan nama di file TXT sesuai dengan `nama_lengkap` di tabel employees
- Format konsisten untuk card number
- Upload secara berkala untuk hasil sync yang optimal

### 2. Employee Data Management
- Pastikan `nama_lengkap` di tabel employees lengkap dan akurat
- Update `NumCard` untuk semua employee
- Hindari duplikasi nama

### 3. Monitoring
- Cek sync percentage secara berkala
- Run manual bulk sync jika percentage rendah
- Monitor logs untuk error mapping

## Troubleshooting

### Sync Percentage Rendah (< 70%)
1. Cek format nama di file TXT vs database employees
2. Run manual bulk sync: `POST /api/attendance/upload-txt/manual-sync`  
3. Periksa log untuk error mapping
4. Update data employee jika ada inkonsistensi

### Employee Tidak Ditemukan
1. Cek `nama_lengkap` di tabel employees
2. Cek format nama di file TXT (typo, spasi extra, etc)
3. Periksa `NumCard` sebagai fallback
4. Update data employee jika perlu

### Performance Issues
1. Bulk sync dioptimalkan dengan batch processing
2. Index pada kolom `user_name` dan `employee_id`
3. Monitor memory usage saat bulk sync data besar

## Summary

✅ **SOLVED**: `employee_id` otomatis ter-sync setiap upload TXT
✅ **SMART**: Multi-level matching (exact, case-insensitive, partial, card)  
✅ **AUTOMATED**: Auto bulk sync setelah upload
✅ **MONITORED**: API endpoints untuk status dan manual sync
✅ **LOGGED**: Detailed logging untuk debugging
✅ **TESTED**: Comprehensive testing script dan API testing

Sistem ini memastikan setiap data attendance yang diupload akan otomatis terhubung dengan employee yang sesuai berdasarkan nama, dengan fallback ke card number jika nama tidak cocok. 